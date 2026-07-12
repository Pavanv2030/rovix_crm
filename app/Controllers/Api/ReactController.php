<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageReactionModel;
use App\Models\WhatsAppConfigModel;

class ReactController extends BaseController
{
    public function react()
    {
        $messageId      = trim((string) $this->request->getPost('message_id'));
        $conversationId = trim((string) $this->request->getPost('conversation_id'));
        $emoji          = trim((string) $this->request->getPost('emoji'));

        if ($messageId === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'message_id is required']);
        }

        if ($emoji === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'emoji is required']);
        }

        $accountId = session('account_id');
        if (!$accountId) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Not authenticated']);
        }

        $db = db_connect();
        $builder = $db->table('messages m')
            ->select('m.*')
            ->join('conversations c', 'c.id = m.conversation_id')
            ->where('m.id', $messageId)
            ->where('c.account_id', $accountId);

        if ($conversationId !== '') {
            $builder->where('m.conversation_id', $conversationId);
        }

        $message = $builder->get()->getRowArray();

        if (!$message) {
            log_message('warning', "React: message not found id={$messageId} conv={$conversationId} account={$accountId}");
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Message not found in this conversation']);
        }

        if ($message['sender_type'] !== 'customer') {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Reactions only work on incoming customer messages',
            ]);
        }

        $conversation = (new ConversationModel())->find($message['conversation_id']);
        $contact      = $conversation ? (new ContactModel())->find($conversation['contact_id']) : null;

        if (!$contact || empty($contact['phone_normalized'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Contact phone not found']);
        }

        $reactionModel = new MessageReactionModel();
        $reactionModel->where('message_id', $messageId)->where('actor_type', 'agent')->delete();
        $reactionModel->insert([
            'message_id'      => $messageId,
            'conversation_id' => $message['conversation_id'],
            'actor_type'      => 'agent',
            'emoji'           => $emoji,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $whatsappSynced = false;
        $syncWarning    = null;

        if (!empty($message['whatsapp_message_id'])) {
            $waConfig = (new WhatsAppConfigModel())->where('account_id', $accountId)->first();
            if ($waConfig && $waConfig['status'] === 'connected') {
                try {
                    $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
                    (new MetaApi())->sendReaction(
                        $waConfig['phone_number_id'],
                        $accessToken,
                        $contact['phone_normalized'],
                        $message['whatsapp_message_id'],
                        $emoji
                    );
                    $whatsappSynced = true;
                } catch (\Exception $e) {
                    log_message('warning', 'React WhatsApp sync failed: ' . $e->getMessage());
                    $syncWarning = 'Saved in inbox; WhatsApp sync failed.';
                }
            } else {
                $syncWarning = 'Saved in inbox; WhatsApp is not connected.';
            }
        } else {
            $syncWarning = 'Saved in inbox only (message not linked to WhatsApp).';
        }

        return $this->response->setJSON([
            'success'         => true,
            'message_id'      => $messageId,
            'emoji'           => $emoji,
            'whatsapp_synced' => $whatsappSynced,
            'warning'         => $syncWarning,
        ]);
    }
}