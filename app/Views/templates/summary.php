<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$qualityColors = [
    'high'    => 'bg-green-100 text-green-700',
    'medium'  => 'bg-amber-100 text-amber-700',
    'low'     => 'bg-red-100 text-red-700',
    'unknown' => 'bg-gray-100 text-gray-500',
];
$statusBg = [
    'draft'    => 'bg-gray-100 text-gray-600',
    'pending'  => 'bg-yellow-100 text-yellow-700',
    'approved' => 'bg-green-100 text-green-700',
    'rejected' => 'bg-red-100 text-red-700',
];
$broadcastStatus = [
    'draft'      => ['bg-gray-100 text-gray-600',   'Draft'],
    'scheduled'  => ['bg-blue-100 text-blue-700',   'Scheduled'],
    'sending'    => ['bg-yellow-100 text-yellow-700','Sending'],
    'sent'       => ['bg-green-100 text-green-700',  'Sent'],
    'cancelled'  => ['bg-red-100 text-red-600',     'Cancelled'],
    'failed'     => ['bg-red-100 text-red-700',     'Failed'],
];
$quality = strtolower($template['quality_score'] ?? 'unknown');
$qc      = $qualityColors[$quality] ?? $qualityColors['unknown'];
$sc      = $statusBg[$template['status']] ?? 'bg-gray-100 text-gray-600';

$deliveryRate = $totals['sent'] > 0 ? round($totals['delivered'] / $totals['sent'] * 100, 1) : 0;
$readRate     = $totals['delivered'] > 0 ? round($totals['read'] / $totals['delivered'] * 100, 1) : 0;
$replyRate    = $totals['delivered'] > 0 ? round($totals['replied'] / $totals['delivered'] * 100, 1) : 0;
$failRate     = $totals['sent'] > 0 ? round($totals['failed'] / $totals['sent'] * 100, 1) : 0;
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-4">
    <a href="<?= base_url('templates') ?>" class="hover:text-blue-600">Templates</a>
    <span>›</span>
    <a href="<?= base_url('templates/' . $template['id']) ?>" class="hover:text-blue-600 font-mono"><?= esc($template['name']) ?></a>
    <span>›</span>
    <span class="text-gray-600 font-medium">Summary</span>
</div>

<!-- Template info bar -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6 flex flex-wrap items-start gap-4">
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-lg font-bold text-gray-900 font-mono"><?= esc($template['name']) ?></h1>
            <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sc ?>"><?= ucfirst($template['status']) ?></span>
            <?php if ($quality !== 'unknown'): ?>
            <span class="text-xs px-2 py-0.5 rounded-full font-medium inline-flex items-center gap-1 <?= $qc ?>"><?= rx_icon('star', 'w-3.5 h-3.5') ?> <?= ucfirst($quality) ?> Quality</span>
            <?php endif; ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-mono uppercase"><?= esc($template['language']) ?></span>
        </div>
        <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?= esc($template['body_text']) ?></p>
    </div>
    <div class="flex gap-2 flex-shrink-0">
        <a href="<?= base_url('broadcasts/create?template=' . urlencode($template['name'])) ?>"
           class="px-3 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
            + New Campaign
        </a>
        <a href="<?= base_url('templates/' . $template['id']) ?>"
           class="px-3 py-2 border border-gray-200 text-gray-600 text-sm rounded-lg hover:bg-gray-50 font-medium">
            View Template
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?= $totals['campaigns'] ?></div>
        <div class="text-xs text-gray-500 mt-1">Campaigns</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold text-blue-700"><?= number_format($totals['sent']) ?></div>
        <div class="text-xs text-gray-500 mt-1">Total Sent</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold text-green-700"><?= number_format($totals['delivered']) ?></div>
        <div class="text-xs text-gray-500 mt-1">Delivered</div>
        <div class="text-xs text-green-600 font-semibold mt-0.5"><?= $deliveryRate ?>%</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold text-blue-600"><?= number_format($totals['read']) ?></div>
        <div class="text-xs text-gray-500 mt-1">Read</div>
        <div class="text-xs text-blue-600 font-semibold mt-0.5"><?= $readRate ?>%</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold text-purple-700"><?= number_format($totals['replied']) ?></div>
        <div class="text-xs text-gray-500 mt-1">Replied</div>
        <div class="text-xs text-purple-600 font-semibold mt-0.5"><?= $replyRate ?>%</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold text-red-600"><?= number_format($totals['failed']) ?></div>
        <div class="text-xs text-gray-500 mt-1">Failed</div>
        <div class="text-xs text-red-500 font-semibold mt-0.5"><?= $failRate ?>%</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center col-span-2 md:col-span-1">
        <div class="text-2xl font-bold text-gray-700"><?= $readRate ?>%</div>
        <div class="text-xs text-gray-500 mt-1">Read Rate</div>
        <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2">
            <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?= min($readRate, 100) ?>%"></div>
        </div>
    </div>
</div>

<!-- Campaigns table -->
<div class="bg-white rounded-xl border border-gray-200">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-900">Campaigns using this template</h2>
        <span class="text-sm text-gray-400"><?= count($campaigns) ?> campaign<?= count($campaigns) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($campaigns)): ?>
    <div class="p-12 text-center">
        <div class="mb-3"><?= rx_icon('megaphone', 'w-12 h-12', 'mx-auto') ?></div>
        <p class="text-gray-500 mb-1">No campaigns have used this template yet.</p>
        <p class="text-sm text-gray-400 mb-4">Create a broadcast campaign to start sending this template.</p>
        <a href="<?= base_url('broadcasts/create') ?>"
           class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800">
            Create Campaign
        </a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Campaign</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Sent</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Delivered</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Read</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Replied</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Failed</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Read Rate</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($campaigns as $c):
                    [$bsc, $bsl] = $broadcastStatus[$c['status']] ?? ['bg-gray-100 text-gray-600', ucfirst($c['status'])];
                    $cRead  = ($c['delivered_count'] > 0) ? round($c['read_count'] / $c['delivered_count'] * 100, 1) : 0;
                    $cFail  = ($c['sent_count'] > 0) ? round($c['failed_count'] / $c['sent_count'] * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900"><?= esc($c['name']) ?></div>
                        <?php if ($c['scheduled_at']): ?>
                        <div class="text-xs text-gray-400">Scheduled <?= date('d M Y H:i', strtotime($c['scheduled_at'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-1 rounded-full font-medium <?= $bsc ?>"><?= $bsl ?></span>
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-gray-700"><?= number_format($c['sent_count']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-green-700"><?= number_format($c['delivered_count']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-blue-700"><?= number_format($c['read_count']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-purple-700"><?= number_format($c['replied_count']) ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $cFail > 5 ? 'text-red-600 font-semibold' : 'text-gray-400' ?>">
                        <?= number_format($c['failed_count']) ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?= min($cRead, 100) ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-600 font-medium w-9 text-right"><?= $cRead ?>%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                        <?= $c['created_at'] ? date('d M Y', strtotime($c['created_at'])) : '—' ?>
                    </td>
                    <td class="px-4 py-3">
                        <a href="<?= base_url('broadcasts/' . $c['id']) ?>"
                           class="text-xs text-blue-600 hover:underline whitespace-nowrap">View →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Table totals row -->
    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex flex-wrap gap-4 text-sm font-medium text-gray-700">
        <span>Totals:</span>
        <span class="text-blue-700"><?= number_format($totals['sent']) ?> sent</span>
        <span class="text-green-700"><?= number_format($totals['delivered']) ?> delivered (<?= $deliveryRate ?>%)</span>
        <span class="text-blue-600"><?= number_format($totals['read']) ?> read (<?= $readRate ?>%)</span>
        <span class="text-purple-700"><?= number_format($totals['replied']) ?> replied</span>
        <span class="text-red-600"><?= number_format($totals['failed']) ?> failed</span>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
