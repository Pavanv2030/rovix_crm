<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div x-data="{
    activeTab: 'all',
    showQuick: false,
    qTemplate: '',
    qNumbers: '',
    qHeaderUrl: '',
    qScheduleAt: '',
    qSending: false,
    qResult: null,
    templates: <?= esc(json_encode(array_map(fn($t) => ['id' => $t['id'], 'header_type' => $t['header_type'] ?? 'none'], $templates ?? [])), 'html') ?>,
    get qNeedsHeaderUrl() {
        const t = this.templates.find(t => t.id === this.qTemplate);
        return t && ['image', 'video', 'document'].includes(t.header_type);
    },
    async quickSend(scheduled) {
        if (!this.qTemplate || !this.qNumbers.trim()) return;
        if (scheduled && !this.qScheduleAt) { alert('Please select a schedule date/time'); return; }
        if (this.qNeedsHeaderUrl && !this.qHeaderUrl.trim()) { alert('This template has an image/video/document header — please provide its URL'); return; }
        this.qSending = true; this.qResult = null;
        const fd = new FormData();
        fd.append('template_id', this.qTemplate);
        fd.append('numbers', this.qNumbers);
        if (this.qNeedsHeaderUrl && this.qHeaderUrl.trim()) fd.append('header_url', this.qHeaderUrl.trim());
        if (scheduled && this.qScheduleAt) fd.append('schedule_at', this.qScheduleAt);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        try {
            const res = await fetch('<?= base_url('api/broadcasts/quick-send') ?>', { method: 'POST', body: fd });
            this.qResult = await res.json();
        } catch(e) { this.qResult = { error: 'Network error' }; }
        this.qSending = false;
    }
}">

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Broadcasts</h1>
        <p class="text-sm text-gray-500 mt-0.5">Send WhatsApp template messages to bulk audiences</p>
    </div>
    <div class="flex gap-2">
        <button @click="showQuick = !showQuick"
                class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 font-medium">
            <?= rx_icon('lightning', 'w-4 h-4', '!text-white') ?> Quick Send
        </button>
        <a href="<?= base_url('broadcasts/create') ?>" class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">+ New Broadcast</a>
    </div>
</div>

<!-- Quick Campaign Panel -->
<div x-show="showQuick" x-cloak class="bg-white border border-gray-200 rounded-xl p-5 mb-6 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-800 mb-4"><?= rx_icon('lightning', 'w-4 h-4') ?> Quick Campaign</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Template</label>
            <select x-model="qTemplate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Select Official Template</option>
                <?php foreach ($templates ?? [] as $t): ?>
                <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?> (<?= esc($t['language'] ?? 'en') ?>)</option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($templates ?? [])): ?>
            <p class="text-xs text-gray-400 mt-1">No approved templates. <a href="<?= base_url('templates') ?>" class="text-blue-600 underline">Fetch from Meta →</a></p>
            <?php endif; ?>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Numbers <span class="text-gray-400">(one per line or comma separated)</span></label>
            <textarea x-model="qNumbers" rows="4" placeholder="919876543210&#10;918765432109&#10;917654321098"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
        </div>
    </div>
    <!-- Header Media URL Row -->
    <div x-show="qNeedsHeaderUrl" x-cloak class="mt-4 border-t border-gray-100 pt-4">
        <label class="block text-xs font-medium text-gray-600 mb-1">Header Image/Video/Document URL <span class="text-gray-400">(this template's header needs one — publicly accessible link)</span></label>
        <input type="url" x-model="qHeaderUrl" placeholder="https://example.com/image.jpg"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
    </div>
    <!-- Schedule Date Row -->
    <div class="mt-4 border-t border-gray-100 pt-4">
        <label class="block text-xs font-medium text-gray-600 mb-1">Schedule Date & Time <span class="text-gray-400">(optional — leave blank to send immediately)</span></label>
        <input type="datetime-local" x-model="qScheduleAt"
               min="<?= date('Y-m-d\TH:i') ?>"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-72">
    </div>

    <div class="flex items-center justify-between mt-4">
        <div x-show="qResult" class="text-sm">
            <span x-show="qResult && qResult.success" class="text-green-600" x-text="qResult ? (qResult.scheduled ? 'Scheduled for ' + qResult.scheduled_at : 'Sent: ' + qResult.sent + ' Failed: ' + qResult.failed) : ''"></span>
            <span x-show="qResult && qResult.error" class="text-red-600" x-text="qResult ? qResult.error : ''"></span>
        </div>
        <div class="flex gap-2 ml-auto">
            <button @click="showQuick = false; qResult = null; qScheduleAt = ''" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">Discard</button>
            <button @click="quickSend(true)" :disabled="qSending || !qTemplate || !qNumbers.trim()"
                    :class="qSending || !qTemplate || !qNumbers.trim() ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                    class="px-5 py-2 text-sm bg-blue-600 text-white rounded-lg font-medium transition-colors">
                Schedule
            </button>
            <button @click="quickSend(false)" :disabled="qSending || !qTemplate || !qNumbers.trim()"
                    :class="qSending || !qTemplate || !qNumbers.trim() ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-700'"
                    class="px-5 py-2 text-sm bg-green-600 text-white rounded-lg font-medium transition-colors">
                <span x-text="qSending ? 'Sending...' : 'Send Now'"></span>
            </button>
        </div>
    </div>
</div>

<?php if (session('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= esc(session('success')) ?></div>
<?php endif; ?>
<?php if (session('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= esc(session('error')) ?></div>
<?php endif; ?>

<!-- Status Tabs -->
<div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-lg w-fit">
    <?php
    $tabs = ['all' => 'All', 'draft' => 'Draft', 'scheduled' => 'Scheduled', 'sending' => 'Sending', 'sent' => 'Sent'];
    $tabColors = ['all' => 'bg-gray-500', 'draft' => 'bg-gray-500', 'scheduled' => 'bg-blue-500', 'sending' => 'bg-yellow-500', 'sent' => 'bg-green-500'];
    foreach ($tabs as $key => $label):
    ?>
    <button @click="activeTab = '<?= $key ?>'"
            :class="activeTab === '<?= $key ?>' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
            class="px-3 py-1.5 text-sm rounded-md font-medium flex items-center gap-1.5 transition-all">
        <?= $label ?>
        <span class="text-xs px-1.5 py-0.5 rounded-full text-white <?= $tabColors[$key] ?>"><?= $statusCounts[$key] ?? 0 ?></span>
    </button>
    <?php endforeach; ?>
</div>

<?php if (empty($broadcasts)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
    <div class="mb-3"><?= rx_icon('megaphone', 'w-12 h-12', 'mx-auto') ?></div>
    <p class="text-gray-500 mb-4">No broadcasts yet. Create your first campaign.</p>
    <a href="<?= base_url('broadcasts/create') ?>" class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800">Create Broadcast</a>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php
    $statusBg = ['draft' => 'bg-gray-100 text-gray-600', 'scheduled' => 'bg-blue-100 text-blue-700', 'sending' => 'bg-yellow-100 text-yellow-700', 'sent' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-red-100 text-red-600'];
    foreach ($broadcasts as $b):
        $total = max(1, (int) $b['total_recipients']);
        $done  = (int) $b['sent_count'] + (int) $b['failed_count'];
        $pct   = $total > 0 ? round($done / $total * 100) : 0;
    ?>
    <div x-show="activeTab === 'all' || activeTab === '<?= $b['status'] ?>'"
         class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">

            <!-- Name & Meta -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-semibold text-gray-900"><?= esc($b['name']) ?></h3>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $statusBg[$b['status']] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucfirst($b['status']) ?></span>
                </div>
                <div class="flex items-center gap-3 mt-1 text-xs text-gray-400 flex-wrap">
                    <span>Template: <span class="font-mono text-gray-600"><?= esc($b['template_name']) ?></span></span>
                    <?php if ($b['scheduled_at']): ?><span>Scheduled: <?= date('d M Y, g:i A', strtotime($b['scheduled_at'])) ?></span><?php endif; ?>
                    <?php if ($b['sent_at']): ?><span>Sent: <?= date('d M Y, g:i A', strtotime($b['sent_at'])) ?></span><?php endif; ?>
                    <span>Created: <?= date('d M Y', strtotime($b['created_at'])) ?></span>
                </div>

                <?php if (in_array($b['status'], ['sending', 'sent']) && $b['total_recipients'] > 0): ?>
                <div class="mt-2">
                    <div class="flex justify-between text-xs text-gray-400 mb-1">
                        <span><?= $done ?> / <?= $b['total_recipients'] ?> sent</span>
                        <span><?= $pct ?>%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-green-500 h-1.5 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats row -->
                <?php if ($b['total_recipients'] > 0): ?>
                <div class="flex gap-3 mt-2 text-xs">
                    <span class="text-gray-600"><?= rx_icon('send', 'w-4 h-4') ?> <?= $b['sent_count'] ?> sent</span>
                    <span class="text-blue-600"><?= rx_icon('check-double', 'w-4 h-4') ?> <?= $b['delivered_count'] ?> delivered</span>
                    <span class="text-green-600"><?= rx_icon('eye', 'w-4 h-4') ?> <?= $b['read_count'] ?> read</span>
                    <?php if ($b['failed_count'] > 0): ?><span class="text-red-500"><?= rx_icon('x', 'w-4 h-4') ?> <?= $b['failed_count'] ?> failed</span><?php endif; ?>
                    <span class="text-gray-400"><?= rx_icon('users', 'w-4 h-4') ?> <?= $b['total_recipients'] ?> recipients</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="<?= base_url('broadcasts/' . $b['id']) ?>" class="text-xs px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">View</a>

                <?php if ($b['status'] === 'draft'): ?>
                <a href="<?= base_url('broadcasts/' . $b['id'] . '/edit') ?>" class="text-xs px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">Edit</a>
                <form action="<?= base_url('broadcasts/' . $b['id'] . '/send') ?>" method="POST" class="inline"
                      onsubmit="return confirm('Send this broadcast now?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-xs px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">Send Now</button>
                </form>
                <?php endif; ?>

                <?php if ($b['status'] === 'scheduled'): ?>
                <form action="<?= base_url('broadcasts/' . $b['id'] . '/cancel') ?>" method="POST" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-xs px-3 py-1.5 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-lg">Unschedule</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
<?= $this->endSection() ?>
