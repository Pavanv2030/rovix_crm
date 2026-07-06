## PHASE 11: Dashboard & Analytics (Week 9)

### Prompt 11.1 — Main Dashboard UI & Metrics

```
Build the main dashboard with key metrics and analytics for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/dashboard/page.tsx
- src/components/dashboard/stats-cards.tsx
- src/components/dashboard/activity-feed.tsx
- src/components/dashboard/charts.tsx

IMPORTANT: Dashboard is the landing page after login. Show high-level overview of account activity, recent conversations, broadcast performance, and quick actions.

Create app/Controllers/DashboardController.php:

<?php
namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\ContactModel;
use App\Models\BroadcastModel;
use App\Models\DealModel;
use App\Models\AutomationModel;
use App\Models\FlowModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $accountId = session('account_id');

        // Date ranges
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $last7Days = date('Y-m-d', strtotime('-7 days'));
        $last30Days = date('Y-m-d', strtotime('-30 days'));

        // Key Metrics
        $conversationModel = new ConversationModel();
        $messageModel = new MessageModel();
        $contactModel = new ContactModel();
        $dealModel = new DealModel();

        // 1. Total Conversations (active in last 30 days)
        $totalConversations = $conversationModel
            ->where('updated_at >=', $last30Days)
            ->countAllResults();

        // Previous period comparison
        $prevConversations = $conversationModel
            ->where('updated_at >=', date('Y-m-d', strtotime('-60 days')))
            ->where('updated_at <', $last30Days)
            ->countAllResults();

        $conversationChange = $prevConversations > 0 
            ? (($totalConversations - $prevConversations) / $prevConversations) * 100 
            : 0;

        // 2. Total Messages (sent + received in last 30 days)
        $totalMessages = $messageModel
            ->where('created_at >=', $last30Days)
            ->countAllResults();

        $prevMessages = $messageModel
            ->where('created_at >=', date('Y-m-d', strtotime('-60 days')))
            ->where('created_at <', $last30Days)
            ->countAllResults();

        $messageChange = $prevMessages > 0 
            ? (($totalMessages - $prevMessages) / $prevMessages) * 100 
            : 0;

        // 3. Total Contacts
        $totalContacts = $contactModel->countAllResults();

        $prevContacts = $contactModel
            ->where('created_at <', $last30Days)
            ->countAllResults();

        $contactChange = $prevContacts > 0 
            ? (($totalContacts - $prevContacts) / $prevContacts) * 100 
            : 0;

        // 4. Active Deals (not won/lost)
        $activeDeals = $dealModel
            ->whereNotIn('stage_status', ['won', 'lost'])
            ->countAllResults();

        $activeDealValue = $dealModel
            ->selectSum('value')
            ->whereNotIn('stage_status', ['won', 'lost'])
            ->first();

        // 5. Response Time (average for last 7 days)
        // Calculate time between customer message and agent response
        $avgResponseTime = $this->calculateAverageResponseTime($last7Days);

        // Charts Data

        // Messages over time (last 7 days)
        $messagesOverTime = $this->getMessagesOverTime($last7Days, $today);

        // Conversation status breakdown
        $conversationStatus = $conversationModel
            ->select('status, COUNT(*) as count')
            ->where('updated_at >=', $last30Days)
            ->groupBy('status')
            ->findAll();

        // Broadcast performance (last 5 broadcasts)
        $broadcastModel = new BroadcastModel();
        $recentBroadcasts = $broadcastModel
            ->select('broadcasts.*, 
                      (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = "sent") as sent_count,
                      (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = "delivered") as delivered_count,
                      (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = "read") as read_count,
                      (SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = broadcasts.id AND status = "failed") as failed_count')
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->findAll();

        // Recent Activity Feed (last 20 items)
        $recentActivity = $this->getRecentActivity(20);

        // Quick Stats
        $stats = [
            'conversations' => [
                'total' => $totalConversations,
                'change' => round($conversationChange, 1),
                'label' => 'Active Conversations',
                'icon' => '💬'
            ],
            'messages' => [
                'total' => $totalMessages,
                'change' => round($messageChange, 1),
                'label' => 'Messages (30d)',
                'icon' => '📨'
            ],
            'contacts' => [
                'total' => $totalContacts,
                'change' => round($contactChange, 1),
                'label' => 'Total Contacts',
                'icon' => '👥'
            ],
            'deals' => [
                'total' => $activeDeals,
                'value' => $activeDealValue['value'] ?? 0,
                'label' => 'Active Deals',
                'icon' => '💰'
            ],
            'response_time' => [
                'total' => $avgResponseTime,
                'label' => 'Avg Response Time',
                'icon' => '⏱️',
                'unit' => 'min'
            ]
        ];

        return view('dashboard/index', [
            'stats' => $stats,
            'messagesOverTime' => $messagesOverTime,
            'conversationStatus' => $conversationStatus,
            'recentBroadcasts' => $recentBroadcasts,
            'recentActivity' => $recentActivity
        ]);
    }

    private function calculateAverageResponseTime(string $startDate): int
    {
        // Get all conversations with messages in the date range
        $db = \Config\Database::connect();
        
        $query = $db->query("
            SELECT 
                AVG(TIMESTAMPDIFF(SECOND, 
                    (SELECT MAX(m1.created_at) 
                     FROM messages m1 
                     WHERE m1.conversation_id = m.conversation_id 
                       AND m1.direction = 'inbound' 
                       AND m1.created_at < m.created_at),
                    m.created_at
                )) as avg_seconds
            FROM messages m
            WHERE m.direction = 'outbound'
              AND m.created_at >= ?
              AND EXISTS (
                  SELECT 1 FROM messages m2 
                  WHERE m2.conversation_id = m.conversation_id 
                    AND m2.direction = 'inbound' 
                    AND m2.created_at < m.created_at
              )
        ", [$startDate]);

        $result = $query->getRow();
        
        // Convert seconds to minutes
        return $result && $result->avg_seconds ? (int)($result->avg_seconds / 60) : 0;
    }

    private function getMessagesOverTime(string $startDate, string $endDate): array
    {
        $db = \Config\Database::connect();
        
        $query = $db->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound
            FROM messages
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$startDate, $endDate . ' 23:59:59']);

        return $query->getResultArray();
    }

    private function getRecentActivity(int $limit = 20): array
    {
        // Combine multiple activity sources into one feed
        $activities = [];

        $db = \Config\Database::connect();

        // Recent conversations
        $convQuery = $db->query("
            SELECT 
                'conversation' as type,
                c.id,
                c.created_at as timestamp,
                ct.name as contact_name,
                ct.phone as contact_phone,
                c.status
            FROM conversations c
            JOIN contacts ct ON c.contact_id = ct.id
            WHERE c.account_id = ?
            ORDER BY c.created_at DESC
            LIMIT 10
        ", [session('account_id')]);

        foreach ($convQuery->getResultArray() as $row) {
            $activities[] = [
                'type' => 'conversation',
                'timestamp' => $row['timestamp'],
                'title' => 'New conversation',
                'description' => "Started with {$row['contact_name']}",
                'icon' => '💬',
                'link' => '/inbox/' . $row['id']
            ];
        }

        // Recent broadcasts
        $broadcastQuery = $db->query("
            SELECT 
                'broadcast' as type,
                id,
                created_at as timestamp,
                name,
                status
            FROM broadcasts
            WHERE account_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ", [session('account_id')]);

        foreach ($broadcastQuery->getResultArray() as $row) {
            $activities[] = [
                'type' => 'broadcast',
                'timestamp' => $row['timestamp'],
                'title' => 'Broadcast sent',
                'description' => $row['name'],
                'icon' => '📢',
                'link' => '/broadcasts/' . $row['id']
            ];
        }

        // Recent deals
        $dealQuery = $db->query("
            SELECT 
                d.id,
                d.created_at as timestamp,
                d.name as deal_name,
                c.name as contact_name
            FROM deals d
            JOIN contacts c ON d.contact_id = c.id
            WHERE d.account_id = ?
            ORDER BY d.created_at DESC
            LIMIT 5
        ", [session('account_id')]);

        foreach ($dealQuery->getResultArray() as $row) {
            $activities[] = [
                'type' => 'deal',
                'timestamp' => $row['timestamp'],
                'title' => 'Deal created',
                'description' => "{$row['deal_name']} for {$row['contact_name']}",
                'icon' => '💰',
                'link' => '/deals/' . $row['id']
            ];
        }

        // Sort by timestamp DESC
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activities, 0, $limit);
    }
}

Create app/Views/dashboard/index.php:

<?php $this->extend('layouts/main'); ?>

<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
    <p class="text-sm text-gray-600 mt-1">Welcome back! Here's what's happening with your account.</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <!-- Conversations -->
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1"><?= $stats['conversations']['label'] ?></p>
                <p class="text-3xl font-semibold text-gray-900"><?= number_format($stats['conversations']['total']) ?></p>
                
                <?php if ($stats['conversations']['change'] != 0): ?>
                <p class="text-xs mt-2 <?= $stats['conversations']['change'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $stats['conversations']['change'] > 0 ? '▲' : '▼' ?> 
                    <?= abs($stats['conversations']['change']) ?>% vs last month
                </p>
                <?php endif; ?>
            </div>
            <div class="text-3xl"><?= $stats['conversations']['icon'] ?></div>
        </div>
    </div>

    <!-- Messages -->
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1"><?= $stats['messages']['label'] ?></p>
                <p class="text-3xl font-semibold text-gray-900"><?= number_format($stats['messages']['total']) ?></p>
                
                <?php if ($stats['messages']['change'] != 0): ?>
                <p class="text-xs mt-2 <?= $stats['messages']['change'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $stats['messages']['change'] > 0 ? '▲' : '▼' ?> 
                    <?= abs($stats['messages']['change']) ?>% vs last month
                </p>
                <?php endif; ?>
            </div>
            <div class="text-3xl"><?= $stats['messages']['icon'] ?></div>
        </div>
    </div>

    <!-- Contacts -->
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1"><?= $stats['contacts']['label'] ?></p>
                <p class="text-3xl font-semibold text-gray-900"><?= number_format($stats['contacts']['total']) ?></p>
                
                <?php if ($stats['contacts']['change'] != 0): ?>
                <p class="text-xs mt-2 <?= $stats['contacts']['change'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $stats['contacts']['change'] > 0 ? '▲' : '▼' ?> 
                    <?= abs($stats['contacts']['change']) ?>% growth
                </p>
                <?php endif; ?>
            </div>
            <div class="text-3xl"><?= $stats['contacts']['icon'] ?></div>
        </div>
    </div>

    <!-- Active Deals -->
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1"><?= $stats['deals']['label'] ?></p>
                <p class="text-3xl font-semibold text-gray-900"><?= number_format($stats['deals']['total']) ?></p>
                <p class="text-xs text-gray-500 mt-2">
                    $<?= number_format($stats['deals']['value'], 2) ?> total value
                </p>
            </div>
            <div class="text-3xl"><?= $stats['deals']['icon'] ?></div>
        </div>
    </div>

    <!-- Response Time -->
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1"><?= $stats['response_time']['label'] ?></p>
                <p class="text-3xl font-semibold text-gray-900">
                    <?= $stats['response_time']['total'] ?><span class="text-base text-gray-500"><?= $stats['response_time']['unit'] ?></span>
                </p>
                <p class="text-xs text-gray-500 mt-2">Last 7 days</p>
            </div>
            <div class="text-3xl"><?= $stats['response_time']['icon'] ?></div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Messages Over Time Chart -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Messages Over Time</h3>
        <canvas id="messages-chart" class="w-full" style="max-height: 300px;"></canvas>
    </div>

    <!-- Conversation Status Chart -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Conversation Status</h3>
        <canvas id="status-chart" class="w-full" style="max-height: 300px;"></canvas>
    </div>
</div>

<!-- Bottom Row: Broadcasts & Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Broadcasts -->
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Recent Broadcasts</h3>
                <a href="<?= base_url('broadcasts') ?>" class="text-sm text-blue-600 hover:text-blue-700">View all →</a>
            </div>
        </div>
        
        <div class="divide-y divide-gray-200">
            <?php if (!empty($recentBroadcasts)): ?>
                <?php foreach ($recentBroadcasts as $broadcast): ?>
                <a href="<?= base_url('broadcasts/' . $broadcast['id']) ?>" 
                   class="block p-4 hover:bg-gray-50 transition">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="font-medium text-gray-900"><?= esc($broadcast['name']) ?></p>
                            <p class="text-sm text-gray-600 mt-1">
                                <?= date('M j, Y g:i A', strtotime($broadcast['created_at'])) ?>
                            </p>
                        </div>
                        
                        <div class="text-right ml-4">
                            <span class="inline-block px-2 py-1 text-xs rounded-full
                                <?= $broadcast['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                    ($broadcast['status'] === 'processing' ? 'bg-blue-100 text-blue-800' : 
                                    'bg-gray-100 text-gray-800') ?>">
                                <?= ucfirst($broadcast['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4 mt-3 text-xs text-gray-600">
                        <span>✓ <?= number_format($broadcast['sent_count']) ?> sent</span>
                        <span>📨 <?= number_format($broadcast['delivered_count']) ?> delivered</span>
                        <span>👁️ <?= number_format($broadcast['read_count']) ?> read</span>
                        <?php if ($broadcast['failed_count'] > 0): ?>
                        <span class="text-red-600">✗ <?= number_format($broadcast['failed_count']) ?> failed</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-gray-500">
                    <p>No recent broadcasts</p>
                    <a href="<?= base_url('broadcasts/create') ?>" 
                       class="inline-block mt-3 text-blue-600 hover:text-blue-700">
                        Create your first broadcast →
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity Feed -->
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
        </div>
        
        <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
            <?php if (!empty($recentActivity)): ?>
                <?php foreach ($recentActivity as $activity): ?>
                <a href="<?= base_url($activity['link']) ?>" 
                   class="block p-4 hover:bg-gray-50 transition">
                    <div class="flex items-start gap-3">
                        <div class="text-2xl"><?= $activity['icon'] ?></div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900"><?= esc($activity['title']) ?></p>
                            <p class="text-sm text-gray-600 truncate"><?= esc($activity['description']) ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php
                                $timestamp = strtotime($activity['timestamp']);
                                $now = time();
                                $diff = $now - $timestamp;
                                
                                if ($diff < 60) {
                                    echo 'Just now';
                                } elseif ($diff < 3600) {
                                    echo floor($diff / 60) . ' min ago';
                                } elseif ($diff < 86400) {
                                    echo floor($diff / 3600) . ' hours ago';
                                } else {
                                    echo date('M j, g:i A', $timestamp);
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-gray-500">
                    <p>No recent activity</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js Implementation -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Messages Over Time Chart
const messagesCtx = document.getElementById('messages-chart').getContext('2d');
const messagesData = <?= json_encode($messagesOverTime) ?>;

new Chart(messagesCtx, {
    type: 'line',
    data: {
        labels: messagesData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [
            {
                label: 'Inbound',
                data: messagesData.map(d => d.inbound),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3
            },
            {
                label: 'Outbound',
                data: messagesData.map(d => d.outbound),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Conversation Status Chart
const statusCtx = document.getElementById('status-chart').getContext('2d');
const statusData = <?= json_encode($conversationStatus) ?>;

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusData.map(d => {
            return d.status.charAt(0).toUpperCase() + d.status.slice(1);
        }),
        datasets: [{
            data: statusData.map(d => d.count),
            backgroundColor: [
                '#3b82f6', // open - blue
                '#10b981', // closed - green
                '#f59e0b', // pending - amber
                '#6b7280'  // other - gray
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'right'
            }
        }
    }
});
</script>

<?php $this->endSection(); ?>

Add route:
GET / → DashboardController::index
GET /dashboard → DashboardController::index
```

### Testing Phase 11 (Dashboard)

Manual test checklist:

```bash
# 1. Navigate to dashboard
http://localhost:8080/

# Test: Dashboard loads, all stat cards visible

# 2. Check stat cards
# Test: 
- Conversations card shows count + change %
- Messages card shows total + change %
- Contacts card shows total + growth %
- Active Deals card shows count + value
- Response Time card shows avg in minutes

# 3. Check charts
# Test:
- Messages Over Time chart renders (7-day line chart)
- Inbound/outbound lines visible
- Conversation Status doughnut chart renders
- Status breakdown shows open/closed/pending

# 4. Check recent broadcasts section
# Test:
- Shows last 5 broadcasts
- Each shows name, timestamp, status badge
- Shows sent/delivered/read/failed counts
- Click broadcast → navigates to detail page

# 5. Check recent activity feed
# Test:
- Shows combined activity (conversations, broadcasts, deals)
- Sorted by timestamp DESC
- Shows relative time ("5 min ago", "2 hours ago")
- Icons match activity type
- Click activity → navigates to detail page

# 6. Empty states
- Create new account with no data
# Test: Empty state messages show ("No recent broadcasts", etc.)

# 7. Performance test
- Account with 10,000+ messages
# Test: Dashboard loads within 2 seconds

# 8. Date range accuracy
- Check "Last 30 days" calculations
# Test: Only data from last 30 days included in totals

# 9. Response time calculation
- Send messages with varying delays
# Test: Avg response time reflects actual delays

# 10. Tenant isolation
- Login as different account
# Test: Dashboard shows only own account data, not other accounts

# 11. Responsive design
- Resize browser window
# Test:
- Stat cards stack on mobile (1 column)
- Charts stack on mobile
- Activity feed scrollable
```

**Pass Criteria:**
- ✅ Dashboard loads quickly (<2s with normal data)
- ✅ All 5 stat cards display correctly
- ✅ Change percentages calculate accurately
- ✅ Messages over time chart renders with 7-day data
- ✅ Conversation status chart renders with breakdown
- ✅ Recent broadcasts list shows last 5 with metrics
- ✅ Recent activity feed combines multiple sources
- ✅ Relative timestamps work ("5 min ago")
- ✅ All links navigate correctly
- ✅ Empty states show when no data
- ✅ Responsive on mobile/tablet
- ✅ Tenant isolation works (only own data)
- ✅ No N+1 query issues
- ✅ Charts don't break with zero data

**Common Issues:**
- Chart.js not loading: Check CDN URL, check browser console
- Stats showing zero: Check date range calculations, check timezone issues
- Change % incorrect: Check previous period calculation logic
- Response time inaccurate: Check SQL query for agent response detection
- Activity feed empty: Check UNION query, check account_id filter
- Broadcasts not showing metrics: Check JOIN with broadcast_recipients table
- Chart rendering issues: Check data format (arrays not objects)
- Mobile layout broken: Check Tailwind grid classes (md:, lg:)
- Tenant leak: Check all queries have account_id filter or BaseModel scoping
- Slow dashboard: Add indexes on created_at, updated_at, account_id columns

---
