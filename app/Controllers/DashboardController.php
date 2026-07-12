<?php

namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\BroadcastModel;

class DashboardController extends BaseController
{
    private const CACHE_TTL = 300;

    public function index()
    {
        $accountId = session('account_id');
        $cacheKey  = 'dashboard_v2_' . $accountId;
        $cache     = \Config\Services::cache();

        $cached = $cache->get($cacheKey);
        if (is_array($cached)) {
            return view('dashboard/index', array_merge(['pageTitle' => 'Dashboard'], $cached));
        }

        $today      = date('Y-m-d');
        $last7Days  = date('Y-m-d', strtotime('-7 days'));
        $last30Days = date('Y-m-d', strtotime('-30 days'));
        $last60Days = date('Y-m-d', strtotime('-60 days'));

        $db = \Config\Database::connect();

        // ── Batched count queries (4 queries instead of 8+) ───────────────
        $convRow = $db->query("
            SELECT
                SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END) AS curr,
                SUM(CASE WHEN updated_at >= ? AND updated_at < ? THEN 1 ELSE 0 END) AS prev
            FROM conversations WHERE account_id = ?
        ", [$last30Days, $last60Days, $last30Days, $accountId])->getRow();

        $msgRow = $db->query("
            SELECT
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS curr,
                SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS prev
            FROM messages WHERE account_id = ?
        ", [$last30Days, $last60Days, $last30Days, $accountId])->getRow();

        $contactRow = $db->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS prev
            FROM contacts WHERE account_id = ?
        ", [$last30Days, $accountId])->getRow();

        $dealRow = $db->query("
            SELECT COUNT(*) AS active_count, COALESCE(SUM(value), 0) AS total_value
            FROM deals WHERE account_id = ? AND status = 'open'
        ", [$accountId])->getRow();

        $totalConversations = (int) ($convRow->curr ?? 0);
        $prevConversations  = (int) ($convRow->prev ?? 0);
        $conversationChange = $prevConversations > 0
            ? (($totalConversations - $prevConversations) / $prevConversations) * 100 : 0;

        $totalMessages = (int) ($msgRow->curr ?? 0);
        $prevMessages  = (int) ($msgRow->prev ?? 0);
        $messageChange = $prevMessages > 0
            ? (($totalMessages - $prevMessages) / $prevMessages) * 100 : 0;

        $totalContacts = (int) ($contactRow->total ?? 0);
        $prevContacts  = (int) ($contactRow->prev ?? 0);
        $contactChange = $prevContacts > 0
            ? (($totalContacts - $prevContacts) / $prevContacts) * 100 : 0;

        $activeDeals     = (int) ($dealRow->active_count ?? 0);
        $activeDealValue = (float) ($dealRow->total_value ?? 0);

        $avgResponseTime = $this->calculateAvgResponseTime($accountId, $last7Days);

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
                'value'  => $activeDealValue,
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

        $messagesOverTime = $this->getMessagesOverTime($accountId, $last7Days, $today);

        $convModel = new ConversationModel();
        $conversationStatus = $convModel
            ->select('status, COUNT(*) as count')
            ->where('updated_at >=', $last30Days)
            ->groupBy('status')
            ->findAll();

        $broadcastModel   = new BroadcastModel();
        $recentBroadcasts = $broadcastModel
            ->select("broadcasts.*,
                COALESCE(SUM(CASE WHEN br.status = 'sent'      THEN 1 ELSE 0 END), 0) AS sent_count,
                COALESCE(SUM(CASE WHEN br.status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_count,
                COALESCE(SUM(CASE WHEN br.status = 'read'      THEN 1 ELSE 0 END), 0) AS read_count,
                COALESCE(SUM(CASE WHEN br.status = 'failed'    THEN 1 ELSE 0 END), 0) AS failed_count")
            ->join('broadcast_recipients br', 'br.broadcast_id = broadcasts.id', 'left')
            ->groupBy('broadcasts.id')
            ->orderBy('broadcasts.created_at', 'DESC')
            ->limit(5)
            ->findAll();

        $recentActivity = $this->getRecentActivity($accountId, 20);

        $payload = [
            'stats'              => $stats,
            'messagesOverTime'   => $messagesOverTime,
            'conversationStatus' => $conversationStatus,
            'recentBroadcasts'   => $recentBroadcasts,
            'recentActivity'     => $recentActivity,
        ];

        $cache->save($cacheKey, $payload, self::CACHE_TTL);

        return view('dashboard/index', array_merge(['pageTitle' => 'Dashboard'], $payload));
    }

    private function calculateAvgResponseTime(int|string $accountId, string $startDate): int
    {
        $db = \Config\Database::connect();

        $result = $db->query("
            SELECT AVG(TIMESTAMPDIFF(SECOND,
                (SELECT MAX(m1.created_at)
                 FROM messages m1
                 WHERE m1.conversation_id = m.conversation_id
                   AND m1.sender_type = 'customer'
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
                    AND m2.sender_type = 'customer'
                    AND m2.created_at < m.created_at
              )
        ", [$accountId, $startDate])->getRow();

        return ($result && $result->avg_seconds) ? (int) ($result->avg_seconds / 60) : 0;
    }

    private function getMessagesOverTime(int|string $accountId, string $startDate, string $endDate): array
    {
        $db = \Config\Database::connect();

        return $db->query("
            SELECT
                DATE(created_at) AS date,
                COUNT(*) AS total,
                SUM(CASE WHEN sender_type = 'customer' THEN 1 ELSE 0 END) AS inbound,
                SUM(CASE WHEN sender_type != 'customer' THEN 1 ELSE 0 END) AS outbound
            FROM messages
            WHERE account_id = ?
              AND created_at >= ?
              AND created_at <= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$accountId, $startDate, $endDate . ' 23:59:59'])->getResultArray();
    }

    private function getRecentActivity(int|string $accountId, int $limit = 20): array
    {
        $db = \Config\Database::connect();

        $rows = $db->query("
            SELECT type, ts, title, description, icon, link FROM (
                SELECT 'conversation' AS type, c.created_at AS ts,
                    'New conversation' AS title,
                    CONCAT('Started with ', ct.name) AS description,
                    'chat' AS icon,
                    CONCAT('inbox/conversation/', c.id) AS link
                FROM conversations c
                JOIN contacts ct ON c.contact_id = ct.id
                WHERE c.account_id = ?
                ORDER BY c.created_at DESC LIMIT 8
            ) conv
            UNION ALL
            SELECT type, ts, title, description, icon, link FROM (
                SELECT 'broadcast' AS type, created_at AS ts,
                    CONCAT('Broadcast ', status) AS title,
                    name AS description,
                    'megaphone' AS icon,
                    CONCAT('broadcasts/', id) AS link
                FROM broadcasts
                WHERE account_id = ?
                ORDER BY created_at DESC LIMIT 5
            ) bc
            UNION ALL
            SELECT type, ts, title, description, icon, link FROM (
                SELECT 'deal' AS type, d.created_at AS ts,
                    'Deal created' AS title,
                    CONCAT(d.title, ' · ', c.name) AS description,
                    'money' AS icon,
                    CONCAT('deals/', d.id) AS link
                FROM deals d
                JOIN contacts c ON d.contact_id = c.id
                WHERE d.account_id = ?
                ORDER BY d.created_at DESC LIMIT 5
            ) dl
            ORDER BY ts DESC
            LIMIT ?
        ", [$accountId, $accountId, $accountId, $limit])->getResultArray();

        return array_map(static fn (array $row) => [
            'type'        => $row['type'],
            'timestamp'   => $row['ts'],
            'title'       => $row['title'],
            'description' => $row['description'],
            'icon'        => $row['icon'],
            'link'        => $row['link'],
        ], $rows);
    }
}