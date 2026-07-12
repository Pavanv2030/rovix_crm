<?php

namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\ContactModel;
use App\Models\MessageModel;
use App\Models\ProfileModel;
use App\Models\ContactTagModel;
use App\Models\TagModel;
use App\Models\MessageTemplateModel;
use App\Models\ConversationStatusModel;

class InboxController extends BaseController
{
    public function index()
    {
        $conversationModel = new ConversationModel();
        $selectedStatus    = $this->request->getGet('status') ?? 'all';

        $builder = $conversationModel->db->table('conversations c')
            ->select('c.*, ct.name as contact_name, ct.phone, ct.phone_normalized, ct.avatar_url as contact_avatar, p.full_name as assigned_agent_name, cs.name as lead_status_name, cs.color as lead_status_color')
            ->join('contacts ct', 'ct.id = c.contact_id', 'left')
            ->join('profiles p', 'p.user_id = c.assigned_agent_id', 'left')
            ->join('conversation_statuses cs', 'cs.id = c.lead_status_id', 'left')
            ->where('c.account_id', session('account_id'))
            ->orderBy('c.last_message_at', 'DESC');

        if ($selectedStatus !== 'all') {
            $builder->where('c.status', $selectedStatus);
        }

        $conversations = $builder->limit(50)->get()->getResultArray();

        // Status counts
        $statusCounts = [];
        foreach (['open', 'pending', 'closed'] as $s) {
            $statusCounts[$s] = $conversationModel->db->table('conversations')
                ->where('account_id', session('account_id'))
                ->where('status', $s)
                ->countAllResults();
        }

        return view('inbox/index', [
            'pageTitle'      => 'Inbox',
            'conversations'  => $conversations,
            'statusCounts'   => $statusCounts,
            'selectedStatus' => $selectedStatus,
        ]);
    }

    // Runs once per account — so the Lead Status dropdown in the inbox has
    // something in it immediately instead of requiring a trip to
    // Settings → Lead Statuses first. Reply messages are left blank on
    // purpose: we can't safely invent what a business wants to tell its
    // customers, so nothing auto-sends until the message is filled in there.
    private function seedDefaultLeadStatusesIfNone(): void
    {
        $model = new ConversationStatusModel();
        if ($model->where('account_id', session('account_id'))->countAllResults() > 0) {
            return;
        }

        $defaults = [
            ['name' => 'New Lead',      'color' => '#3B82F6'],
            ['name' => 'Contacted',     'color' => '#F59E0B'],
            ['name' => 'Hot Lead',      'color' => '#F97316'],
            ['name' => 'Qualified',     'color' => '#8B5CF6'],
            ['name' => 'Converted',     'color' => '#10B981'],
            ['name' => 'Not Interested','color' => '#EF4444'],
        ];

        foreach ($defaults as $i => $d) {
            $model->insert([
                'account_id' => session('account_id'),
                'name'       => $d['name'],
                'color'      => $d['color'],
                'sort_order' => $i,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function conversation(string $conversationId)
    {
        $this->seedDefaultLeadStatusesIfNone();

        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->where('account_id', session('account_id'))->find($conversationId);

        if (!$conversation) {
            return redirect()->to(base_url('inbox'))->with('error', 'Conversation not found.');
        }

        // Load messages
        $messageModel = new MessageModel();
        $messages     = $messageModel->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'ASC')
            ->findAll();

        // Batch-fetch reactions + quoted-reply previews for this thread —
        // one query each instead of N+1 per message.
        $messageIds = array_column($messages, 'id');
        $reactionsByMessage = [];
        if ($messageIds) {
            foreach ((new \App\Models\MessageReactionModel())->whereIn('message_id', $messageIds)->findAll() as $r) {
                $reactionsByMessage[$r['message_id']][] = $r;
            }
        }

        $replyWamids = array_values(array_unique(array_filter(array_column($messages, 'reply_to_message_id'))));
        $quotedByWamid = [];
        if ($replyWamids) {
            foreach ($messageModel->whereIn('whatsapp_message_id', $replyWamids)->findAll() as $q) {
                $quotedByWamid[$q['whatsapp_message_id']] = $q;
            }
        }

        foreach ($messages as &$m) {
            $m['reactions'] = $reactionsByMessage[$m['id']] ?? [];
            $m['quoted']    = $m['reply_to_message_id'] ? ($quotedByWamid[$m['reply_to_message_id']] ?? null) : null;
        }
        unset($m);

        // Mark as read
        if ($conversation['unread_count'] > 0) {
            $conversationModel->update($conversationId, ['unread_count' => 0]);
        }

        // Load contact
        $contactModel = new ContactModel();
        $contact      = $contactModel->find($conversation['contact_id']);

        // Load tags for contact
        $tags = [];
        if ($contact) {
            $tags = $conversationModel->db->table('contact_tags ct')
                ->select('t.id, t.name, t.color')
                ->join('tags t', 't.id = ct.tag_id')
                ->where('ct.contact_id', $conversation['contact_id'])
                ->get()->getResultArray();
        }

        // Load all account tags for tag selector
        $allTags = (new TagModel())->findAll();

        // Load agents for assignment
        ProfileModel::setBypassAccountScope(true);
        $agents = (new ProfileModel())->where('account_id', session('account_id'))
            ->whereIn('account_role', ['owner', 'admin', 'agent'])
            ->findAll();
        ProfileModel::setBypassAccountScope(false);

        // Load all conversations for sidebar
        $allConversations = $conversationModel->db->table('conversations c')
            ->select('c.*, ct.name as contact_name, ct.phone, ct.avatar_url as contact_avatar')
            ->join('contacts ct', 'ct.id = c.contact_id', 'left')
            ->where('c.account_id', session('account_id'))
            ->orderBy('c.last_message_at', 'DESC')
            ->limit(50)->get()->getResultArray();

        $statusCounts = [];
        foreach (['open', 'pending', 'closed'] as $s) {
            $statusCounts[$s] = $conversationModel->db->table('conversations')
                ->where('account_id', session('account_id'))
                ->where('status', $s)
                ->countAllResults();
        }

        $templates = (new MessageTemplateModel())->where('status', 'approved')->findAll();

        $leadStatuses = (new ConversationStatusModel())
            ->where('account_id', session('account_id'))
            ->orderBy('sort_order', 'ASC')->orderBy('created_at', 'ASC')
            ->findAll();

        return view('inbox/index', [
            'pageTitle'        => 'Inbox',
            'conversations'    => $allConversations,
            'statusCounts'     => $statusCounts,
            'selectedStatus'   => 'all',
            'activeConversation' => $conversation,
            'messages'         => $messages,
            'contact'          => $contact,
            'tags'             => $tags,
            'allTags'          => $allTags,
            'agents'           => $agents,
            'templates'        => $templates,
            'leadStatuses'     => $leadStatuses,
        ]);
    }

    public function search()
    {
        $q      = $this->request->getGet('q') ?? '';
        $status = $this->request->getGet('status') ?? 'all';

        $builder = (new ConversationModel())->db->table('conversations c')
            ->select('c.id, c.status, c.unread_count, c.last_message_text, c.last_message_at, c.last_customer_message_at, ct.name as contact_name, ct.phone, ct.avatar_url as contact_avatar')
            ->join('contacts ct', 'ct.id = c.contact_id', 'left')
            ->where('c.account_id', session('account_id'));

        if ($status !== 'all') {
            $builder->where('c.status', $status);
        }

        if ($q) {
            $builder->groupStart()
                ->like('ct.name', $q)
                ->orLike('ct.phone', $q)
                ->orLike('c.last_message_text', $q)
            ->groupEnd();
        }

        $results = $builder->orderBy('c.last_message_at', 'DESC')->limit(30)->get()->getResultArray();

        return $this->response->setJSON($results);
    }
}
