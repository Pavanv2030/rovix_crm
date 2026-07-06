<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$statusBg   = ['draft' => 'bg-gray-100 text-gray-700', 'pending' => 'bg-yellow-100 text-yellow-700', 'approved' => 'bg-green-100 text-green-700', 'rejected' => 'bg-red-100 text-red-700'];
$buttons    = json_decode($template['buttons'] ?? '[]', true) ?? [];
$samples    = json_decode($template['sample_values'] ?? '{}', true) ?? [];
$sampleBody = $samples['body'] ?? [];

$previewBody = $template['body_text'];
foreach ($sampleBody as $i => $val) {
    $previewBody = str_replace('{{' . ($i + 1) . '}}', '<strong>' . esc($val) . '</strong>', $previewBody);
}
?>

<div class="flex items-center gap-2 mb-5 text-sm">
    <a href="<?= base_url('templates') ?>" class="text-gray-400 hover:text-gray-600">← Templates</a>
    <span class="text-gray-300">/</span>
    <span class="text-gray-700 font-medium font-mono"><?= esc($template['name']) ?></span>
</div>

<?php if (session('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= esc(session('success')) ?></div>
<?php endif; ?>
<?php if (session('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= esc(session('error')) ?></div>
<?php endif; ?>

<div class="flex flex-col lg:flex-row gap-6">

    <!-- Left Panel -->
    <div class="w-full lg:w-72 flex-shrink-0 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-4">
                <h2 class="font-bold text-gray-900 font-mono text-sm"><?= esc($template['name']) ?></h2>
                <span class="text-xs px-2 py-1 rounded-full font-medium <?= $statusBg[$template['status']] ?? 'bg-gray-100 text-gray-700' ?>">
                    <?= ucfirst($template['status']) ?>
                </span>
            </div>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-400">Category</span><span class="text-gray-700 capitalize"><?= esc($template['category']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-400">Language</span><span class="text-gray-700 uppercase font-mono"><?= esc($template['language']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-400">Created</span><span class="text-gray-700"><?= date('d M Y', strtotime($template['created_at'])) ?></span></div>
                <?php if ($template['meta_template_id']): ?>
                <div class="flex justify-between"><span class="text-gray-400">Meta ID</span><span class="text-gray-500 font-mono text-xs"><?= esc($template['meta_template_id']) ?></span></div>
                <?php endif; ?>
                <?php if ($template['quality_score']): ?>
                <div class="flex justify-between"><span class="text-gray-400">Quality</span>
                    <span class="<?= $template['quality_score'] === 'HIGH' ? 'text-green-600' : ($template['quality_score'] === 'LOW' ? 'text-red-600' : 'text-yellow-600') ?> font-medium text-xs">
                        <?= esc($template['quality_score']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($template['status'] === 'rejected'): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-red-700 mb-1">Rejection Reason</p>
            <p class="text-xs text-red-600"><?= esc($template['quality_score'] ?? 'Template did not meet Meta guidelines. Please review and edit before resubmitting.') ?></p>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="space-y-2">
            <?php if (in_array($template['status'], ['draft', 'rejected'])): ?>
            <form action="<?= base_url('templates/' . $template['id'] . '/submit') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="w-full py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg font-medium">
                    Submit for Approval
                </button>
            </form>
            <?php endif; ?>

            <?php if ($template['status'] === 'pending'): ?>
            <form action="<?= base_url('templates/' . $template['id'] . '/refresh') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="w-full py-2 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 text-sm rounded-lg font-medium">
                    ↻ Refresh Status
                </button>
            </form>
            <?php endif; ?>

            <?php if (in_array($template['status'], ['draft', 'rejected'])): ?>
            <a href="<?= base_url('templates/' . $template['id'] . '/edit') ?>"
               class="block text-center w-full py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                Edit Template
            </a>
            <?php endif; ?>

            <?php if (in_array($template['status'], ['draft', 'rejected']) && has_min_role('admin')): ?>
            <form action="<?= base_url('templates/' . $template['id'] . '/delete') ?>" method="POST"
                  onsubmit="return confirm('Delete this template permanently?')">
                <?= csrf_field() ?>
                <button type="submit" class="w-full py-2 text-red-600 text-sm hover:text-red-700">Delete Template</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Panel: Preview -->
    <div class="flex-1 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-700 mb-4">WhatsApp Preview</h3>
            <div class="bg-[#e5ddd5] rounded-2xl p-4">
                <div class="bg-white rounded-xl rounded-tl-none shadow-sm max-w-sm p-3 space-y-2">
                    <?php if ($template['header_type'] !== 'none' && $template['header_content']): ?>
                    <?php if ($template['header_type'] === 'text'): ?>
                    <p class="font-semibold text-gray-800 text-sm border-b pb-2"><?= esc($template['header_content']) ?></p>
                    <?php elseif ($template['header_type'] === 'image'): ?>
                    <img src="<?= esc(trim($template['header_content'])) ?>" alt="Header image"
                         class="w-full h-24 object-cover rounded-lg"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="bg-gray-200 rounded-lg h-24 items-center justify-center text-gray-400 text-xs uppercase" style="display:none">image header (failed to load)</div>
                    <?php else: ?>
                    <div class="bg-gray-200 rounded-lg h-24 flex items-center justify-center text-gray-400 text-xs uppercase"><?= esc($template['header_type']) ?> header</div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <p class="text-sm text-gray-800 leading-relaxed"><?= $previewBody ?></p>

                    <?php if ($template['footer_text']): ?>
                    <p class="text-xs text-gray-400"><?= esc($template['footer_text']) ?></p>
                    <?php endif; ?>
                    <p class="text-right text-xs text-gray-400">12:00 <?= rx_icon('check-double', 'w-3.5 h-3.5') ?></p>
                </div>
                <?php if ($buttons): ?>
                <div class="space-y-1 mt-2 max-w-sm">
                    <?php foreach ($buttons as $btn): ?>
                    <div class="bg-white rounded-xl text-center py-2 text-sm text-blue-600 font-medium shadow-sm"><?= esc($btn['text']) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($sampleBody)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-700 mb-3">Variable Mapping</h3>
            <table class="w-full text-sm">
                <thead><tr class="text-left border-b border-gray-100">
                    <th class="pb-2 text-xs text-gray-500 font-medium">Variable</th>
                    <th class="pb-2 text-xs text-gray-500 font-medium">Sample Value</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($sampleBody as $i => $val): ?>
                    <tr>
                        <td class="py-2 font-mono text-blue-600">{{<?= $i + 1 ?>}}</td>
                        <td class="py-2 text-gray-700"><?= esc($val) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
<?= $this->endSection() ?>
