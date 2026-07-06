<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Header -->
<div class="mb-6">
    <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-sm text-gray-500 mt-0.5">
        Welcome back, <?= esc(current_profile()['full_name'] ?? 'User') ?>! Here's your account overview.
    </p>
</div>

<!-- ── Stat Cards ──────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">

    <?php
    $cards = [
        ['key' => 'conversations', 'color' => 'blue'],
        ['key' => 'messages',      'color' => 'indigo'],
        ['key' => 'contacts',      'color' => 'teal'],
        ['key' => 'deals',         'color' => 'amber'],
        ['key' => 'response_time', 'color' => 'purple'],
    ];
    $colors = [
        'blue'   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-600',   'num' => 'text-blue-700'],
        'indigo' => ['bg' => 'bg-indigo-50',  'text' => 'text-indigo-600', 'num' => 'text-indigo-700'],
        'teal'   => ['bg' => 'bg-teal-50',   'text' => 'text-teal-600',   'num' => 'text-teal-700'],
        'amber'  => ['bg' => 'bg-amber-50',  'text' => 'text-amber-600',  'num' => 'text-amber-700'],
        'purple' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'num' => 'text-purple-700'],
    ];
    foreach ($cards as $card):
        $s = $stats[$card['key']];
        $c = $colors[$card['color']];
    ?>
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <div class="flex items-start justify-between mb-3">
            <p class="text-xs font-medium text-gray-500 leading-tight"><?= $s['label'] ?></p>
            <div class="w-9 h-9 <?= $c['bg'] ?> rounded-lg flex items-center justify-center flex-shrink-0">
                <?= rx_icon($s['icon'], 'w-5 h-5') ?>
            </div>
        </div>

        <div class="text-2xl font-bold <?= $c['num'] ?>">
            <?php if ($card['key'] === 'response_time'): ?>
                <?= $s['total'] ?><span class="text-sm font-normal text-gray-400 ml-0.5"><?= $s['unit'] ?></span>
            <?php elseif ($card['key'] === 'deals'): ?>
                <?= number_format($s['total']) ?>
            <?php else: ?>
                <?= number_format($s['total']) ?>
            <?php endif; ?>
        </div>

        <div class="mt-1 text-xs text-gray-400">
            <?php if ($card['key'] === 'deals'): ?>
                ₹<?= number_format($s['value'], 0) ?> pipeline value
            <?php elseif ($card['key'] === 'response_time'): ?>
                Last 7 days average
            <?php elseif (!empty($s['change'])): ?>
                <span class="<?= $s['change'] > 0 ? 'text-green-600' : 'text-red-500' ?> font-medium">
                    <?= $s['change'] > 0 ? '▲' : '▼' ?> <?= abs($s['change']) ?>%
                </span>
                vs last 30d
            <?php else: ?>
                &nbsp;
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Quick Actions ──────────────────────────────────────────────────────── -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="<?= base_url('contacts/create') ?>"
       class="px-3 py-1.5 bg-blue-900 text-white text-xs rounded-lg hover:bg-blue-800 font-medium">
        + New Contact
    </a>
    <a href="<?= base_url('broadcasts/create') ?>"
       class="px-3 py-1.5 bg-white border border-gray-200 text-xs rounded-lg hover:bg-gray-50 text-gray-700 font-medium">
        + New Broadcast
    </a>
    <a href="<?= base_url('flows/create') ?>"
       class="px-3 py-1.5 bg-white border border-gray-200 text-xs rounded-lg hover:bg-gray-50 text-gray-700 font-medium">
        + New Flow
    </a>
    <a href="<?= base_url('inbox') ?>"
       class="px-3 py-1.5 bg-white border border-gray-200 text-xs rounded-lg hover:bg-gray-50 text-gray-700 font-medium">
        Open Inbox
    </a>
</div>

<!-- ── Charts Row ─────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

    <!-- Messages over time -->
    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-800">Messages — Last 7 Days</h3>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>Inbound</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>Outbound</span>
            </div>
        </div>
        <div style="height: 220px; position: relative;">
            <canvas id="messages-chart"></canvas>
        </div>
    </div>

    <!-- Conversation status -->
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Conversation Status</h3>
        <div style="height: 220px; position: relative;">
            <canvas id="status-chart"></canvas>
        </div>
        <?php if (empty($conversationStatus)): ?>
        <p class="text-center text-xs text-gray-400 mt-2">No conversations yet</p>
        <?php endif; ?>
    </div>
</div>

<!-- ── Bottom Row ─────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    <!-- Recent Broadcasts -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Recent Broadcasts</h3>
            <a href="<?= base_url('broadcasts') ?>" class="text-xs text-blue-600 hover:text-blue-700">View all →</a>
        </div>

        <?php if (empty($recentBroadcasts)): ?>
        <div class="p-8 text-center">
            <div class="mb-2"><?= rx_icon('megaphone', 'w-12 h-12', 'mx-auto') ?></div>
            <p class="text-sm text-gray-500 mb-3">No broadcasts yet</p>
            <a href="<?= base_url('broadcasts/create') ?>"
               class="text-xs text-blue-600 hover:text-blue-700 font-medium">Create your first broadcast →</a>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($recentBroadcasts as $bc):
                $statusClass = match($bc['status'] ?? '') {
                    'completed'  => 'bg-green-100 text-green-700',
                    'processing' => 'bg-blue-100 text-blue-700',
                    'scheduled'  => 'bg-amber-100 text-amber-700',
                    'failed'     => 'bg-red-100 text-red-700',
                    default      => 'bg-gray-100 text-gray-600',
                };
            ?>
            <a href="<?= base_url('broadcasts/' . $bc['id']) ?>"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-sm text-gray-800 truncate"><?= esc($bc['name']) ?></div>
                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                        <span><?= rx_icon('check', 'w-4 h-4') ?> <?= number_format($bc['sent_count'] ?? 0) ?> sent</span>
                        <span><?= rx_icon('eye', 'w-4 h-4') ?> <?= number_format($bc['read_count'] ?? 0) ?> read</span>
                        <?php if (($bc['failed_count'] ?? 0) > 0): ?>
                        <span class="text-red-500"><?= rx_icon('x', 'w-4 h-4') ?> <?= number_format($bc['failed_count']) ?> failed</span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $statusClass ?>">
                    <?= ucfirst($bc['status'] ?? '') ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Recent Activity</h3>
        </div>

        <?php if (empty($recentActivity)): ?>
        <div class="p-8 text-center">
            <div class="mb-2"><?= rx_icon('inbox-empty', 'w-12 h-12', 'mx-auto') ?></div>
            <p class="text-sm text-gray-500">No activity yet</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
            <?php foreach ($recentActivity as $activity):
                $ts   = strtotime($activity['timestamp']);
                $diff = time() - $ts;
                if ($diff < 60)         $ago = 'Just now';
                elseif ($diff < 3600)   $ago = floor($diff / 60) . 'm ago';
                elseif ($diff < 86400)  $ago = floor($diff / 3600) . 'h ago';
                else                    $ago = date('M j', $ts);
            ?>
            <a href="<?= base_url($activity['link']) ?>"
               class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                    <?= rx_icon($activity['icon'], 'w-4 h-4') ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-800"><?= esc($activity['title']) ?></div>
                    <div class="text-xs text-gray-500 truncate mt-0.5"><?= esc($activity['description']) ?></div>
                </div>
                <div class="text-xs text-gray-400 flex-shrink-0 mt-0.5"><?= $ago ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Chart.js ───────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const msgData    = <?= json_encode($messagesOverTime,   JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const statusData = <?= json_encode($conversationStatus, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

// Messages line chart
new Chart(document.getElementById('messages-chart'), {
    type: 'line',
    data: {
        labels: msgData.map(d => {
            const dt = new Date(d.date);
            return dt.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
        }),
        datasets: [
            {
                label: 'Inbound',
                data: msgData.map(d => parseInt(d.inbound)  || 0),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.08)',
                borderWidth: 2,
                tension: 0.35,
                fill: true,
                pointRadius: 3,
            },
            {
                label: 'Outbound',
                data: msgData.map(d => parseInt(d.outbound) || 0),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.08)',
                borderWidth: 2,
                tension: 0.35,
                fill: true,
                pointRadius: 3,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
            x: { grid: { display: false } },
        },
    },
});

// Conversation status doughnut
const statusColors = {
    open:    '#3b82f6',
    closed:  '#10b981',
    pending: '#f59e0b',
    default: '#6b7280',
};

if (statusData.length > 0) {
    new Chart(document.getElementById('status-chart'), {
        type: 'doughnut',
        data: {
            labels: statusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
            datasets: [{
                data: statusData.map(d => parseInt(d.count)),
                backgroundColor: statusData.map(d => statusColors[d.status] || statusColors.default),
                borderWidth: 0,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 } } },
            },
            cutout: '65%',
        },
    });
}
</script>

<?= $this->endSection() ?>
