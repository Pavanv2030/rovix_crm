<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\MessageReactionModel;

class InboxController extends BaseController
{
    // Polled every few seconds by the inbox sidebar so the conversation list
    // updates itself (new last message, unread count, reordering) without
    // the user needing to reload the page.
    public function conversations()
    {
        $conversationModel = new ConversationModel();

        $conversations = $conversationModel->db->table('conversations c')
            ->select('c.*, ct.name as contact_name, ct.phone, ct.phone_normalized, ct.avatar_url as contact_avatar, p.full_name as assigned_agent_name')
            ->join('contacts ct', 'ct.id = c.contact_id', 'left')
            ->join('profiles p', 'p.user_id = c.assigned_agent_id', 'left')
            ->where('c.account_id', session('account_id'))
            ->orderBy('c.last_message_at', 'DESC')
            ->limit(50)->get()->getResultArray();

        return $this->response->setJSON($conversations);
    }

    // Polled every few seconds by an open conversation thread so new
    // incoming/outgoing messages appear without a manual refresh.
    public function messages(string $conversationId)
    {
        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->where('account_id', session('account_id'))->find($conversationId);

        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        }

        $afterAt = $this->request->getGet('after_at') ?: '1970-01-01 00:00:00';
        $afterId = $this->request->getGet('after_id') ?: '';

        $messageModel = new MessageModel();
        $messages = $messageModel->where('conversation_id', $conversationId)
            ->groupStart()
                ->where('created_at >', $afterAt)
                ->orGroupStart()
                    ->where('created_at', $afterAt)
                    ->where('id !=', $afterId)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('created_at', 'ASC')
            ->findAll();

        if (!$messages) {
            return $this->response->setJSON(['html' => '']);
        }

        $messageIds         = array_column($messages, 'id');
        $reactionsByMessage = [];
        foreach ((new MessageReactionModel())->whereIn('message_id', $messageIds)->findAll() as $r) {
            $reactionsByMessage[$r['message_id']][] = $r;
        }

        $replyWamids   = array_values(array_unique(array_filter(array_column($messages, 'reply_to_message_id'))));
        $quotedByWamid = [];
        if ($replyWamids) {
            foreach ($messageModel->whereIn('whatsapp_message_id', $replyWamids)->findAll() as $q) {
                $quotedByWamid[$q['whatsapp_message_id']] = $q;
            }
        }

        $contact            = (new ContactModel())->find($conversation['contact_id']);
        $hasCustomerMessage = false;
        $html               = '';

        foreach ($messages as $m) {
            $m['reactions'] = $reactionsByMessage[$m['id']] ?? [];
            $m['quoted']    = $m['reply_to_message_id'] ? ($quotedByWamid[$m['reply_to_message_id']] ?? null) : null;
            if ($m['sender_type'] === 'customer') {
                $hasCustomerMessage = true;
            }
            $html .= view('inbox/partials/message_bubble', ['msg' => $m, 'contact' => $contact]);
        }

        // User is actively viewing this thread while it's being polled, so
        // any new customer message arriving here should count as read.
        if ($hasCustomerMessage && $conversation['unread_count'] > 0) {
            $conversationModel->update($conversationId, ['unread_count' => 0]);
        }

        $last = end($messages);

        return $this->response->setJSON([
            'html'    => $html,
            'last_id' => $last['id'],
            'last_at' => $last['created_at'],
        ]);
    }
}
