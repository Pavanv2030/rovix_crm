<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div x-data="{ activeTab: 'broadcasts' }">

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Reports</h1>
    </div>
</div>

<!-- Report Sub-nav -->
<div class="flex gap-1 mb-6 border-b border-gray-200">
    <a href="<?= base_url('reports/sending-history') ?>"
       class="px-4 py-2.5 text-sm font-medium text-blue-700 border-b-2 border-blue-700 -mb-px">
        Sending History
    </a>
    <a href="<?= base_url('reports/scheduled-log') ?>"
       class="px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent -mb-px">
        Scheduled Log
    </a>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-lg w-fit">
    <button @click="activeTab = 'broadcasts'"
            :class="activeTab === 'broadcasts' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-1.5 text-sm rounded-md font-medium transition-all">
        Broadcasts
        <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full text-white bg-green-500"><?= count($broadcasts) ?></span>
    </button>
    <button @click="activeTab = 'individual'"
            :class="activeTab === 'individual' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-1.5 text-sm rounded-md font-medium transition-all">
        Individual
        <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full text-white bg-blue-500"><?= count($messages) ?></span>
    </button>
</div>

<!-- Broadcasts Tab -->
<div x-show="activeTab === 'broadcasts'">
<?php if (empty($broadcasts)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
    <div class="mb-3"><?= rx_icon('send', 'w-12 h-12', 'mx-auto') ?></div>
    <p class="text-gray-500">No sent broadcasts yet.</p>
    <a href="<?= base_url('broadcasts') ?>" class="mt-3 inline-block text-sm text-blue-600 hover:underline">Go to Broadcasts →</a>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-teal-600 text-white text-xs uppercase">
                <th class="px-4 py-3 text-left font-semibold">Campaign Name</th>
                <th class="px-4 py-3 text-left font-semibold">Template</th>
                <th class="px-4 py-3 text-center font-semibold">Total</th>
                <th class="px-4 py-3 text-center font-semibold">Sent</th>
                <th class="px-4 py-3 text-center font-semibold">Delivered</th>
                <th class="px-4 py-3 text-center font-semibold">Read</th>
                <th class="px-4 py-3 text-center font-semibold">Failed</th>
                <th class="px-4 py-3 text-left font-semibold">Sent At</th>
                <th class="px-4 py-3 text-center font-semibold">Status</th>
                <th class="px-4 py-3 text-center font-semibold">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($broadcasts as $b):
            $statusBg = ['sending' => 'bg-yellow-100 text-yellow-700', 'sent' => 'bg-green-100 text-green-700'];
        ?>
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900 max-w-xs truncate"><?= esc($b['name']) ?></td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600"><?= esc($b['template_name']) ?></td>
            <td class="px-4 py-3 text-center text-gray-700"><?= (int) $b['total_recipients'] ?></td>
            <td class="px-4 py-3 text-center text-gray-700"><?= (int) $b['sent_count'] ?></td>
            <td class="px-4 py-3 text-center text-blue-600"><?= (int) $b['delivered_count'] ?></td>
            <td class="px-4 py-3 text-center text-green-600"><?= (int) $b['read_count'] ?></td>
            <td class="px-4 py-3 text-center text-red-500"><?= (int) $b['failed_count'] ?></td>
            <td class="px-4 py-3 text-gray-500 text-xs"><?= $b['sent_at'] ? date('d M Y, g:i A', strtotime($b['sent_at'])) : '—' ?></td>
            <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $statusBg[$b['status']] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucfirst($b['status']) ?></span>
            </td>
            <td class="px-4 py-3 text-center">
                <a href="<?= base_url('broadcasts/' . $b['id']) ?>" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-400">
        Showing <?= count($broadcasts) ?> records (last 200)
    </div>
</div>
<?php endif; ?>
</div>

<!-- Individual Messages Tab -->
<div x-show="activeTab === 'individual'" x-cloak>
<?php if (empty($messages)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
    <div class="mb-3"><?= rx_icon('chat', 'w-12 h-12', 'mx-auto') ?></div>
    <p class="text-gray-500">No individual messages sent yet.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-teal-600 text-white text-xs uppercase">
                <th class="px-4 py-3 text-left font-semibold">To</th>
                <th class="px-4 py-3 text-left font-semibold">Message</th>
                <th class="px-4 py-3 text-left font-semibold">Template</th>
                <th class="px-4 py-3 text-left font-semibold">Type</th>
                <th class="px-4 py-3 text-left font-semibold">Sent At</th>
                <th class="px-4 py-3 text-center font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php
        $statusBadge = [
            'sent'      => 'bg-blue-100 text-blue-700',
            'delivered' => 'bg-teal-100 text-teal-700',
            'read'      => 'bg-green-100 text-green-700',
            'failed'    => 'bg-red-100 text-red-600',
        ];
        foreach ($messages as $m):
            $display = !empty($m['contact_name']) ? $m['contact_name'] . ' (' . $m['phone_number'] . ')' : $m['phone_number'];
            $preview = mb_substr(strip_tags($m['content_text'] ?? ''), 0, 60);
            if (mb_strlen($m['content_text'] ?? '') > 60) $preview .= '…';
        ?>
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-800 text-xs"><?= esc($display) ?></td>
            <td class="px-4 py-3 text-gray-600 text-xs max-w-xs"><?= esc($preview ?: '(' . $m['content_type'] . ')') ?></td>
            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?= esc($m['template_name'] ?? '—') ?></td>
            <td class="px-4 py-3 text-xs">
                <span class="px-1.5 py-0.5 rounded text-xs font-medium <?= $m['sender_type'] === 'bot' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600' ?>">
                    <?= $m['sender_type'] === 'bot' ? 'Bot' : 'Agent' ?>
                </span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d M Y, g:i A', strtotime($m['created_at'])) ?></td>
            <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $statusBadge[$m['status']] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucfirst($m['status'] ?? 'sent') ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-400">
        Showing <?= count($messages) ?> records (last 500)
    </div>
</div>
<?php endif; ?>
</div>

</div>
<?= $this->endSection() ?>
