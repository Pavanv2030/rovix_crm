<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Automations</h1>
        <p class="text-sm text-gray-500 mt-0.5">Auto-respond and act on WhatsApp events</p>
    </div>
    <a href="<?= base_url('automations/create') ?>"
       class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
        + New Automation
    </a>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>

<?php if (empty($automations)): ?>
<div class="bg-white border border-gray-200 rounded-xl p-12 text-center">
    <div class="mb-4"><?= rx_icon('lightning', 'w-12 h-12', 'mx-auto') ?></div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">No automations yet</h3>
    <p class="text-sm text-gray-500 mb-6">
        Automations run actions automatically when events happen — like a new message or a tag being added.
    </p>
    <a href="<?= base_url('automations/create') ?>"
       class="inline-block px-5 py-2.5 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
        Create your first automation
    </a>
</div>
<?php else: ?>

<?php
$triggerLabels = [
    'new_message_received'   => ['icon' => rx_icon('chat', 'w-4 h-4'), 'label' => 'New Message'],
    'first_inbound_message'  => ['icon' => rx_icon('wave', 'w-4 h-4'), 'label' => 'First Message'],
    'keyword_match'          => ['icon' => rx_icon('key', 'w-4 h-4'), 'label' => 'Keyword Match'],
    'new_contact_created'    => ['icon' => rx_icon('user', 'w-4 h-4'), 'label' => 'New Contact'],
    'conversation_assigned'  => ['icon' => rx_icon('user-raise', 'w-4 h-4'), 'label' => 'Conversation Assigned'],
    'tag_added'              => ['icon' => rx_icon('tag', 'w-4 h-4'), 'label' => 'Tag Added'],
    'time_based'             => ['icon' => rx_icon('clock', 'w-4 h-4'), 'label' => 'Time-Based'],
];
?>

<div class="space-y-3">
    <?php foreach ($automations as $auto): ?>
    <?php
        $tInfo = $triggerLabels[$auto['trigger_type']] ?? ['icon' => rx_icon('lightning', 'w-4 h-4'), 'label' => $auto['trigger_type']];
        $isActive = (bool)$auto['is_active'];
    ?>
    <div class="bg-white border border-gray-200 rounded-xl p-5 flex items-center gap-4 hover:border-blue-300 transition-colors">

        <!-- Toggle -->
        <form method="POST" action="<?= base_url('automations/' . $auto['id'] . '/toggle') ?>">
            <?= csrf_field() ?>
            <button type="submit" title="<?= $isActive ? 'Click to pause' : 'Click to activate' ?>"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors <?= $isActive ? 'bg-green-500' : 'bg-gray-300' ?>">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform <?= $isActive ? 'translate-x-6' : 'translate-x-1' ?>"></span>
            </button>
        </form>

        <!-- Icon + Name -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <a href="<?= base_url('automations/' . $auto['id']) ?>"
                   class="text-sm font-semibold text-gray-900 hover:text-blue-700 truncate">
                    <?= esc($auto['name']) ?>
                </a>
                <?php if (!$isActive): ?>
                <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">Paused</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1"><?= $tInfo['icon'] ?> <?= $tInfo['label'] ?></span>
                <span>·</span>
                <span><?= (int)$auto['step_count'] ?> step<?= $auto['step_count'] !== 1 ? 's' : '' ?></span>
                <span>·</span>
                <span><?= (int)$auto['execution_count'] ?> runs</span>
                <?php if ($auto['last_executed_at']): ?>
                <span>· Last ran <?= date('d M', strtotime($auto['last_executed_at'])) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="<?= base_url('automations/' . $auto['id'] . '/edit') ?>"
               class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:border-gray-400 text-gray-600">Edit</a>
            <a href="<?= base_url('automations/' . $auto['id']) ?>"
               class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:border-gray-400 text-gray-600">View</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
