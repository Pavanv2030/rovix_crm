<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Activity Log</h1>
        <p class="text-sm text-gray-500 mt-0.5">All team actions, sorted newest first</p>
    </div>
    <a href="<?= base_url('team') ?>"
       class="px-3 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-700">
        ← Back to Team
    </a>
</div>

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <?php if (empty($activities)): ?>
    <div class="p-12 text-center">
        <div class="mb-2"><?= rx_icon('clipboard', 'w-10 h-10', 'mx-auto') ?></div>
        <p class="text-sm text-gray-500">No activity recorded yet.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Time</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">User</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Action</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Details</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($activities as $a):
                    $meta = $a['metadata'] ? json_decode($a['metadata'], true) : [];
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap">
                        <?= date('M j, Y', strtotime($a['created_at'])) ?>
                        <div class="text-gray-400"><?= date('g:i A', strtotime($a['created_at'])) ?></div>
                    </td>
                    <td class="px-5 py-4">
                        <div class="font-medium text-gray-900 text-xs"><?= esc($a['user_name'] ?? '—') ?></div>
                        <div class="text-gray-400 text-xs"><?= esc($a['user_email'] ?? '') ?></div>
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-block px-2 py-0.5 bg-blue-50 text-blue-700 text-xs rounded-full font-mono">
                            <?= esc($a['action']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-4 max-w-xs">
                        <?php if ($a['entity_type']): ?>
                        <div class="text-xs text-gray-600 mb-0.5">
                            <?= ucfirst(esc($a['entity_type'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($meta): ?>
                        <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                            <?php foreach ($meta as $k => $v): ?>
                            <span class="text-xs text-gray-500">
                                <?= esc($k) ?>: <strong class="text-gray-700"><?= esc($v) ?></strong>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-xs text-gray-400 font-mono">
                        <?= esc($a['ip_address'] ?? '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pager && $pager->getPageCount() > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100">
        <?= $pager->links() ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
