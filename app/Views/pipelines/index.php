<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Pipelines</h1>
    <a href="<?= base_url('pipelines/create') ?>"
       class="px-4 py-2 text-sm bg-blue-900 text-white rounded-lg hover:bg-blue-800 font-medium">
        + New Pipeline
    </a>
</div>

<?php if (empty($pipelines)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
    <div class="mb-4"><?= rx_icon('chart', 'w-12 h-12', 'mx-auto') ?></div>
    <h3 class="text-lg font-semibold text-gray-700 mb-1">No pipelines yet</h3>
    <p class="text-sm text-gray-400 mb-4">Create your first pipeline to start managing deals</p>
    <a href="<?= base_url('pipelines/create') ?>" class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800">
        Create Pipeline
    </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($pipelines as $pipeline): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-3">
            <h3 class="font-semibold text-gray-900 text-lg"><?= esc($pipeline['name']) ?></h3>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="text-gray-400 hover:text-gray-600 p-1">⋮</button>
                <div x-show="open" @click.away="open = false" x-cloak
                     class="absolute right-0 mt-1 w-36 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-20">
                    <a href="<?= base_url('pipelines/' . $pipeline['id'] . '/edit') ?>"
                       class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit</a>
                    <form action="<?= base_url('pipelines/' . $pipeline['id'] . '/delete') ?>" method="POST"
                          onsubmit="return confirm('Delete this pipeline?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="block w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50">Delete</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stage dots -->
        <div class="flex gap-1.5 mb-4">
            <?php foreach (array_slice($pipeline['stages'], 0, 6) as $stage): ?>
            <div class="w-3 h-3 rounded-full" style="background-color: <?= esc($stage['color']) ?>"
                 title="<?= esc($stage['name']) ?>"></div>
            <?php endforeach; ?>
            <?php if (count($pipeline['stages']) > 6): ?>
            <span class="text-xs text-gray-400">+<?= count($pipeline['stages']) - 6 ?></span>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="flex gap-4 text-sm text-gray-500 mb-4">
            <span><?= count($pipeline['stages']) ?> stages</span>
            <span><?= $pipeline['deal_count'] ?> deals</span>
            <?php if ($pipeline['deal_value'] > 0): ?>
            <span class="text-green-600 font-medium">₹<?= number_format($pipeline['deal_value']) ?></span>
            <?php endif; ?>
        </div>

        <a href="<?= base_url('pipelines/' . $pipeline['id'] . '/board') ?>"
           class="block w-full text-center py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 transition-colors">
            Open Board
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
