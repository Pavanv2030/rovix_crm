<?php

namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\ContactModel;
use App\Models\BroadcastModel;
use App\Models\DealModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $today      = date('Y-m-d');
        $last7Days  = date('Y-m-d', strtotime('-7 days'));
        $last30Days = date('Y-m-d', strtotime('-30 days'));
        $last60Days = date('Y-m-d', strtotime('-60 days'));

        $convModel    = new ConversationModel();
        $messageModel = new MessageModel();
        $contactModel = new ContactModel();
        $dealModel    = new DealModel();

        // ── Conversations (last 30 days) ──────────────────────────────────
        $totalConversations = $convModel->where('updated_at >=', $last30Days)->countAllResults();
        $prevConversations  = (new ConversationModel())
            ->where('updated_at >=', $last60Days)->where('updated_at <', $last30Days)->countAllResults();
        $conversationChange = $prevConversations > 0
            ? (($totalConversations - $prevConversations) / $prevConversations) * 100 : 0;

        // ── Messages (last 30 days) ───────────────────────────────────────
        $totalMessages = $messageModel->where('created_at >=', $last30Days)->countAllResults();
        $prevMessages  = (new MessageModel())
            ->where('created_at >=', $last60Days)->where('created_at <', $last30Days)->countAllResults();
        $messageChange = $prevMessages > 0
            ? (($totalMessages - $prevMessages) / $prevMessages) * 100 : 0;

        // ── Contacts ──────────────────────────────────────────────────────
        $totalContacts = $contactModel->countAllResults();
        $prevContacts  = (new ContactModel())->where('created_at <', $last30Days)->countAllResults();
        $contactChange = $prevContacts > 0
            ? (($totalContacts - $prevContacts) / $prevContacts) * 100 : 0;

        // ── Active Deals ──────────────────────────────────────────────────
        $activeDeals     = $dealModel->where('status', 'open')->countAllResults();
        $activeDealValue = (new DealModel())->selectSum('value')->where('status', 'open')->first();

        // ── Avg Response Time (last 7 days) ───────────────────────────────
        $avgResponseTime = $this->calculateAvgResponseTime($last7Days);

        // ── Stats array ───────────────────────────────────────────────────
        $stats = [
            'conversations' => [
                'total'  => $totalConversations,
                'change' => round($conversationChange, 1),
                'label'  => 'Active Conversations',
                'icon'   => 'chat',
            ],
            'messages' => [
                'total'  => $totalMessages,
                'change' => round($messageChange, 1),
                'label'  => 'Messages (30d)',
                'icon'   => 'mail',
            ],
            'contacts' => [
                'total'  => $totalContacts,
                'change' => round($contactChange, 1),
                'label'  => 'Total Contacts',
                'icon'   => 'users',
            ],
            'deals' => [
                'total'  => $activeDeals,
                'value'  => $activeDealValue['value'] ?? 0,
                'label'  => 'Active Deals',
                'icon'   => 'money',
            ],
            'response_time' => [
                'total'  => $avgResponseTime,
                'label'  => 'Avg Response Time',
                'icon'   => 'clock',
                'unit'   => 'min',
            ],
        ];

        // ── Charts ────────────────────────────────────────────────────────
        $messagesOverTime    = $this->getMessagesOverTime($last7Days, $today);
        $conversationStatus  = $convModel
            ->select('status, COUNT(*) as count')
            ->where('updated_at >=', $last30Days)
            ->groupBy('status')
            ->findAll();

        // ── Recent Broadcasts ─────────────────────────────────────────────
        $broadcastModel   = new BroadcastModel();
        $recentBroadcasts = $broadcastModel
            ->select("broadcasts.*,
                (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = 'sent')      as sent_count,
                (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = 'delivered') as delivered_count,
                (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = 'read')      as read_count,
                (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = 'failed')    as failed_count")
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->findAll();

        // ── Activity Feed ─────────────────────────────────────────────────
        $recentActivity = $this->getRecentActivity(20);

        return view('dashboard/index', [
            'pageTitle'          => 'Dashboard',
            'stats'              => $stats,
            'messagesOverTime'   => $messagesOverTime,
            'conversationStatus' => $conversationStatus,
            'recentBroadcasts'   => $recentBroadcasts,
            'recentActivity'     => $recentActivity,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calculateAvgResponseTime(string $startDate): int
    {
        $accountId = session('account_id');
        $db = \Config\Database::connect();

        $result = $db->query("
            SELECT AVG(TIMESTAMPDIFF(SECOND,
                (SELECT MAX(m1.created_at)
                 FROM messages m1
                 WHERE m1.conversation_id = m.conversation_id
                   AND m1.sender_type = 'contact'
                   AND m1.created_at < m.created_at),
                m.created_at
            )) AS avg_seconds
            FROM messages m
            WHERE m.account_id = ?
              AND m.sender_type = 'agent'
              AND m.created_at >= ?
              AND EXISTS (
                  SELECT 1 FROM messages m2
                  WHERE m2.conversation_id = m.conversation_id
                    AND m2.sender_type = 'contact'
                    AND m2.created_at < m.created_at
              )
        ", [$accountId, $startDate])->getRow();

        return ($result && $result->avg_seconds) ? (int)($result->avg_seconds / 60) : 0;
    }

    private function getMessagesOverTime(string $startDate, string $endDate): array
    {
        $accountId = session('account_id');
        $db = \Config\Database::connect();

        return $db->query("
            SELECT
                DATE(created_at) AS date,
                COUNT(*) AS total,
                SUM(CASE WHEN sender_type = 'contact' THEN 1 ELSE 0 END) AS inbound,
                SUM(CASE WHEN sender_type != 'contact' THEN 1 ELSE 0 END) AS outbound
            FROM messages
            WHERE account_id = ?
              AND created_at >= ?
              AND created_at <= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$accountId, $startDate, $endDate . ' 23:59:59'])->getResultArray();
    }

    private function getRecentActivity(int $limit = 20): array
    {
        $accountId  = session('account_id');
        $db         = \Config\Database::connect();
        $activities = [];

        // Recent conversations
        foreach ($db->query("
            SELECT c.id, c.created_at AS ts, ct.name AS contact_name
            FROM conversations c
            JOIN contacts ct ON c.contact_id = ct.id
            WHERE c.account_id = ?
            ORDER BY c.created_at DESC LIMIT 8
        ", [$accountId])->getResultArray() as $row) {
            $activities[] = [
                'type'        => 'conversation',
                'timestamp'   => $row['ts'],
                'title'       => 'New conversation',
                'description' => 'Started with ' . $row['contact_name'],
                'icon'        => 'chat',
                'link'        => 'inbox/conversation/' . $row['id'],
            ];
        }

        // Recent broadcasts
        foreach ($db->query("
            SELECT id, created_at AS ts, name, status
            FROM broadcasts
            WHERE account_id = ?
            ORDER BY created_at DESC LIMIT 5
        ", [$accountId])->getResultArray() as $row) {
            $activities[] = [
                'type'        => 'broadcast',
                'timestamp'   => $row['ts'],
                'title'       => 'Broadcast ' . $row['status'],
                'description' => $row['name'],
                'icon'        => 'megaphone',
                'link'        => 'broadcasts/' . $row['id'],
            ];
        }

        // Recent deals
        foreach ($db->query("
            SELECT d.id, d.created_at AS ts, d.title AS deal_name, c.name AS contact_name
            FROM deals d
            JOIN contacts c ON d.contact_id = c.id
            WHERE d.account_id = ?
            ORDER BY d.created_at DESC LIMIT 5
        ", [$accountId])->getResultArray() as $row) {
            $activities[] = [
                'type'        => 'deal',
                'timestamp'   => $row['ts'],
                'title'       => 'Deal created',
                'description' => $row['deal_name'] . ' · ' . $row['contact_name'],
                'icon'        => 'money',
                'link'        => 'deals/' . $row['id'],
            ];
        }

        usort($activities, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));

        return array_slice($activities, 0, $limit);
    }
}
