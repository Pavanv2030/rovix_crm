<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Models\MessageModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageReactionModel;

class ReactController extends BaseController
{
    public function react()
    {
        $messageId = $this->request->getPost('message_id');
        $emoji     = $this->request->getPost('emoji');

        if (empty($messageId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'message_id is required']);
        }

        $message = (new MessageModel())->find($messageId);
        if (!$message || empty($message['whatsapp_message_id'])) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Message not found']);
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if (!$waConfig) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'WhatsApp not connected']);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            (new MetaApi())->sendReaction(
                $waConfig['phone_number_id'],
                $accessToken,
                $message['whatsapp_message_id'],
                $emoji ?? ''
            );

            // Was never persisted here — the customer's own reactions save
            // to message_reactions via the webhook, but an agent's reaction
            // sent from this endpoint just went out over WhatsApp and
            // vanished, never visible again in this app's own inbox.
            $reactionModel = new MessageReactionModel();
            $reactionModel->where('message_id', $messageId)->where('actor_type', 'agent')->delete();
            if (!empty($emoji)) {
                $reactionModel->insert([
                    'message_id'      => $messageId,
                    'conversation_id' => $message['conversation_id'],
                    'actor_type'      => 'agent',
                    'emoji'           => $emoji,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }

            return $this->response->setJSON(['success' => true]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to send reaction: ' . $e->getMessage()]);
        }
    }
}
