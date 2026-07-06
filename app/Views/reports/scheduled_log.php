<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Reports</h1>
    </div>
    <a href="<?= base_url('broadcasts') ?>" class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
        + New Broadcast
    </a>
</div>

<!-- Report Sub-nav -->
<div class="flex gap-1 mb-6 border-b border-gray-200">
    <a href="<?= base_url('reports/sending-history') ?>"
       class="px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent -mb-px">
        Sending History
    </a>
    <a href="<?= base_url('reports/scheduled-log') ?>"
       class="px-4 py-2.5 text-sm font-medium text-blue-700 border-b-2 border-blue-700 -mb-px">
        Scheduled Log
    </a>
</div>

<?php if (session('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= esc(session('success')) ?></div>
<?php endif; ?>

<?php if (empty($scheduled)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
    <div class="mb-3"><?= rx_icon('clock', 'w-12 h-12', 'mx-auto') ?></div>
    <p class="text-gray-500 mb-2">No scheduled campaigns.</p>
    <p class="text-xs text-gray-400">Use "Schedule" in Broadcasts or Quick Campaign to queue a send.</p>
    <a href="<?= base_url('broadcasts') ?>" class="mt-4 inline-block text-sm text-blue-600 hover:underline">Go to Broadcasts →</a>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-teal-600 text-white text-xs uppercase">
                <th class="px-4 py-3 text-left font-semibold">Campaign Name</th>
                <th class="px-4 py-3 text-left font-semibold">Template</th>
                <th class="px-4 py-3 text-center font-semibold">Recipients</th>
                <th class="px-4 py-3 text-left font-semibold">Scheduled Date</th>
                <th class="px-4 py-3 text-center font-semibold">Status</th>
                <th class="px-4 py-3 text-center font-semibold">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($scheduled as $b):
            $isPast = strtotime($b['scheduled_at']) < time();
        ?>
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900"><?= esc($b['name']) ?></td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600"><?= esc($b['template_name']) ?></td>
            <td class="px-4 py-3 text-center text-gray-700"><?= (int) $b['total_recipients'] ?></td>
            <td class="px-4 py-3 text-xs <?= $isPast ? 'text-red-500 font-medium' : 'text-gray-700' ?>">
                <?= date('d M Y, g:i A', strtotime($b['scheduled_at'])) ?>
                <?php if ($isPast): ?><span class="ml-1 text-red-400">(overdue)</span><?php endif; ?>
            </td>
            <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-700">Scheduled</span>
            </td>
            <td class="px-4 py-3 text-center">
                <div class="flex gap-1 justify-center">
                    <a href="<?= base_url('broadcasts/' . $b['id']) ?>" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded">View</a>
                    <form action="<?= base_url('broadcasts/' . $b['id'] . '/cancel') ?>" method="POST" class="inline"
                          onsubmit="return confirm('Cancel this scheduled broadcast?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="text-xs px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded">Cancel</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-400">
        <?= count($scheduled) ?> scheduled campaign<?= count($scheduled) !== 1 ? 's' : '' ?>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
