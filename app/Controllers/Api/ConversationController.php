<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ConversationModel;
use App\Models\ContactTagModel;
use App\Models\ContactNoteModel;
use App\Models\MessageModel;
use App\Models\ProfileModel;
use App\Models\TagModel;
use App\Models\ContactModel;
use App\Libraries\LeadStatusApplier;

class ConversationController extends BaseController
{
    public function assign()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $agentId        = $this->request->getPost('agent_id');

        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->find($conversationId);

        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        }

        $agentName = 'Unassigned';
        if ($agentId) {
            ProfileModel::setBypassAccountScope(true);
            $agent = (new ProfileModel())->where('user_id', $agentId)->where('account_id', session('account_id'))->first();
            ProfileModel::setBypassAccountScope(false);

            if (!$agent) {
                return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid agent']);
            }
            $agentName = $agent['full_name'];
        }

        $conversationModel->update($conversationId, ['assigned_agent_id' => $agentId ?: null]);

        (new MessageModel())->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'system',
            'content_type'    => 'text',
            'content_text'    => $agentId ? "Conversation assigned to {$agentName}" : 'Conversation unassigned',
            'status'          => 'sent',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'agent_name' => $agentName]);
    }

    public function updateStatus()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $status         = $this->request->getPost('status');

        if (!in_array($status, ['open', 'pending', 'closed'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid status']);
        }

        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->find($conversationId);

        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        }

        $updateData = ['status' => $status];
        if ($status === 'closed') {
            $updateData['unread_count'] = 0;
        }

        $conversationModel->update($conversationId, $updateData);

        (new MessageModel())->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'system',
            'content_type'    => 'text',
            'content_text'    => "Conversation marked as {$status}",
            'status'          => 'sent',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    // Changing a conversation's lead status (a user-defined list, separate
    // from the open/pending/closed inbox status above) can automatically
    // WhatsApp the customer a message configured per-status in
    // Settings → Lead Statuses — e.g. moving to "Qualified" auto-sends a
    // "thanks, we'll be in touch" reply without the agent typing anything.
    public function updateLeadStatus()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $statusId       = $this->request->getPost('lead_status_id');

        $result = LeadStatusApplier::apply($conversationId, $statusId, session('account_id'));

        if (isset($result['error'])) {
            return $this->response->setStatusCode($result['code'] ?? 400)->setJSON(['error' => $result['error']]);
        }

        return $this->response->setJSON($result);
    }

    public function addTag()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $tagId          = $this->request->getPost('tag_id');

        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        }

        // Without this, a tag_id from another account could get attached
        // to this contact and then display (its name/color leak) wherever
        // this contact's tags are shown.
        if (!(new TagModel())->find($tagId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid tag']);
        }

        $contactTagModel = new ContactTagModel();
        $existing        = $contactTagModel->where('contact_id', $conversation['contact_id'])->where('tag_id', $tagId)->first();

        if (!$existing) {
            $contactTagModel->insert(['contact_id' => $conversation['contact_id'], 'tag_id' => $tagId]);
        }

        return $this->response->setJSON(['success' => true]);
    }

    public function addNote()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $noteText       = trim($this->request->getPost('note_text') ?? '');

        if (empty($noteText)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Note text is required']);
        }

        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        }

        (new ContactNoteModel())->insert([
            'contact_id' => $conversation['contact_id'],
            'user_id'    => session('user_id'),
            'note_text'  => $noteText,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function addContactNote()
    {
        $contactId = $this->request->getPost('contact_id');
        $noteText  = trim($this->request->getPost('note_text') ?? '');

        if (empty($noteText)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Note text is required']);
        }

        // This endpoint never checked contact_id belonged to the caller's
        // account at all — any contact_id (including another tenant's)
        // would silently accept a note write.
        if (!(new ContactModel())->find($contactId)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Contact not found']);
        }

        (new ContactNoteModel())->insert([
            'contact_id' => $contactId,
            'user_id'    => session('user_id'),
            'note_text'  => $noteText,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true]);
    }
}
