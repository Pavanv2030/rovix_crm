<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\MessageTemplateModel;
use App\Libraries\WhatsApp\TemplateSendBuilder;
use App\Models\WhatsAppConfigModel;

class SendController extends BaseController
{
    public function send()
    {
        $conversationId    = $this->request->getPost('conversation_id');
        $contentType       = $this->request->getPost('content_type') ?? 'text';
        $contentText       = $this->request->getPost('content_text');
        $replyToMessageId  = $this->request->getPost('reply_to_message_id');

        if (empty($conversationId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'conversation_id is required']);
        }

        if (empty($contentText) && $contentType === 'text') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'content_text is required for text messages']);
        }

        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if (!$waConfig || $waConfig['status'] !== 'connected') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'WhatsApp not connected']);
        }

        $contact = (new ContactModel())->find($conversation['contact_id']);
        if (!$contact) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Contact not found']);
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        $messageModel = new MessageModel();
        $messageId    = $messageModel->insert([
            'conversation_id'    => $conversationId,
            'account_id'         => session('account_id'),
            'sender_type'        => 'agent',
            'content_type'       => $contentType,
            'content_text'       => $contentText,
            'status'             => 'sending',
            'reply_to_message_id'=> $replyToMessageId,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        try {
            $metaApi  = new MetaApi();
            $response = $metaApi->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $contentText,
                $replyToMessageId
            );

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'],
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => substr($contentText, 0, 200),
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON([
                'success'              => true,
                'message_id'           => $messageId,
                'whatsapp_message_id'  => $response['messages'][0]['id'],
            ]);

        } catch (\Exception $e) {
            $messageModel->update($messageId, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to send message: ' . $e->getMessage()]);
        }
    }

    public function sendTemplate()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $templateId     = $this->request->getPost('template_id');

        if (!$conversationId || !$templateId) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'conversation_id and template_id required']);
        }

        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        }

        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Template not found']);
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if (!$waConfig || $waConfig['status'] !== 'connected') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'WhatsApp not connected']);
        }

        $contact = (new ContactModel())->find($conversation['contact_id']);
        if (!$contact) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Contact not found']);
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        $messageModel = new MessageModel();
        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'agent',
            'content_type'    => 'template',
            'content_text'    => $template['name'],
            'template_name'   => $template['name'],
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            // Body {{n}} placeholders need real values or Meta rejects the
            // send with 131008 "Required parameter is missing" — fall back
            // to the sample values captured when the template was created.
            $variables = [];
            $samples   = json_decode($template['sample_values'] ?? '{}', true) ?? [];
            foreach ($samples['body'] ?? [] as $i => $value) {
                $variables['body_' . ($i + 1)] = $value;
            }

            $components = TemplateSendBuilder::buildComponents($template, $variables);
            $response = (new MetaApi())->sendTemplate(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $template['name'],
                $template['language'] ?? 'en',
                $components
            );

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => 'Template: ' . $template['name'],
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);

        } catch (\Exception $e) {
            $messageModel->update($messageId, ['status' => 'failed', 'error_message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }
}
