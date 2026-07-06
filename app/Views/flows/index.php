<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Flows</h1>
        <p class="text-sm text-gray-500 mt-0.5">Visual chatbot decision trees triggered by keywords</p>
    </div>
    <a href="<?= base_url('flows/create') ?>"
       class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
        + New Flow
    </a>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>

<?php if (empty($flows)): ?>
<div class="bg-white border border-gray-200 rounded-xl p-12 text-center">
    <div class="mb-4"><?= rx_icon('branch', 'w-12 h-12', 'mx-auto') ?></div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">No flows yet</h3>
    <p class="text-sm text-gray-500 mb-6">
        Flows are interactive chatbot conversations — triggered by a keyword and driven by a visual decision tree.
    </p>
    <a href="<?= base_url('flows/create') ?>"
       class="inline-block px-5 py-2.5 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
        Build your first flow
    </a>
</div>
<?php else: ?>

<div class="space-y-3">
    <?php foreach ($flows as $flow): ?>
    <?php
        $isActive = (bool)$flow['is_active'];
        $keywords = json_decode($flow['trigger_keywords'] ?? '[]', true) ?? [];
    ?>
    <div class="bg-white border border-gray-200 rounded-xl p-5 flex items-center gap-4 hover:border-blue-300 transition-colors">

        <!-- Toggle -->
        <form method="POST" action="<?= base_url('flows/' . $flow['id'] . '/toggle') ?>">
            <?= csrf_field() ?>
            <button type="submit" title="<?= $isActive ? 'Click to pause' : 'Click to activate' ?>"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors <?= $isActive ? 'bg-green-500' : 'bg-gray-300' ?>">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform <?= $isActive ? 'translate-x-6' : 'translate-x-1' ?>"></span>
            </button>
        </form>

        <!-- Icon -->
        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
             style="background: #EFF6FF;"><?= rx_icon('branch', 'w-5 h-5') ?></div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
            <a href="<?= base_url('flows/' . $flow['id']) ?>"
               class="font-semibold text-gray-900 hover:text-blue-600 block truncate">
                <?= esc($flow['name']) ?>
            </a>
            <div class="flex items-center gap-3 mt-1">
                <span class="text-xs text-gray-500">
                    Keywords:
                    <?php foreach (array_slice($keywords, 0, 4) as $kw): ?>
                    <span class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full mr-1"><?= esc($kw) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($keywords) > 4): ?>
                    <span class="text-gray-400">+<?= count($keywords) - 4 ?> more</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="text-center px-4 border-l border-gray-100">
            <div class="text-lg font-bold text-gray-800"><?= number_format($flow['execution_count'] ?? 0) ?></div>
            <div class="text-xs text-gray-400">runs</div>
        </div>
        <div class="text-center px-4 border-l border-gray-100">
            <div class="text-lg font-bold text-gray-800"><?= number_format($flow['run_count'] ?? 0) ?></div>
            <div class="text-xs text-gray-400">active</div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 pl-4 border-l border-gray-100">
            <a href="<?= base_url('flows/' . $flow['id'] . '/test') ?>"
               class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                ▶ Test
            </a>
            <a href="<?= base_url('flows/' . $flow['id'] . '/edit') ?>"
               class="px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700 font-medium">
                Edit
            </a>
            <a href="<?= base_url('flows/' . $flow['id']) ?>"
               class="px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700 font-medium">
                View
            </a>
            <form method="POST" action="<?= base_url('flows/' . $flow['id'] . '/duplicate') ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700 font-medium">
                    Copy
                </button>
            </form>
            <form method="POST" action="<?= base_url('flows/' . $flow['id'] . '/delete') ?>" class="inline"
                  onsubmit="return confirm('Delete this flow? This cannot be undone.')">
                <?= csrf_field() ?>
                <button type="submit" class="px-3 py-1.5 text-xs bg-white border border-red-200 rounded-lg hover:bg-red-50 text-red-600 font-medium">
                    Delete
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
