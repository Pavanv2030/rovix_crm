<?php $this->extend('layouts/main'); ?>
<?php $this->section('content'); ?>

<div class="p-6 max-w-5xl mx-auto" x-data="appointmentTypes()">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= esc($pageTitle) ?></h1>
            <p class="text-sm text-gray-500 mt-0.5">Create service types and set up WhatsApp booking flows</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= base_url('appointments') ?>"
               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                View Appointments
            </a>
            <button @click="showCreate = true"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                + New Type
            </button>
        </div>
    </div>

    <!-- Google Calendar status -->
    <div class="mb-5 p-4 rounded-xl border <?= $googleToken ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' ?>">
        <div class="flex items-center gap-3">
            <span><?= $googleToken ? rx_icon('check-circle', 'w-6 h-6') : rx_icon('warning', 'w-6 h-6') ?></span>
            <div class="flex-1">
                <?php if ($googleToken): ?>
                <p class="text-sm font-medium text-green-800">Google Calendar connected (<?= esc($googleToken['email'] ?? 'Connected') ?>)</p>
                <p class="text-xs text-green-700">New appointments will auto-create calendar events with Google Meet links.</p>
                <?php else: ?>
                <p class="text-sm font-medium text-amber-800">Google Calendar not connected</p>
                <p class="text-xs text-amber-700">Connect to auto-create calendar events and Google Meet links for each booking.</p>
                <?php endif; ?>
            </div>
            <?php if ($googleToken): ?>
            <button @click="disconnectGoogle()" class="text-xs text-red-600 hover:underline">Disconnect</button>
            <?php else: ?>
            <a href="<?= base_url('appointments/google/connect') ?>"
               class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                Connect Google
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Types list -->
    <?php if (empty($types)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-10 text-center">
        <div class="w-14 h-14 rounded-2xl bg-blue-100 flex items-center justify-center mx-auto mb-4">
            <?= rx_icon('calendar', 'w-7 h-7') ?>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">No appointment types yet</h3>
        <p class="text-sm text-gray-500 mb-5">Create a service type (e.g. "Sales Call", "Demo", "Consultation") to start booking.</p>
        <button @click="showCreate = true"
                class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            Create First Type
        </button>
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($types as $type): ?>
        <?php $flow = $flowByType[$type['id']] ?? null; ?>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0"><?= rx_icon('calendar', 'w-5 h-5') ?></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-semibold text-gray-900"><?= esc($type['name']) ?></h3>
                        <?php if ($type['active']): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Active</span>
                        <?php endif; ?>
                        <?php if ($flow): ?>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Flow Published</span>
                        <?php else: ?>
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">No Flow</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($type['description']): ?>
                    <p class="text-sm text-gray-500 mb-2"><?= esc($type['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span><?= rx_icon('clock', 'w-3.5 h-3.5') ?> <?= esc($type['duration_minutes']) ?> min</span>
                        <span><?= rx_icon('money', 'w-3.5 h-3.5') ?> <?= strtoupper(esc($type['currency'])) ?> <?= number_format($type['price'], 2) ?></span>
                        <span><?= rx_icon('calendar', 'w-3.5 h-3.5') ?> Up to <?= esc($type['max_days_ahead']) ?> days ahead</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <?php if (!$flow): ?>
                    <button @click="createFlow('<?= esc($type['id']) ?>', '<?= esc($type['name']) ?>')"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        Create Flow
                    </button>
                    <?php else: ?>
                    <span class="text-xs text-gray-400">Flow ID: <?= esc($flow['flow_id']) ?></span>
                    <?php endif; ?>
                    <button @click="deleteType('<?= esc($type['id']) ?>')"
                            class="text-xs text-red-500 hover:text-red-700 px-2 py-1.5 rounded hover:bg-red-50">
                        Delete
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Create type modal -->
    <div x-show="showCreate" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.away="showCreate = false">
            <h2 class="text-lg font-bold text-gray-900 mb-4">New Appointment Type</h2>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                    <input x-model="form.name" type="text" placeholder="e.g. Sales Call"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <textarea x-model="form.description" rows="2" placeholder="Brief description shown in WhatsApp flow"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Duration (min)</label>
                        <input x-model="form.duration_minutes" type="number" min="5" value="30"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Buffer (min)</label>
                        <input x-model="form.buffer_minutes" type="number" min="0" value="0"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Price</label>
                        <input x-model="form.price" type="number" min="0" step="0.01" value="0"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
                        <select x-model="form.currency"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="INR">INR</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Max days ahead</label>
                    <input x-model="form.max_days_ahead" type="number" min="1" value="60"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <p class="text-xs text-gray-400 mt-3">
                Default availability: Mon–Fri 9am–5pm. You can customize per-day hours after creation.
            </p>

            <div class="flex gap-2 mt-5">
                <button @click="showCreate = false"
                        class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
                <button @click="saveType()" :disabled="saving"
                        class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    <span x-text="saving ? 'Creating...' : 'Create Type'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Create flow overlay -->
    <div x-show="creatingFlow" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 text-center max-w-sm w-full mx-4">
            <div class="w-12 h-12 rounded-full border-4 border-blue-600 border-t-transparent animate-spin mx-auto mb-4"></div>
            <p class="font-semibold text-gray-900">Creating WhatsApp Flow</p>
            <p class="text-sm text-gray-500 mt-1">Creating and publishing flow on Meta...</p>
        </div>
    </div>

</div>

<script>
function appointmentTypes() {
    return {
        showCreate: false,
        saving: false,
        creatingFlow: false,
        form: {
            name: '', description: '', duration_minutes: 30,
            buffer_minutes: 0, price: 0, currency: 'INR', max_days_ahead: 60,
        },

        async saveType() {
            if (!this.form.name.trim()) { alert('Name is required'); return; }
            this.saving = true;
            const fd = new FormData();
            Object.entries(this.form).forEach(([k, v]) => fd.append(k, v));
            const res = await fetch('<?= base_url('appointments/types/create') ?>', { method: 'POST', body: fd });
            const d   = await res.json();
            this.saving = false;
            if (d.success) { window.location.reload(); }
            else { alert(d.error || 'Error creating type'); }
        },

        async createFlow(typeId, typeName) {
            if (!confirm(`Create WhatsApp booking flow for "${typeName}"?\n\nThis publishes a flow on Meta and may take a few seconds.`)) return;
            this.creatingFlow = true;
            const fd = new FormData();
            fd.append('appointment_type_id', typeId);
            const res = await fetch('<?= base_url('appointments/flows/create') ?>', { method: 'POST', body: fd });
            const d   = await res.json();
            this.creatingFlow = false;
            if (d.success) { alert('Flow created and published! Flow ID: ' + d.flow_id + '\n\nReady to use — send it from a conversation in the Inbox.'); window.location.reload(); }
            else { alert('Error: ' + (d.error || 'Unknown error')); }
        },

        async deleteType(typeId) {
            if (!confirm('Delete this appointment type?')) return;
            const fd = new FormData();
            const res = await fetch(`<?= base_url('appointments/types') ?>/${typeId}/delete`, { method: 'POST', body: fd });
            const d   = await res.json();
            if (d.success) { window.location.reload(); }
            else { alert(d.error || 'Error deleting'); }
        },

        async disconnectGoogle() {
            if (!confirm('Disconnect Google Calendar?')) return;
            const res = await fetch('<?= base_url('appointments/google/disconnect') ?>', { method: 'POST', body: new FormData() });
            const d   = await res.json();
            if (d.success) window.location.reload();
        },
    };
}
</script>

<?php $this->endSection(); ?>
