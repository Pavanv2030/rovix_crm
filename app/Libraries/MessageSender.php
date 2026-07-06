<?php

namespace App\Libraries;

use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\MessageTemplateModel;
use App\Models\WhatsAppConfigModel;

class MessageSender
{
    public function sendText(array $payload): void
    {
        $conversationId = $payload['conversation_id'];
        $accountId      = $payload['account_id'];
        $content        = $payload['content'] ?? '';

        [$waConfig, $accessToken, $contact] = $this->resolve($conversationId, $accountId);

        $messageModel = new MessageModel();
        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => $accountId,
            'sender_type'     => 'bot',
            'content_type'    => 'text',
            'content_text'    => $content,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $response = (new MetaApi())->sendText(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            $content
        );

        $messageModel->update($messageId, [
            'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
            'status'              => 'sent',
        ]);

        (new ConversationModel())->where('id', $conversationId)->set([
            'last_message_text' => substr($content, 0, 200),
            'last_message_at'   => date('Y-m-d H:i:s'),
        ])->update();
    }

    public function sendTemplate(array $payload): void
    {
        $conversationId   = $payload['conversation_id'];
        $accountId        = $payload['account_id'];
        $templateName     = $payload['template_name'] ?? '';
        $templateLanguage = $payload['template_language'] ?? 'en';
        $variables        = $payload['variables'] ?? [];

        [$waConfig, $accessToken, $contact] = $this->resolve($conversationId, $accountId);

        $template = (new MessageTemplateModel())
            ->where('account_id', $accountId)
            ->where('name', $templateName)
            ->first();

        $components = [];
        if ($template) {
            // TemplateSendBuilder expects 'body_N' keys, not plain integers —
            // array_combine(range(1,...)) here never matched, so body
            // placeholders always sent empty regardless of $variables.
            $mapped = [];
            foreach (array_values($variables) as $i => $value) {
                $mapped['body_' . ($i + 1)] = $value;
            }
            if (empty($mapped)) {
                $samples = json_decode($template['sample_values'] ?? '{}', true) ?? [];
                foreach ($samples['body'] ?? [] as $i => $value) {
                    $mapped['body_' . ($i + 1)] = $value;
                }
            }
            $components = \App\Libraries\WhatsApp\TemplateSendBuilder::buildComponents($template, $mapped);
        }

        $messageModel = new MessageModel();
        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => $accountId,
            'sender_type'     => 'bot',
            'content_type'    => 'template',
            'content_text'    => $templateName,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $response = (new MetaApi())->sendTemplate(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            $templateName,
            $templateLanguage,
            $components
        );

        $messageModel->update($messageId, [
            'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
            'status'              => 'sent',
        ]);
    }

    private function resolve(string $conversationId, string $accountId): array
    {
        $conversation = (new ConversationModel())
            ->where('id', $conversationId)
            ->where('account_id', $accountId)
            ->first();

        if (!$conversation) {
            throw new \Exception("Conversation {$conversationId} not found");
        }

        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', $accountId)
            ->first();

        if (!$waConfig) {
            throw new \Exception("No WhatsApp config for account {$accountId}");
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        $contact = (new ContactModel())
            ->where('id', $conversation['contact_id'])
            ->first();

        if (!$contact) {
            throw new \Exception("Contact not found for conversation {$conversationId}");
        }

        return [$waConfig, $accessToken, $contact];
    }
}
