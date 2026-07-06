<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\PhoneUtils;
use App\Libraries\WhatsApp\TemplateSendBuilder;
use App\Models\BroadcastModel;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\MessageTemplateModel;
use App\Models\TagModel;
use App\Models\WhatsAppConfigModel;

class BroadcastsController extends BaseController
{
    public function countRecipients()
    {
        $body         = json_decode($this->request->getBody(), true) ?? [];
        $audienceType = $body['audience_type'] ?? 'all';
        $tagIds       = $body['tag_ids'] ?? [];

        $contactModel = new ContactModel();

        if ($audienceType === 'all') {
            $count = $contactModel->countAllResults();
        } elseif ($audienceType === 'tags' && !empty($tagIds)) {
            $db    = \Config\Database::connect();
            $count = $db->table('contacts c')
                ->join('contact_tags ct', 'ct.contact_id = c.id')
                ->whereIn('ct.tag_id', $tagIds)
                ->where('c.account_id', session('account_id'))
                ->groupBy('c.id')
                ->countAllResults();
        } else {
            $count = 0;
        }

        return $this->response->setJSON(['count' => $count]);
    }

    public function getProgress(string $broadcastId)
    {
        $broadcast = (new BroadcastModel())->find($broadcastId);
        if (!$broadcast) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found']);
        }

        $total = (int) $broadcast['total_recipients'];
        $done  = (int) $broadcast['sent_count'] + (int) $broadcast['failed_count'];
        $pct   = $total > 0 ? round($done / $total * 100) : 0;

        return $this->response->setJSON([
            'status'            => $broadcast['status'],
            'total_recipients'  => $total,
            'sent_count'        => (int) $broadcast['sent_count'],
            'delivered_count'   => (int) $broadcast['delivered_count'],
            'read_count'        => (int) $broadcast['read_count'],
            'failed_count'      => (int) $broadcast['failed_count'],
            'percentage'        => $pct,
        ]);
    }

    public function quickSend()
    {
        $templateId  = $this->request->getPost('template_id');
        $numbersRaw  = $this->request->getPost('numbers') ?? '';
        $scheduleAt  = $this->request->getPost('schedule_at'); // optional datetime-local value
        $headerUrl   = trim($this->request->getPost('header_url') ?? '');

        if (!$templateId || !trim($numbersRaw)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'template_id and numbers required']);
        }

        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Template not found']);
        }

        if (in_array($template['header_type'] ?? 'none', ['image', 'video', 'document'], true) && !$headerUrl) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'This template has a media header — a header URL is required']);
        }

        $numbers = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $numbersRaw))));

        // If scheduled — store as broadcast record, picked up by run:scheduled command
        if ($scheduleAt) {
            $scheduledAt = date('Y-m-d H:i:s', strtotime($scheduleAt));
            $broadcastModel = new BroadcastModel();
            $broadcastId = $broadcastModel->insert([
                'account_id'        => session('account_id'),
                'name'             => 'Quick: ' . $template['name'] . ' (' . date('d M H:i', strtotime($scheduledAt)) . ')',
                'template_name'    => $template['name'],
                'template_language'=> $template['language'] ?? 'en',
                'header_media_url' => $headerUrl ?: null,
                'audience_filter'  => json_encode(['type' => 'manual', 'numbers' => $numbers]),
                'status'           => 'scheduled',
                'scheduled_at'     => $scheduledAt,
                'total_recipients' => count($numbers),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON([
                'success'      => true,
                'scheduled'    => true,
                'scheduled_at' => date('d M Y, g:i A', strtotime($scheduledAt)),
                'broadcast_id' => $broadcastId,
            ]);
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if (!$waConfig || $waConfig['status'] !== 'connected') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'WhatsApp not connected']);
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
        $metaApi     = new MetaApi();

        // Same fallback as SendController — body {{n}} placeholders need
        // real values or Meta rejects with 131008.
        $variables = [];
        $samples   = json_decode($template['sample_values'] ?? '{}', true) ?? [];
        foreach ($samples['body'] ?? [] as $i => $value) {
            $variables['body_' . ($i + 1)] = $value;
        }
        if ($headerUrl) {
            $variables['header_url'] = $headerUrl;
        }

        $components = TemplateSendBuilder::buildComponents($template, $variables);

        // Immediate quick-sends previously never created a broadcast record,
        // so they were invisible in Reports → Sending History. Insert one
        // up front and update counts as sends complete, same as a normal
        // campaign, so quick sends show up in history too.
        $broadcastModel = new BroadcastModel();
        $broadcastId = $broadcastModel->insert([
            'account_id'        => session('account_id'),
            'name'              => 'Quick: ' . $template['name'] . ' (' . date('d M H:i') . ')',
            'template_name'     => $template['name'],
            'template_language' => $template['language'] ?? 'en',
            'header_media_url'  => $headerUrl ?: null,
            'audience_filter'   => json_encode(['type' => 'manual', 'numbers' => $numbers]),
            'status'            => 'sending',
            'total_recipients'  => count($numbers),
            'created_by'        => session('user_id'),
            'sent_at'           => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        $recipientModel = new \App\Models\BroadcastRecipientModel();
        $contactModel   = new ContactModel();
        $sent = 0; $failed = 0; $errors = [];

        foreach ($numbers as $num) {
            $normalized = PhoneUtils::normalize($num);
            $contact    = $normalized ? $contactModel->where('phone_normalized', $normalized)->first() : null;

            if (!$normalized) {
                $failed++;
                $errors[] = "$num: invalid";
                $recipientModel->insert([
                    'broadcast_id'  => $broadcastId,
                    'status'        => 'failed',
                    'error_message' => 'Invalid phone number',
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            try {
                $response = $metaApi->sendTemplate(
                    $waConfig['phone_number_id'],
                    $accessToken,
                    $normalized,
                    $template['name'],
                    $template['language'] ?? 'en',
                    $components
                );
                $sent++;

                // Without a recipient row keyed to the whatsapp_message_id,
                // Meta's later delivered/read status webhooks have nothing
                // to attribute back to this broadcast — counts would stay
                // stuck at 0 forever even after real delivery/read events.
                $recipientModel->insert([
                    'broadcast_id'         => $broadcastId,
                    'contact_id'           => $contact['id'] ?? null,
                    'status'               => 'sent',
                    'whatsapp_message_id'  => $response['messages'][0]['id'] ?? null,
                    'sent_at'              => date('Y-m-d H:i:s'),
                    'created_at'           => date('Y-m-d H:i:s'),
                ]);

                if ($contact) {
                    $this->logToInbox(session('account_id'), $contact['id'], $template['name'], $response['messages'][0]['id'] ?? null);
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "$num: " . $e->getMessage();
                $recipientModel->insert([
                    'broadcast_id'  => $broadcastId,
                    'contact_id'    => $contact['id'] ?? null,
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $broadcastModel->update($broadcastId, [
            'status'       => 'sent',
            'sent_count'   => $sent,
            'failed_count' => $failed,
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'success'      => true,
            'sent'         => $sent,
            'failed'       => $failed,
            'errors'       => $errors,
            'broadcast_id' => $broadcastId,
        ]);
    }

    /**
     * Quick Send previously only tracked delivery in broadcast_recipients —
     * never wrote to messages, so a sent quick-broadcast never appeared in
     * the customer's inbox conversation at all (same gap BroadcastProcessor
     * had for scheduled/batched sends).
     */
    private function logToInbox(string $accountId, string $contactId, string $templateName, ?string $waMessageId): void
    {
        $conversationModel = new ConversationModel();
        $conversation = $conversationModel->where('account_id', $accountId)->where('contact_id', $contactId)->first();

        if (!$conversation) {
            $conversationId = $conversationModel->insert([
                'account_id' => $accountId,
                'contact_id' => $contactId,
                'status'     => 'open',
            ]);
            $conversation = $conversationModel->find($conversationId);
        }

        (new MessageModel())->insert([
            'conversation_id'     => $conversation['id'],
            'account_id'          => $accountId,
            'sender_type'         => 'agent',
            'content_type'        => 'template',
            'content_text'        => $templateName,
            'template_name'       => $templateName,
            'status'              => 'sent',
            'whatsapp_message_id' => $waMessageId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $conversationModel->update($conversation['id'], [
            'last_message_text' => 'Template: ' . $templateName,
            'last_message_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}
