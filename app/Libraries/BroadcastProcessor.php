<?php

namespace App\Libraries;

use App\Models\BroadcastModel;
use App\Models\BroadcastRecipientModel;
use App\Models\ContactModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageTemplateModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\TemplateSendBuilder;

class BroadcastProcessor
{
    public function prepare(string $broadcastId): int
    {
        $broadcastModel = new BroadcastModel();
        $broadcast      = $broadcastModel->find($broadcastId);

        if (!$broadcast) {
            throw new \Exception('Broadcast not found');
        }

        $contacts = $this->getRecipients($broadcast);

        if (empty($contacts)) {
            throw new \Exception('No recipients found for this audience filter');
        }

        $recipientModel = new BroadcastRecipientModel();
        $variableMap    = json_decode($broadcast['variable_map'] ?? '{}', true) ?? [];

        $recipientIds = [];
        foreach ($contacts as $contact) {
            $variables = $this->resolveVariables($variableMap, $contact);

            $recipientId = $recipientModel->insert([
                'broadcast_id' => $broadcastId,
                'contact_id'   => $contact['id'],
                'variables'    => json_encode($variables),
                'status'       => 'pending',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            $recipientIds[] = $recipientId;
        }

        $broadcastModel->update($broadcastId, [
            'total_recipients' => count($contacts),
            'status'           => 'sending',
            'sent_at'          => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $batchSize  = max(1, min(50, (int) ($broadcast['batch_size'] ?? 50)));
        $dispatcher = new JobDispatcher();
        $batches    = array_chunk($recipientIds, $batchSize);

        foreach ($batches as $index => $batch) {
            $dispatcher->dispatch('send_broadcast_batch', [
                'broadcast_id'  => $broadcastId,
                'recipient_ids' => $batch,
                'batch_index'   => $index,
            ], null, 7);
        }

        return count($batches);
    }

    public function processBatch(string $broadcastId, array $recipientIds): array
    {
        $broadcastModel = new BroadcastModel();
        $broadcast      = $broadcastModel->find($broadcastId);

        if (!$broadcast) {
            throw new \Exception('Broadcast not found');
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $broadcast['account_id'])->first();
        if (!$waConfig) {
            throw new \Exception('WhatsApp not connected for this account');
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        $template = (new MessageTemplateModel())
            ->where('account_id', $broadcast['account_id'])
            ->where('name', $broadcast['template_name'])
            ->where('status', 'approved')
            ->first();

        if (!$template) {
            throw new \Exception('Approved template "' . $broadcast['template_name'] . '" not found');
        }

        $recipientModel = new BroadcastRecipientModel();
        $contactModel   = new ContactModel();
        $metaApi        = new MetaApi();

        $results     = ['sent' => 0, 'failed' => 0, 'errors' => []];
        $startTime   = microtime(true);
        $sent        = 0;
        $maxPerSec   = 70;

        foreach ($recipientIds as $recipientId) {
            $recipient = $recipientModel->find($recipientId);
            if (!$recipient || $recipient['status'] !== 'pending') continue;

            $contact = $contactModel->find($recipient['contact_id']);
            if (!$contact || empty($contact['phone_normalized'])) {
                $recipientModel->update($recipientId, ['status' => 'failed', 'error_message' => 'No phone number']);
                $results['failed']++;
                continue;
            }

            // Rate limiting: 70 msg/sec
            if ($sent > 0 && $sent % $maxPerSec === 0) {
                $elapsed = microtime(true) - $startTime;
                if ($elapsed < 1.0) {
                    usleep((int) ((1.0 - $elapsed) * 1_000_000));
                }
                $startTime = microtime(true);
            }

            try {
                $variables = json_decode($recipient['variables'] ?? '{}', true) ?? [];
                if (empty($variables)) {
                    // No per-recipient personalization set — fall back to
                    // the template's own sample values so body {{n}}
                    // placeholders aren't sent empty (Meta 131008).
                    $samples = json_decode($template['sample_values'] ?? '{}', true) ?? [];
                    foreach ($samples['body'] ?? [] as $i => $value) {
                        $variables['body_' . ($i + 1)] = $value;
                    }
                }
                if (!empty($broadcast['header_media_url'])) {
                    $variables['header_url'] = $broadcast['header_media_url'];
                }
                $components = TemplateSendBuilder::buildComponents($template, $variables);

                $response = $metaApi->sendTemplate(
                    $waConfig['phone_number_id'],
                    $accessToken,
                    $contact['phone_normalized'],
                    $template['name'],
                    $template['language'],
                    $components
                );

                $waMessageId = $response['messages'][0]['id'] ?? null;

                $recipientModel->update($recipientId, [
                    'status'               => 'sent',
                    'whatsapp_message_id'  => $waMessageId,
                    'sent_at'              => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                ]);

                $this->logToInbox($broadcast['account_id'], $contact['id'], $template['name'], $waMessageId);

                $results['sent']++;
                $sent++;

            } catch (\Exception $e) {
                $recipientModel->update($recipientId, [
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                $results['failed']++;
                $results['errors'][] = ['contact' => $contact['phone'], 'error' => $e->getMessage()];
                log_message('error', 'Broadcast batch send failed: ' . $e->getMessage());
            }
        }

        // Check if entire broadcast is complete
        $total   = (int) $broadcast['total_recipients'];
        $nowSent = (new \App\Models\BroadcastRecipientModel())
            ->where('broadcast_id', $broadcastId)
            ->whereIn('status', ['sent', 'delivered', 'read', 'replied', 'failed'])
            ->countAllResults();

        if ($nowSent >= $total && $total > 0) {
            $broadcastModel->update($broadcastId, [
                'status'     => 'sent',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Increment counters atomically
        $db = \Config\Database::connect();
        $db->table('broadcasts')
            ->where('id', $broadcastId)
            ->set([
                'sent_count' => 'sent_count + ' . (int)$results['sent'],
                'failed_count' => 'failed_count + ' . (int)$results['failed'],
                'updated_at' => 'NOW()'
            ], false) // false = don't escape SQL functions
            ->update();

        return $results;
    }

    /**
     * Broadcast sends previously only tracked delivery in broadcast_recipients —
     * never wrote to messages, so a sent broadcast never appeared in the
     * customer's inbox conversation at all.
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

    private function getRecipients(array $broadcast): array
    {
        $contactModel   = new ContactModel();
        $audienceFilter = json_decode($broadcast['audience_filter'] ?? '{}', true);
        $type           = $audienceFilter['type'] ?? 'all';
        $db             = \Config\Database::connect();

        // Find the ID of the 'Unsubscribed' tag for this account (if it exists)
        $unsubscribedTag = $db->table('tags')
            ->where('account_id', $broadcast['account_id'])
            ->where('name', 'Unsubscribed')
            ->get()->getRowArray();
        $unsubscribedTagId = $unsubscribedTag['id'] ?? null;

        if ($type === 'all') {
            $builder = $contactModel->where('account_id', $broadcast['account_id']);
            $this->applyUnsubscribeFilter($builder, $unsubscribedTagId, 'id');
            return $builder->findAll();
        }

        if ($type === 'tags' && !empty($audienceFilter['tag_ids'])) {
            $query = $db->table('contacts c')
                ->select('c.*')
                ->join('contact_tags ct', 'ct.contact_id = c.id')
                ->whereIn('ct.tag_id', $audienceFilter['tag_ids'])
                ->where('c.account_id', $broadcast['account_id']);

            $this->applyUnsubscribeFilter($query, $unsubscribedTagId, 'c.id');

            return $query->groupBy('c.id')
                ->get()->getResultArray();
        }

        return [];
    }

    /**
     * Exclude contacts who have the 'Unsubscribed' tag.
     */
    private function applyUnsubscribeFilter($builder, ?string $unsubscribedTagId, string $idColumn): void
    {
        if (!$unsubscribedTagId) {
            return;
        }

        $builder->whereNotIn($idColumn, function ($subQuery) use ($unsubscribedTagId) {
            return $subQuery->select('contact_id')->from('contact_tags')->where('tag_id', $unsubscribedTagId);
        });
    }

    private function resolveVariables(array $variableMap, array $contact): array
    {
        $resolved = [];
        foreach ($variableMap as $key => $source) {
            $resolved['body_' . $key] = match ($source) {
                'name'         => $contact['name'] ?? '',
                'phone'        => $contact['phone'] ?? '',
                'company'      => $contact['company'] ?? '',
                'email'        => $contact['email'] ?? '',
                default        => $source,
            };
        }
        return $resolved;
    }
}
