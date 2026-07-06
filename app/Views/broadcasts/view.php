<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<script>
window.__broadcastId  = '<?= esc($broadcast['id']) ?>';
window.__broadcastStatus = '<?= esc($broadcast['status']) ?>';
</script>

<div class="flex items-center gap-2 mb-5 text-sm">
    <a href="<?= base_url('broadcasts') ?>" class="text-gray-400 hover:text-gray-600">← Broadcasts</a>
    <span class="text-gray-300">/</span>
    <span class="text-gray-700 font-medium"><?= esc($broadcast['name']) ?></span>
</div>

<?php if (session('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= esc(session('success')) ?></div>
<?php endif; ?>
<?php if (session('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= esc(session('error')) ?></div>
<?php endif; ?>

<?php
$statusBg = ['draft' => 'bg-gray-100 text-gray-600', 'scheduled' => 'bg-blue-100 text-blue-700', 'sending' => 'bg-yellow-100 text-yellow-700', 'sent' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-red-100 text-red-600'];
?>

<!-- Auto-refresh when sending -->
<?php if ($broadcast['status'] === 'sending'): ?>
<div x-data="{ pct: <?= $pct ?> }" x-init="setInterval(async () => {
    const r = await fetch(window.__BASE + 'api/broadcasts/<?= $broadcast['id'] ?>/progress');
    const d = await r.json();
    pct = d.percentage;
    if (d.status !== 'sending') location.reload();
}, 5000)">
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 flex items-center gap-3">
        <div class="animate-spin h-5 w-5 border-2 border-yellow-500 border-t-transparent rounded-full flex-shrink-0"></div>
        <div class="flex-1">
            <p class="text-sm font-medium text-yellow-800">Sending in progress — auto-refreshing</p>
            <div class="w-full bg-yellow-200 rounded-full h-1.5 mt-2">
                <div class="bg-yellow-500 h-1.5 rounded-full transition-all" :style="`width:${pct}%`"></div>
            </div>
        </div>
        <span class="text-yellow-700 font-bold" x-text="pct + '%'"></span>
    </div>
</div>
<?php endif; ?>

<div class="flex flex-col lg:flex-row gap-6">

    <!-- Left: Info & Actions -->
    <div class="w-full lg:w-72 flex-shrink-0 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-4">
                <h2 class="font-bold text-gray-900"><?= esc($broadcast['name']) ?></h2>
                <span class="text-xs px-2 py-1 rounded-full font-medium <?= $statusBg[$broadcast['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                    <?= ucfirst($broadcast['status']) ?>
                </span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-400">Template</span>
                    <span class="font-mono text-gray-700 text-xs"><?= esc($broadcast['template_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Language</span>
                    <span class="text-gray-700 uppercase font-mono text-xs"><?= esc($broadcast['template_language']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Created</span>
                    <span class="text-gray-700"><?= date('d M Y', strtotime($broadcast['created_at'])) ?></span>
                </div>
                <?php if ($broadcast['scheduled_at']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-400">Scheduled</span>
                    <span class="text-gray-700"><?= date('d M Y, g:i A', strtotime($broadcast['scheduled_at'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($broadcast['sent_at']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-400">Sent at</span>
                    <span class="text-gray-700"><?= date('d M Y, g:i A', strtotime($broadcast['sent_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="space-y-2">
            <?php if ($broadcast['status'] === 'draft'): ?>
            <form action="<?= base_url('broadcasts/' . $broadcast['id'] . '/send') ?>" method="POST"
                  onsubmit="return confirm('Send this broadcast to <?= $broadcast['total_recipients'] ?: 'all matching' ?> contacts now?')">
                <?= csrf_field() ?>
                <button type="submit" class="w-full py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg font-medium">
                    Send Now
                </button>
            </form>

            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="w-full py-2 bg-blue-100 hover:bg-blue-200 text-blue-800 text-sm rounded-lg font-medium">
                    Schedule for Later
                </button>
                <div x-show="open" @click.outside="open = false" class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl p-4 shadow-lg z-10">
                    <form action="<?= base_url('broadcasts/' . $broadcast['id'] . '/schedule') ?>" method="POST">
                        <?= csrf_field() ?>
                        <label class="block text-xs text-gray-600 mb-1">Date & Time</label>
                        <input type="datetime-local" name="scheduled_at" required
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="w-full py-1.5 bg-blue-900 text-white text-sm rounded-lg">Confirm Schedule</button>
                    </form>
                </div>
            </div>

            <a href="<?= base_url('broadcasts/' . $broadcast['id'] . '/edit') ?>"
               class="block text-center w-full py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">Edit</a>

            <?php if (has_min_role('admin')): ?>
            <form action="<?= base_url('broadcasts/' . $broadcast['id'] . '/delete') ?>" method="POST"
                  onsubmit="return confirm('Delete this broadcast permanently?')">
                <?= csrf_field() ?>
                <button type="submit" class="w-full py-2 text-red-600 text-sm hover:text-red-700">Delete</button>
            </form>
            <?php endif; ?>

            <?php elseif ($broadcast['status'] === 'scheduled'): ?>
            <form action="<?= base_url('broadcasts/' . $broadcast['id'] . '/cancel') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="w-full py-2 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 text-sm rounded-lg">Unschedule</button>
            </form>
            <?php endif; ?>

            <!-- Always-available actions -->
            <div class="pt-2 border-t border-gray-100 space-y-2">
                <?php if (in_array($broadcast['status'], ['sent', 'sending']) && $stats['failed'] > 0): ?>
                <form action="<?= base_url('broadcasts/' . $broadcast['id'] . '/retry') ?>" method="POST"
                      onsubmit="return confirm('Retry <?= $stats['failed'] ?> failed recipients?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full py-2 bg-orange-50 hover:bg-orange-100 text-orange-700 text-sm rounded-lg font-medium border border-orange-200">
                        ↻ Retry Failed (<?= $stats['failed'] ?>)
                    </button>
                </form>
                <?php endif; ?>

                <a href="<?= base_url('broadcasts/' . $broadcast['id'] . '/export') ?>"
                   class="block text-center w-full py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-sm rounded-lg border border-gray-200">
                    ↓ Export CSV
                </a>

                <form action="<?= base_url('broadcasts/' . $broadcast['id'] . '/duplicate') ?>" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full py-2 text-gray-500 hover:text-gray-700 text-sm">Duplicate</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Stats & Recipients -->
    <div class="flex-1 space-y-4">

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <?php
            $statCards = [
                ['label' => 'Recipients', 'value' => $stats['total'], 'color' => 'text-gray-700'],
                ['label' => 'Sent',       'value' => $stats['sent'],  'color' => 'text-blue-600'],
                ['label' => 'Delivered',  'value' => $stats['delivered'], 'color' => 'text-indigo-600'],
                ['label' => 'Read',       'value' => $stats['read'],  'color' => 'text-green-600'],
                ['label' => 'Replied',    'value' => $stats['replied'], 'color' => 'text-purple-600'],
                ['label' => 'Failed',     'value' => $stats['failed'], 'color' => 'text-red-500'],
                ['label' => 'Pending',    'value' => $stats['pending'], 'color' => 'text-yellow-600'],
            ];
            ?>
            <?php foreach ($statCards as $card): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold <?= $card['color'] ?>"><?= number_format($card['value']) ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?= $card['label'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Progress bar -->
        <?php if ($stats['total'] > 0): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="flex justify-between text-xs text-gray-500 mb-2">
                <span><?= $stats['sent'] + $stats['failed'] ?> / <?= $stats['total'] ?> processed</span>
                <span><?= $pct ?>% complete</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="bg-green-500 h-2 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($stats['sent'] > 0): ?>
        <?php
        $sent      = max(1, $stats['sent']);
        $delRate   = round($stats['delivered'] / $sent * 100);
        $readRate  = round($stats['read']       / $sent * 100);
        $replyRate = round($stats['replied']    / $sent * 100);
        $failRate  = $stats['total'] > 0 ? round($stats['failed'] / $stats['total'] * 100) : 0;
        ?>

        <!-- Rate Metrics -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-indigo-600"><?= $delRate ?>%</p>
                <p class="text-xs text-gray-500 mt-0.5">Delivery Rate</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-green-600"><?= $readRate ?>%</p>
                <p class="text-xs text-gray-500 mt-0.5">Read Rate</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-purple-600"><?= $replyRate ?>%</p>
                <p class="text-xs text-gray-500 mt-0.5">Reply Rate</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold <?= $failRate > 5 ? 'text-red-500' : 'text-gray-400' ?>"><?= $failRate ?>%</p>
                <p class="text-xs text-gray-500 mt-0.5">Failure Rate</p>
            </div>
        </div>

        <!-- Delivery Funnel -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Delivery Funnel</h3>
            <div class="space-y-3">
                <?php
                $funnel = [
                    ['label' => 'Sent',      'value' => $stats['sent'],      'color' => 'bg-blue-500',   'pct' => 100],
                    ['label' => 'Delivered', 'value' => $stats['delivered'], 'color' => 'bg-indigo-500', 'pct' => $delRate],
                    ['label' => 'Read',      'value' => $stats['read'],      'color' => 'bg-green-500',  'pct' => $readRate],
                    ['label' => 'Replied',   'value' => $stats['replied'],   'color' => 'bg-purple-500', 'pct' => $replyRate],
                ];
                foreach ($funnel as $f):
                ?>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 w-16 flex-shrink-0"><?= $f['label'] ?></span>
                    <div class="flex-1 bg-gray-100 rounded-full h-7 relative overflow-hidden">
                        <div class="<?= $f['color'] ?> h-7 rounded-full flex items-center px-3 transition-all"
                             style="width:<?= max(8, $f['pct']) ?>%">
                            <span class="text-white text-xs font-medium whitespace-nowrap">
                                <?= number_format($f['value']) ?>
                            </span>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-gray-600 w-10 text-right"><?= $f['pct'] ?>%</span>
                </div>
                <?php endforeach; ?>

                <?php if ($stats['failed'] > 0): ?>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 w-16 flex-shrink-0">Failed</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-7 relative overflow-hidden">
                        <div class="bg-red-400 h-7 rounded-full flex items-center px-3"
                             style="width:<?= max(8, $failRate) ?>%">
                            <span class="text-white text-xs font-medium"><?= number_format($stats['failed']) ?></span>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-red-500 w-10 text-right"><?= $failRate ?>%</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recipients Table -->
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-700">Recipients</h3>
                <form method="GET" class="flex items-center gap-2">
                    <select name="status" onchange="this.form.submit()"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none">
                        <?php foreach (['all' => 'All Status', 'pending' => 'Pending', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $statusFilter === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php if (empty($recipients)): ?>
            <div class="p-8 text-center text-gray-400 text-sm">
                <?= $broadcast['status'] === 'draft' ? 'No recipients yet — send the broadcast to start.' : 'No recipients found.' ?>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr class="text-left text-xs text-gray-500 font-medium">
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sent At</th>
                            <th class="px-4 py-3">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php
                        $rBg = ['pending' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-100 text-blue-700', 'delivered' => 'bg-indigo-100 text-indigo-700', 'read' => 'bg-green-100 text-green-700', 'replied' => 'bg-purple-100 text-purple-700', 'failed' => 'bg-red-100 text-red-600'];
                        foreach ($recipients as $r):
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-800"><?= esc($r['contact_name'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= esc($r['contact_phone'] ?? '—') ?></td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $rBg[$r['status']] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucfirst($r['status']) ?></span></td>
                            <td class="px-4 py-3 text-gray-400 text-xs"><?= $r['sent_at'] ? date('d M, g:i A', strtotime($r['sent_at'])) : '—' ?></td>
                            <td class="px-4 py-3 text-red-400 text-xs max-w-xs truncate"><?= esc($r['error_message'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalRec > $perPage): ?>
            <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                <span>Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalRec) ?> of <?= $totalRec ?></span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>" class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-50">← Prev</a>
                    <?php endif; ?>
                    <?php if ($page * $perPage < $totalRec): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>" class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-50">Next →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
