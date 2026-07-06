<?php

namespace App\Libraries;

use App\Models\ConversationModel;
use App\Models\ConversationStatusModel;
use App\Models\ContactModel;
use App\Models\MessageModel;
use App\Models\MessageTemplateModel;
use App\Models\WhatsAppConfigModel;
use App\Models\ActivityLogModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\TemplateSendBuilder;

/**
 * Sets a conversation's lead status and fires its configured auto-reply
 * (static/AI/template). Shared by Api\ConversationController::updateLeadStatus()
 * (UI dropdown, has a session) and Api\WebhookController's "Interested"
 * button-click handler (background webhook context, no session) — both need
 * identical status-change + auto-reply behavior, so account_id is passed in
 * explicitly rather than read from session().
 */
class LeadStatusApplier
{
    public static function apply(string $conversationId, ?string $statusId, string $accountId): array
    {
        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->find($conversationId);

        if (!$conversation) {
            return ['error' => 'Conversation not found', 'code' => 404];
        }

        // No-op if unchanged — avoids repeat "status changed" system
        // messages and repeat auto-replies on retries/duplicate triggers.
        if (($statusId ?: null) === ($conversation['lead_status_id'] ?? null)) {
            return ['success' => true, 'auto_reply_sent' => false, 'unchanged' => true];
        }

        $status = null;
        if ($statusId) {
            $status = (new ConversationStatusModel())->where('account_id', $accountId)->find($statusId);
            if (!$status) {
                return ['error' => 'Invalid status', 'code' => 400];
            }
        }

        $conversationModel->update($conversationId, ['lead_status_id' => $statusId ?: null]);

        (new MessageModel())->insert([
            'conversation_id' => $conversationId,
            'account_id'      => $accountId,
            'sender_type'     => 'system',
            'content_type'    => 'text',
            'content_text'    => $status ? "Lead status changed to {$status['name']}" : 'Lead status cleared',
            'status'          => 'sent',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $autoReplySent  = false;
        $replyMode      = $status['reply_mode'] ?? 'static';
        $hasReplySource = $status && (
            ($replyMode === 'template' && !empty($status['template_id'])) ||
            ($replyMode === 'ai'       && !empty($status['ai_instruction'])) ||
            ($replyMode === 'static'   && !empty($status['auto_reply_message']))
        );

        if ($hasReplySource) {
            $contact  = (new ContactModel())->find($conversation['contact_id']);
            $waConfig = (new WhatsAppConfigModel())->where('account_id', $accountId)->first();

            if ($contact && $waConfig && ($waConfig['status'] ?? '') === 'connected') {
                try {
                    $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

                    if ($replyMode === 'template') {
                        $autoReplySent = self::sendTemplate($conversationId, $contact, $waConfig, $accessToken, $status['template_id'], $accountId, $status['template_header_url'] ?? null);
                    } else {
                        $text = self::resolveReplyText($status, $conversationId, $contact, $accountId);

                        if ($text === null) {
                            throw new \Exception('No reply text resolved');
                        }

                        $sendResult = (new MetaApi())->sendText(
                            $waConfig['phone_number_id'],
                            $accessToken,
                            $contact['phone_normalized'],
                            $text
                        );

                        (new MessageModel())->insert([
                            'conversation_id'     => $conversationId,
                            'account_id'          => $accountId,
                            'sender_type'         => 'agent',
                            'content_type'        => 'text',
                            'content_text'        => $text,
                            'status'              => 'sent',
                            'whatsapp_message_id' => $sendResult['messages'][0]['id'] ?? null,
                            'created_at'          => date('Y-m-d H:i:s'),
                        ]);

                        $conversationModel->update($conversationId, [
                            'last_message_text' => $text,
                            'last_message_at'   => date('Y-m-d H:i:s'),
                        ]);

                        $autoReplySent = true;
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Lead status auto-reply failed: ' . $e->getMessage());
                }
            }
        }

        ActivityLogModel::record('conversation.lead_status_changed', 'conversation', $conversationId, ['status' => $status['name'] ?? null]);

        return ['success' => true, 'auto_reply_sent' => $autoReplySent];
    }

    /**
     * Template mode — unlike static/AI text, this can reach the customer
     * even outside WhatsApp's 24h session window since it's a Meta-approved
     * template, same mechanism as SendController::sendTemplate().
     */
    private static function sendTemplate(string $conversationId, array $contact, array $waConfig, string $accessToken, string $templateId, string $accountId, ?string $headerUrl = null): bool
    {
        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) {
            log_message('warning', "[LeadStatusApplier] template {$templateId} not found");
            return false;
        }

        $variables = [];
        $samples   = json_decode($template['sample_values'] ?? '{}', true) ?? [];
        foreach ($samples['body'] ?? [] as $i => $value) {
            $variables['body_' . ($i + 1)] = $value;
        }
        // Sample value is only for Meta's approval review — substitute the
        // real contact name so every customer doesn't get the literal sample.
        if (!empty($contact['name'])) {
            $variables['body_1'] = $contact['name'];
        }
        if ($headerUrl) {
            $variables['header_url'] = $headerUrl;
        }

        $components = TemplateSendBuilder::buildComponents($template, $variables);

        $sendResult = (new MetaApi())->sendTemplate(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            $template['name'],
            $template['language'] ?? 'en',
            $components
        );

        (new MessageModel())->insert([
            'conversation_id'     => $conversationId,
            'account_id'          => $accountId,
            'sender_type'         => 'agent',
            'content_type'        => 'template',
            'content_text'        => $template['name'],
            'template_name'       => $template['name'],
            'status'              => 'sent',
            'whatsapp_message_id' => $sendResult['messages'][0]['id'] ?? null,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        (new ConversationModel())->update($conversationId, [
            'last_message_text' => 'Template: ' . $template['name'],
            'last_message_at'   => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Static message: plain {{name}} substitution, same every time.
     * AI mode: OpenAI writes a fresh message per customer using the
     * account's Settings → AI key, the status's instruction, and this
     * conversation's recent messages as context. Falls back to the static
     * message (if also set) when the AI call fails.
     */
    private static function resolveReplyText(array $status, string $conversationId, array $contact, string $accountId): ?string
    {
        if (!empty($status['use_ai']) && !empty($status['ai_instruction'])) {
            $recentMessages = (new MessageModel())
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'DESC')
                ->findAll(6);
            $recentMessages = array_reverse($recentMessages);

            $transcript = implode("\n", array_map(
                fn($m) => ($m['sender_type'] === 'customer' ? 'Customer' : 'Business') . ': ' . mb_strimwidth((string) ($m['content_text'] ?? '['. $m['content_type'] .']'), 0, 200, '...'),
                $recentMessages
            ));

            $messages = [
                [
                    'role'    => 'system',
                    'content' => 'You are writing a single WhatsApp message on behalf of a business to a customer whose lead status just changed. Follow the instruction, use the conversation for context and personalization, and keep it short and natural — like a real agent typed it. Reply with ONLY the message text, no explanation, no quotes.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Instruction: {$status['ai_instruction']}\n\nCustomer name: " . ($contact['name'] ?? 'the customer') . "\n\nRecent conversation:\n{$transcript}",
                ],
            ];

            $result = OpenAiClient::chat($accountId, $messages, null, 300, 'lead_status_reply');
            if (!isset($result['error'])) {
                return $result['text'];
            }
            log_message('warning', "[LeadStatusApplier] AI reply failed: {$result['error']}");
        }

        if (!empty($status['auto_reply_message'])) {
            return str_replace('{{name}}', $contact['name'] ?? '', $status['auto_reply_message']);
        }

        return null;
    }
}
