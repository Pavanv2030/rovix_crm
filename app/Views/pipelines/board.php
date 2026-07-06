<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<meta name="csrf-name" content="<?= csrf_token() ?>">

<!-- Header -->
<div class="flex items-center justify-between mb-4 flex-shrink-0">
    <div class="flex items-center gap-2">
        <a href="<?= base_url('pipelines') ?>" class="text-gray-400 hover:text-gray-600 text-sm">Pipelines</a>
        <span class="text-gray-300">›</span>
        <h1 class="text-lg font-bold text-gray-900"><?= esc($pipeline['name']) ?></h1>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= base_url('deals/create?pipeline_id=' . $pipeline['id']) ?>"
           class="px-4 py-2 text-sm bg-blue-900 text-white rounded-lg hover:bg-blue-800 font-medium">
            + New Deal
        </a>
        <?php if (has_min_role('admin')): ?>
        <a href="<?= base_url('pipelines/' . $pipeline['id'] . '/edit') ?>"
           class="px-3 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Edit Pipeline</a>
        <?php endif; ?>
    </div>
</div>

<!-- Kanban Board -->
<div class="flex gap-4 overflow-x-auto pb-4 -mx-6 px-6" style="min-height: calc(100vh - 180px)">
    <?php foreach ($stages as $stage): ?>
    <?php
    $stageDeals  = $dealsByStage[$stage['id']] ?? [];
    $totalValue  = array_sum(array_column($stageDeals, 'value'));
    $today       = date('Y-m-d');
    $weekFromNow = date('Y-m-d', strtotime('+7 days'));
    ?>

    <div class="flex-shrink-0 w-72 bg-gray-50 rounded-xl border border-gray-200 flex flex-col">
        <!-- Column Header -->
        <div class="p-4 border-b border-gray-200" style="border-left: 4px solid <?= esc($stage['color']) ?>; border-radius: 0.75rem 0.75rem 0 0;">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-sm"><?= esc($stage['name']) ?></h3>
                <span class="text-xs bg-white border border-gray-200 text-gray-500 rounded-full px-2 py-0.5"><?= count($stageDeals) ?></span>
            </div>
            <?php if ($totalValue > 0): ?>
            <p class="text-xs text-gray-500 mt-0.5">₹<?= number_format($totalValue) ?></p>
            <?php endif; ?>
        </div>

        <!-- Deal Cards (Sortable) -->
        <div class="kanban-column flex-1 p-3 space-y-2 overflow-y-auto"
             data-stage-id="<?= esc($stage['id']) ?>"
             style="max-height: calc(100vh - 280px)">

            <?php foreach ($stageDeals as $deal): ?>
            <?php
            $isOverdue   = $deal['expected_close_date'] && $deal['expected_close_date'] < $today;
            $isThisWeek  = $deal['expected_close_date'] && $deal['expected_close_date'] >= $today && $deal['expected_close_date'] <= $weekFromNow;
            ?>
            <div class="deal-card bg-white rounded-lg border border-gray-200 p-3 cursor-move hover:shadow-md transition-shadow"
                 data-deal-id="<?= esc($deal['id']) ?>">
                <a href="<?= base_url('deals/' . $deal['id']) ?>" class="block" onclick="return false">
                    <h4 class="font-semibold text-gray-900 text-sm mb-1"><?= esc($deal['title']) ?></h4>

                    <?php if ($deal['contact_name'] || $deal['contact_phone']): ?>
                    <p class="text-xs text-gray-500 mb-2"><?= esc($deal['contact_name'] ?? $deal['contact_phone']) ?></p>
                    <?php endif; ?>

                    <p class="text-base font-bold text-green-600 mb-1">₹<?= number_format($deal['value']) ?></p>

                    <?php if ($deal['expected_close_date']): ?>
                    <p class="text-xs <?= $isOverdue ? 'text-red-500 font-medium' : ($isThisWeek ? 'text-yellow-600' : 'text-gray-400') ?>">
                        <?= $isOverdue ? rx_icon('warning', 'w-3 h-3') . ' ' : '' ?>Close: <?= date('d M', strtotime($deal['expected_close_date'])) ?>
                    </p>
                    <?php endif; ?>

                    <?php if ($deal['agent_name']): ?>
                    <div class="mt-2 flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded-full bg-blue-900 text-white text-xs flex items-center justify-center flex-shrink-0">
                            <?= strtoupper(substr($deal['agent_name'], 0, 1)) ?>
                        </div>
                        <span class="text-xs text-gray-400"><?= esc($deal['agent_name']) ?></span>
                    </div>
                    <?php endif; ?>
                </a>
                <a href="<?= base_url('deals/' . $deal['id']) ?>"
                   class="mt-2 block text-center text-xs text-blue-600 hover:text-blue-800 py-1 border-t border-gray-100">
                    View →
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add Deal button -->
        <div class="p-3 border-t border-gray-200">
            <a href="<?= base_url('deals/create?pipeline_id=' . $pipeline['id'] . '&stage_id=' . $stage['id']) ?>"
               class="block text-center text-sm text-blue-600 hover:text-blue-800 py-1.5 hover:bg-blue-50 rounded-lg transition-colors">
                + Add Deal
            </a>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($stages)): ?>
    <div class="flex-1 flex items-center justify-center text-gray-400">
        <div class="text-center">
            <p class="text-lg mb-2">No stages yet</p>
            <a href="<?= base_url('pipelines/' . $pipeline['id'] . '/edit') ?>" class="text-blue-600 hover:underline text-sm">Edit pipeline to add stages</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const csrfName  = document.querySelector('meta[name=csrf-name]').content;
    const csrfToken = document.querySelector('meta[name=csrf-token]').content;

    document.querySelectorAll('.kanban-column').forEach(column => {
        new Sortable(column, {
            group: 'deals',
            animation: 150,
            ghostClass: 'opacity-40',
            dragClass: 'shadow-xl',
            onEnd: function (evt) {
                const dealId    = evt.item.dataset.dealId;
                const newStageId = evt.to.dataset.stageId;
                if (!dealId || !newStageId) return;

                fetch('<?= base_url('api/deals') ?>/' + dealId + '/move', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ stage_id: newStageId, [csrfName]: csrfToken })
                }).catch(e => console.error('Move failed:', e));
            }
        });
    });

    // Make deal cards clickable (not draggable on View link)
    document.querySelectorAll('.deal-card a[href]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
})();
</script>

<?= $this->endSection() ?>
