<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$isActive = (bool)$automation['is_active'];
$triggerLabels = [
    'new_message_received'   => ['icon' => rx_icon('chat', 'w-5 h-5'), 'label' => 'New inbound WhatsApp message'],
    'first_inbound_message'  => ['icon' => rx_icon('wave', 'w-5 h-5'), 'label' => 'First message from a contact'],
    'keyword_match'          => ['icon' => rx_icon('key', 'w-5 h-5'), 'label' => 'Message matches keyword'],
    'new_contact_created'    => ['icon' => rx_icon('user', 'w-5 h-5'), 'label' => 'New contact is created'],
    'conversation_assigned'  => ['icon' => rx_icon('user-raise', 'w-5 h-5'), 'label' => 'Conversation is assigned'],
    'tag_added'              => ['icon' => rx_icon('tag', 'w-5 h-5'), 'label' => 'Tag is added to contact'],
    'time_based'             => ['icon' => rx_icon('clock', 'w-5 h-5'), 'label' => 'Scheduled (time-based)'],
];
$stepLabels = [
    'send_message'         => rx_icon('chat', 'w-4 h-4') . ' Send Text Message',
    'send_template'        => rx_icon('document', 'w-4 h-4') . ' Send Template',
    'send_appointment_flow' => rx_icon('calendar', 'w-4 h-4') . ' Send Appointment Booking',
    'send_catalog'          => rx_icon('cart', 'w-4 h-4') . ' Send Catalog',
    'add_tag'              => rx_icon('tag', 'w-4 h-4') . ' Add Tag',
    'remove_tag'           => rx_icon('trash', 'w-4 h-4') . ' Remove Tag',
    'assign_conversation'  => rx_icon('user-raise', 'w-4 h-4') . ' Assign Conversation',
    'update_contact_field' => rx_icon('pencil', 'w-4 h-4') . ' Update Contact Field',
    'create_deal'          => rx_icon('briefcase', 'w-4 h-4') . ' Create Deal',
    'wait'                 => rx_icon('clock', 'w-4 h-4') . ' Wait',
    'condition'            => rx_icon('branch', 'w-4 h-4') . ' Condition',
    'send_webhook'         => rx_icon('link', 'w-4 h-4') . ' Send Webhook',
    'close_conversation'   => rx_icon('check-circle', 'w-4 h-4') . ' Close Conversation',
];
$tInfo = $triggerLabels[$automation['trigger_type']] ?? ['icon' => rx_icon('lightning', 'w-5 h-5'), 'label' => $automation['trigger_type']];
$triggerConfig = json_decode($automation['trigger_config'] ?? '{}', true) ?? [];
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
            <a href="<?= base_url('automations') ?>" class="hover:text-gray-600">Automations</a>
            <span>/</span>
        </div>
        <h1 class="text-xl font-bold text-gray-900"><?= esc($automation['name']) ?></h1>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-medium <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-green-500' : 'bg-gray-400' ?>"></span>
            <?= $isActive ? 'Active' : 'Paused' ?>
        </span>
        <form method="POST" action="<?= base_url('automations/' . $automation['id'] . '/toggle') ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit"
                    class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:border-gray-400 text-gray-600">
                <?= $isActive ? 'Pause' : 'Activate' ?>
            </button>
        </form>
        <a href="<?= base_url('automations/' . $automation['id'] . '/edit') ?>"
           class="px-3 py-1.5 text-xs bg-blue-900 text-white rounded-lg hover:bg-blue-800">Edit</a>
        <form method="POST" action="<?= base_url('automations/' . $automation['id'] . '/delete') ?>"
              onsubmit="return confirm('Delete this automation? This cannot be undone.')">
            <?= csrf_field() ?>
            <button type="submit"
                    class="px-3 py-1.5 text-xs border border-red-200 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
        </form>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left: Trigger + Steps + Stats -->
    <div class="lg:col-span-1 space-y-4">

        <!-- Stats -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Stats</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900"><?= (int)$automation['execution_count'] ?></div>
                    <div class="text-xs text-gray-500 mt-0.5">Total runs</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900"><?= count($steps) ?></div>
                    <div class="text-xs text-gray-500 mt-0.5">Steps</div>
                </div>
            </div>
            <?php if ($automation['last_executed_at']): ?>
            <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                Last run: <?= date('d M Y, g:i A', strtotime($automation['last_executed_at'])) ?>
            </div>
            <?php endif; ?>
            <div class="mt-1 text-xs text-gray-400">
                Created: <?= date('d M Y', strtotime($automation['created_at'])) ?>
            </div>
        </div>

        <!-- Trigger -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Trigger</h2>
            <div class="flex items-start gap-2">
                <span><?= $tInfo['icon'] ?></span>
                <div>
                    <div class="text-sm font-medium text-gray-800"><?= $tInfo['label'] ?></div>
                    <?php if ($automation['trigger_type'] === 'keyword_match' && !empty($triggerConfig['keywords'])): ?>
                    <div class="text-xs text-gray-500 mt-1">
                        Keywords: <span class="font-mono"><?= esc($triggerConfig['keywords']) ?></span>
                        <?php if (!empty($triggerConfig['match_all'])): ?>
                        <span class="ml-1 text-blue-600">(match ALL)</span>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($automation['trigger_type'] === 'tag_added' && !empty($triggerConfig['tag_id'])): ?>
                    <div class="text-xs text-gray-500 mt-1">
                        Tag: <span class="font-medium"><?= esc($tags[$triggerConfig['tag_id']] ?? $triggerConfig['tag_id']) ?></span>
                    </div>
                    <?php elseif ($automation['trigger_type'] === 'time_based'): ?>
                    <div class="text-xs text-gray-500 mt-1">
                        <?= ucfirst($triggerConfig['schedule'] ?? 'daily') ?>
                        <?php if (($triggerConfig['schedule'] ?? '') === 'weekly'): ?>
                        on <?= ucfirst($triggerConfig['day'] ?? 'monday') ?>
                        <?php endif; ?>
                        at <?= $triggerConfig['time'] ?? '09:00' ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Steps -->
        <?php if (!empty($steps)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Steps</h2>
            <div class="space-y-2">
                <?php foreach ($steps as $i => $step): ?>
                <?php $config = json_decode($step['step_config'] ?? '{}', true) ?? []; ?>
                <div class="flex items-start gap-3">
                    <span class="text-xs text-gray-400 font-bold mt-0.5 w-4"><?= $i + 1 ?></span>
                    <div class="flex-1">
                        <div class="text-sm font-medium text-gray-700">
                            <?= $stepLabels[$step['step_type']] ?? $step['step_type'] ?>
                        </div>
                        <?php if ($step['step_type'] === 'send_message' && !empty($config['message'])): ?>
                        <div class="text-xs text-gray-400 mt-0.5 truncate"><?= esc(substr($config['message'], 0, 60)) ?><?= strlen($config['message']) > 60 ? '…' : '' ?></div>
                        <?php elseif ($step['step_type'] === 'add_tag' && !empty($config['tag_id'])): ?>
                        <div class="text-xs text-gray-400 mt-0.5"><?= esc($tags[$config['tag_id']] ?? $config['tag_id']) ?></div>
                        <?php elseif ($step['step_type'] === 'wait' && !empty($config['amount'])): ?>
                        <div class="text-xs text-gray-400 mt-0.5"><?= esc($config['amount']) ?> <?= esc($config['unit'] ?? 'hours') ?></div>
                        <?php elseif ($step['step_type'] === 'condition'): ?>
                        <div class="text-xs text-gray-400 mt-0.5"><?= esc($config['field'] ?? '') ?> <?= esc($config['operator'] ?? '') ?> <?= esc($config['value'] ?? '') ?></div>
                        <?php elseif ($step['step_type'] === 'send_webhook' && !empty($config['url'])): ?>
                        <div class="text-xs text-gray-400 mt-0.5 truncate"><?= esc($config['url']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($i < count($steps) - 1): ?>
                <div class="ml-4 border-l-2 border-dashed border-gray-200 h-3"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Execution Logs -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Execution Logs</h2>
                <p class="text-xs text-gray-400 mt-0.5">Last <?= $perPage ?> of <?= $totalLogs ?> runs</p>
            </div>

            <?php if (empty($logs)): ?>
            <div class="px-6 py-12 text-center text-sm text-gray-400">
                No executions yet. This automation will log here once it runs.
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($logs as $log): ?>
                <?php
                    $stepsExec = json_decode($log['steps_executed'] ?? '[]', true) ?? [];
                    $statusColors = [
                        'completed' => 'bg-green-100 text-green-700',
                        'failed'    => 'bg-red-100 text-red-700',
                        'running'   => 'bg-blue-100 text-blue-700',
                        'skipped'   => 'bg-gray-100 text-gray-600',
                    ];
                    $sc = $statusColors[$log['status']] ?? 'bg-gray-100 text-gray-600';
                ?>
                <div class="px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-800"><?= esc($log['contact_name']) ?></span>
                                <?php if ($log['contact_phone']): ?>
                                <span class="text-xs text-gray-400"><?= esc($log['contact_phone']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                <?= date('d M Y, g:i A', strtotime($log['created_at'])) ?>
                                · <?= count($stepsExec) ?> step<?= count($stepsExec) !== 1 ? 's' : '' ?> executed
                            </div>
                            <?php if (!empty($log['error_message'])): ?>
                            <div class="mt-1 text-xs text-red-600 font-mono"><?= esc($log['error_message']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($stepsExec)): ?>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <?php foreach ($stepsExec as $se): ?>
                                <?php
                                    $outcomeColors = [
                                        'done'   => 'bg-green-50 text-green-600',
                                        'queued' => 'bg-blue-50 text-blue-600',
                                        'wait'   => 'bg-yellow-50 text-yellow-600',
                                        'skipped_no_conversation' => 'bg-gray-50 text-gray-500',
                                    ];
                                    $oc = $outcomeColors[$se['outcome'] ?? ''] ?? 'bg-gray-50 text-gray-500';
                                ?>
                                <span class="text-xs px-2 py-0.5 rounded-full <?= $oc ?>">
                                    <?= $stepLabels[$se['type'] ?? ''] ?? $se['type'] ?>: <?= esc($se['outcome'] ?? '?') ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full font-medium flex-shrink-0 <?= $sc ?>">
                            <?= ucfirst($log['status']) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalLogs > $perPage): ?>
            <div class="px-6 py-4 border-t border-gray-100 flex items-center gap-2 text-sm">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                <span class="text-gray-500">Page <?= $page ?> of <?= ceil($totalLogs / $perPage) ?></span>
                <?php if ($page < ceil($totalLogs / $perPage)): ?>
                <a href="?page=<?= $page + 1 ?>" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
