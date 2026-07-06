<?php $this->extend('layouts/main'); ?>
<?php $this->section('content'); ?>

<div class="p-6 max-w-6xl mx-auto" x-data="appointmentsIndex()">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= esc($pageTitle) ?></h1>
            <p class="text-sm text-gray-500 mt-0.5">All booked appointments across your account</p>
        </div>
        <a href="<?= base_url('appointments/types') ?>"
           class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            Manage Types
        </a>
    </div>

    <?php if (empty($appointments)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-14 text-center">
        <?= rx_icon('calendar', 'w-12 h-12', 'mx-auto') ?>
        <h3 class="text-lg font-semibold text-gray-900 mt-4 mb-2">No appointments yet</h3>
        <p class="text-sm text-gray-500 mb-5">Send a booking flow from the Inbox to get your first appointment.</p>
        <a href="<?= base_url('appointments/types') ?>"
           class="inline-block px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            Set Up Appointment Types
        </a>
    </div>
    <?php else: ?>

    <!-- Summary cards -->
    <?php
    $counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
    foreach ($appointments as $a) { $counts[$a['status']] = ($counts[$a['status']] ?? 0) + 1; }
    ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php $cards = [
            ['label' => 'Pending',   'count' => $counts['pending'],   'color' => 'bg-amber-50 text-amber-700 border-amber-200'],
            ['label' => 'Confirmed', 'count' => $counts['confirmed'],  'color' => 'bg-blue-50 text-blue-700 border-blue-200'],
            ['label' => 'Completed', 'count' => $counts['completed'],  'color' => 'bg-green-50 text-green-700 border-green-200'],
            ['label' => 'Cancelled', 'count' => $counts['cancelled'],  'color' => 'bg-red-50 text-red-700 border-red-200'],
        ]; ?>
        <?php foreach ($cards as $card): ?>
        <div class="bg-white rounded-xl border <?= $card['color'] ?> p-4 text-center">
            <div class="text-2xl font-bold"><?= $card['count'] ?></div>
            <div class="text-xs font-medium mt-0.5"><?= $card['label'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Scheduled</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Meet</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($appointments as $apt): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3.5">
                        <div class="font-medium text-gray-900"><?= esc($apt['contact_name'] ?? 'Unknown') ?></div>
                        <div class="text-xs text-gray-400"><?= esc($apt['contact_phone'] ?? '') ?></div>
                    </td>
                    <td class="px-5 py-3.5 text-gray-700"><?= esc($apt['type_name'] ?? '—') ?></td>
                    <td class="px-5 py-3.5 text-gray-700">
                        <?php if ($apt['scheduled_at']): ?>
                        <div><?= date('d M Y', strtotime($apt['scheduled_at'])) ?></div>
                        <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($apt['scheduled_at'])) ?></div>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <?php $statusColor = match($apt['status']) {
                            'confirmed' => 'bg-blue-100 text-blue-700',
                            'completed' => 'bg-green-100 text-green-700',
                            'cancelled' => 'bg-red-100 text-red-700',
                            default     => 'bg-amber-100 text-amber-700',
                        }; ?>
                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium <?= $statusColor ?>">
                            <?= ucfirst($apt['status']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <?php if ($apt['meet_link']): ?>
                        <a href="<?= esc($apt['meet_link']) ?>" target="_blank"
                           class="text-blue-600 hover:underline text-xs">Join Meet</a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <?php if ($apt['booking_token']): ?>
                            <a href="<?= base_url('booking/' . $apt['booking_token']) ?>" target="_blank"
                               class="text-xs text-gray-500 hover:text-blue-600 px-2 py-1 rounded hover:bg-gray-100">
                                View
                            </a>
                            <?php endif; ?>
                            <?php if (!in_array($apt['status'], ['cancelled', 'completed'])): ?>
                            <button @click="updateStatus('<?= esc($apt['id']) ?>', 'confirmed')"
                                    class="text-xs text-blue-600 hover:underline px-2 py-1 rounded hover:bg-blue-50">
                                Confirm
                            </button>
                            <button @click="sendReminder('<?= esc($apt['id']) ?>')"
                                    class="text-xs text-amber-600 hover:underline px-2 py-1 rounded hover:bg-amber-50">
                                <span x-show="sendingReminder !== '<?= esc($apt['id']) ?>'">Send Reminder</span>
                                <span x-show="sendingReminder === '<?= esc($apt['id']) ?>'">Sending…</span>
                            </button>
                            <button @click="reschedule('<?= esc($apt['id']) ?>')"
                                    class="text-xs text-purple-600 hover:underline px-2 py-1 rounded hover:bg-purple-50">
                                <span x-show="rescheduling !== '<?= esc($apt['id']) ?>'">Reschedule</span>
                                <span x-show="rescheduling === '<?= esc($apt['id']) ?>'">Sending…</span>
                            </button>
                            <button @click="cancelApt('<?= esc($apt['id']) ?>')"
                                    class="text-xs text-red-500 hover:underline px-2 py-1 rounded hover:bg-red-50">
                                Cancel
                            </button>
                            <?php endif; ?>
                            <?php if ($apt['reminder_sent_at']): ?>
                            <span class="text-xs text-gray-400" title="Reminder sent <?= esc($apt['reminder_sent_at']) ?>">✓ Reminded</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<script>
function appointmentsIndex() {
    return {
        sendingReminder: null,
        rescheduling: null,
        async reschedule(id) {
            if (this.rescheduling) return;
            this.rescheduling = id;
            try {
                const res = await fetch(`<?= base_url('appointments') ?>/${id}/reschedule`, { method: 'POST', body: new FormData() });
                const d   = await res.json();
                alert(d.success ? d.message : (d.error || 'Error'));
            } catch (e) {
                alert('Failed to send reschedule request');
            }
            this.rescheduling = null;
        },
        async updateStatus(id, status) {
            const fd = new FormData();
            fd.append('status', status);
            const res = await fetch(`<?= base_url('appointments') ?>/${id}/status`, { method: 'POST', body: fd });
            const d   = await res.json();
            if (d.success) window.location.reload();
            else alert(d.error || 'Error');
        },
        async cancelApt(id) {
            if (!confirm('Cancel this appointment?')) return;
            const res = await fetch(`<?= base_url('appointments') ?>/${id}/cancel`, { method: 'POST', body: new FormData() });
            const d   = await res.json();
            if (d.success) window.location.reload();
            else alert(d.error || 'Error');
        },
        async sendReminder(id) {
            if (this.sendingReminder) return;
            this.sendingReminder = id;
            try {
                const res = await fetch(`<?= base_url('appointments') ?>/${id}/send-reminder`, { method: 'POST', body: new FormData() });
                const d   = await res.json();
                if (d.success) window.location.reload();
                else { alert(d.error || 'Error'); this.sendingReminder = null; }
            } catch (e) {
                alert('Failed to send reminder');
                this.sendingReminder = null;
            }
        },
    };
}
</script>

<?php $this->endSection(); ?>
