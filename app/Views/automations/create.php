<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<script>
window.__atData = <?= json_encode([
    'automation'    => $automation ?? null,
    'triggerConfig' => $triggerConfig ?? [],
    'stepsForForm'  => $stepsForForm ?? [],
    'templates'     => array_map(fn($t) => ['name' => $t['name'], 'language' => $t['language']], $templates),
    'tags'          => array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $tags),
    'team'          => array_map(fn($u) => ['id' => $u['id'], 'name' => $u['full_name']], $team),
    'stages'        => $stages,
    'appointmentTypes' => array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $appointmentTypes ?? []),
], JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<?php
$isEdit    = isset($automation);
$formAction = $isEdit
    ? base_url('automations/' . $automation['id'] . '/update')
    : base_url('automations');
?>

<div class="max-w-3xl mx-auto" x-data="automationBuilder()" x-init="init()">

<div class="flex items-center gap-3 mb-6">
    <a href="<?= base_url($isEdit ? 'automations/' . $automation['id'] : 'automations') ?>"
       class="text-gray-400 hover:text-gray-600">← <?= $isEdit ? 'Back' : 'Automations' ?></a>
    <h1 class="text-xl font-bold text-gray-900"><?= esc($pageTitle) ?></h1>
</div>

<?php if (session()->getFlashdata('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>

<form action="<?= $formAction ?>" method="POST" @submit="onSubmit">
    <?= csrf_field() ?>
    <input type="hidden" id="steps_json" name="steps_json" :value="JSON.stringify(steps.map(s=>({type:s.type,config:s.config})))">

    <!-- ── Name ── -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Automation Name <span class="text-red-500">*</span></label>
        <input type="text" name="name"
               value="<?= esc($automation['name'] ?? old('name') ?? '') ?>"
               placeholder="e.g. Welcome new contacts"
               required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>

    <!-- ── Trigger ── -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Trigger — When should this run?</h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
            <?php
            $triggers = [
                ['value' => 'new_message_received',  'icon' => rx_icon('chat', 'w-4 h-4'), 'label' => 'New Message'],
                ['value' => 'first_inbound_message', 'icon' => rx_icon('wave', 'w-4 h-4'), 'label' => 'First Message'],
                ['value' => 'keyword_match',          'icon' => rx_icon('key', 'w-4 h-4'), 'label' => 'Keyword Match'],
                ['value' => 'new_contact_created',    'icon' => rx_icon('user', 'w-4 h-4'), 'label' => 'New Contact'],
                ['value' => 'conversation_assigned',  'icon' => rx_icon('user-raise', 'w-4 h-4'), 'label' => 'Assigned'],
                ['value' => 'tag_added',              'icon' => rx_icon('tag', 'w-4 h-4'), 'label' => 'Tag Added'],
                ['value' => 'time_based',             'icon' => rx_icon('clock', 'w-4 h-4'), 'label' => 'Time-Based'],
            ];
            foreach ($triggers as $tr): ?>
            <label :class="triggerType === '<?= $tr['value'] ?>'
                           ? 'border-blue-600 bg-blue-50 text-blue-800'
                           : 'border-gray-200 text-gray-600 hover:border-blue-300'"
                   class="flex items-center gap-2 px-3 py-2.5 border rounded-lg cursor-pointer text-sm transition-colors">
                <input type="radio" name="trigger_type" value="<?= $tr['value'] ?>"
                       x-model="triggerType" class="sr-only">
                <span><?= $tr['icon'] ?></span>
                <span class="font-medium"><?= $tr['label'] ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Trigger config: keyword_match -->
        <div x-show="triggerType === 'keyword_match'" class="border-t border-gray-100 pt-4 space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Keywords (comma-separated)</label>
                <input type="text" name="tc_keywords" x-model="tc.keywords"
                       placeholder="hello, hi, start"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="tc_match_all" value="1" x-model="tc.match_all" class="rounded border-gray-300 text-blue-600">
                Match ALL keywords (AND), not any one (OR)
            </label>
        </div>

        <!-- Trigger config: tag_added -->
        <div x-show="triggerType === 'tag_added'" class="border-t border-gray-100 pt-4">
            <label class="block text-xs font-medium text-gray-600 mb-1">Filter to specific tag (optional — leave blank to fire on any tag)</label>
            <select name="tc_tag_id" x-model="tc.tag_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— Any tag —</option>
                <?php foreach ($tags as $tag): ?>
                <option value="<?= esc($tag['id']) ?>"><?= esc($tag['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Trigger config: time_based -->
        <div x-show="triggerType === 'time_based'" class="border-t border-gray-100 pt-4 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Schedule</label>
                    <select name="tc_schedule" x-model="tc.schedule"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Time</label>
                    <input type="time" name="tc_time" x-model="tc.time"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div x-show="tc.schedule === 'weekly'">
                <label class="block text-xs font-medium text-gray-600 mb-1">Day of Week</label>
                <select name="tc_day" x-model="tc.day"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="monday">Monday</option>
                    <option value="tuesday">Tuesday</option>
                    <option value="wednesday">Wednesday</option>
                    <option value="thursday">Thursday</option>
                    <option value="friday">Friday</option>
                    <option value="saturday">Saturday</option>
                    <option value="sunday">Sunday</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── Steps ── -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Steps — What should happen?</h2>
            <button type="button" @click="addStep()"
                    class="text-sm px-3 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-100 font-medium">
                + Add Step
            </button>
        </div>

        <div x-show="steps.length === 0" class="text-center py-8 text-sm text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">
            No steps yet. Click "+ Add Step" to begin.
        </div>

        <div class="space-y-3">
            <template x-for="(step, idx) in steps" :key="step._id">
                <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                    <!-- Step header -->
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-xs font-bold text-gray-400 w-5 text-center" x-text="idx + 1"></span>
                        <select x-model="step.type" @change="step.config = {}"
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="send_message">Send Text Message</option>
                            <option value="send_template">Send Template</option>
                            <option value="send_appointment_flow">Send Appointment Booking</option>
                            <option value="send_catalog">Send Catalog</option>
                            <option value="add_tag">Add Tag</option>
                            <option value="remove_tag">Remove Tag</option>
                            <option value="assign_conversation">Assign Conversation</option>
                            <option value="update_contact_field">Update Contact Field</option>
                            <option value="create_deal">Create Deal</option>
                            <option value="wait">Wait</option>
                            <option value="condition">Condition (Filter)</option>
                            <option value="send_webhook">Send Webhook</option>
                            <option value="close_conversation">Close Conversation</option>
                        </select>
                        <button type="button" @click="moveUp(idx)" :disabled="idx === 0"
                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-20">↑</button>
                        <button type="button" @click="moveDown(idx)" :disabled="idx === steps.length - 1"
                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-20">↓</button>
                        <button type="button" @click="removeStep(idx)"
                                class="p-1 text-red-400 hover:text-red-600"><?= rx_icon('x', 'w-4 h-4') ?></button>
                    </div>

                    <!-- send_message -->
                    <div x-show="step.type === 'send_message'">
                        <label class="block text-xs text-gray-500 mb-1">Message <span class="text-gray-400">(use {{name}}, {{phone}}, {{email}}, {{company}})</span></label>
                        <textarea x-model="step.config.message" rows="3"
                                  placeholder="Hello {{name}}, thanks for reaching out!"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>

                    <!-- send_template -->
                    <div x-show="step.type === 'send_template'" class="space-y-2">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Template</label>
                            <select x-model="step.config.template_name"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">— Select approved template —</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?= esc($t['name']) ?>"><?= esc($t['name'] . ' (' . $t['language'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-xs text-gray-400">Variables: map them via Edit if template requires {{1}}, {{2}}, etc.</p>
                    </div>

                    <!-- send_appointment_flow -->
                    <div x-show="step.type === 'send_appointment_flow'" class="space-y-2">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Appointment Type</label>
                            <select x-model="step.config.appointment_type_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">— Select appointment type —</option>
                                <?php foreach ($appointmentTypes as $t): ?>
                                    <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <template x-if="appointmentTypes.length === 0">
                                <p class="text-xs text-amber-600 mt-1">No appointment types found. Create one in Appointments → Types first.</p>
                            </template>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Message text (optional)</label>
                            <input type="text" x-model="step.config.body_text" placeholder="Please choose a date & time for your appointment."
                                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- send_catalog -->
                    <div x-show="step.type === 'send_catalog'" class="space-y-2">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Message text (optional)</label>
                            <input type="text" x-model="step.config.body_text" placeholder="Browse our products"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Footer text (optional)</label>
                            <input type="text" x-model="step.config.footer_text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <p class="text-xs text-gray-400">Sends the catalog connected in Catalog settings.</p>
                    </div>

                    <!-- add_tag / remove_tag -->
                    <div x-show="step.type === 'add_tag' || step.type === 'remove_tag'">
                        <label class="block text-xs text-gray-500 mb-1" x-text="step.type === 'add_tag' ? 'Tag to add' : 'Tag to remove'"></label>
                        <select x-model="step.config.tag_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">— Select tag —</option>
                            <?php foreach ($tags as $t): ?>
                                <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- assign_conversation -->
                    <div x-show="step.type === 'assign_conversation'">
                        <label class="block text-xs text-gray-500 mb-1">Assign to team member (leave blank to unassign)</label>
                        <select x-model="step.config.user_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($team as $u): ?>
                                <option value="<?= esc($u['id']) ?>"><?= esc($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- update_contact_field -->
                    <div x-show="step.type === 'update_contact_field'" class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Field</label>
                            <select x-model="step.config.field"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="name">Name</option>
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="company">Company</option>
                                <option value="notes">Notes</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Value</label>
                            <input type="text" x-model="step.config.value"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- create_deal -->
                    <div x-show="step.type === 'create_deal'" class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Deal Name</label>
                                <input type="text" x-model="step.config.name" placeholder="Deal — {{name}}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Value (₹)</label>
                                <input type="number" x-model="step.config.value" min="0"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Pipeline Stage</label>
                            <select x-model="step.config.stage_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">— Select stage —</option>
                                <?php foreach ($stages as $s): ?>
                                    <option value="<?= esc($s['id']) ?>"><?= esc($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- wait -->
                    <div x-show="step.type === 'wait'" class="flex items-center gap-3">
                        <label class="text-xs text-gray-500">Wait</label>
                        <input type="number" x-model="step.config.amount" min="1" value="1"
                               class="w-20 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <select x-model="step.config.unit"
                                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="minutes">Minutes</option>
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                        </select>
                        <span class="text-xs text-gray-400">before continuing</span>
                    </div>

                    <!-- condition -->
                    <div x-show="step.type === 'condition'" class="space-y-2">
                        <p class="text-xs text-gray-400">If condition is false, the automation stops here.</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Field</label>
                                <select x-model="step.config.field"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                    <option value="name">Name</option>
                                    <option value="email">Email</option>
                                    <option value="phone">Phone</option>
                                    <option value="company">Company</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Operator</label>
                                <select x-model="step.config.operator"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                    <option value="not_empty">is not empty</option>
                                    <option value="empty">is empty</option>
                                    <option value="equals">equals</option>
                                    <option value="not_equals">not equals</option>
                                    <option value="contains">contains</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Value</label>
                                <input type="text" x-model="step.config.value"
                                       :disabled="['not_empty','empty'].includes(step.config.operator)"
                                       :class="['not_empty','empty'].includes(step.config.operator) ? 'bg-gray-100' : ''"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- send_webhook -->
                    <div x-show="step.type === 'send_webhook'">
                        <label class="block text-xs text-gray-500 mb-1">Webhook URL</label>
                        <input type="url" x-model="step.config.url" placeholder="https://your-site.com/webhook"
                               class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- close_conversation -->
                    <div x-show="step.type === 'close_conversation'">
                        <p class="text-xs text-gray-500">Closes the most recent open conversation for this contact.</p>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="steps.length > 0" class="mt-3 text-center">
            <button type="button" @click="addStep()"
                    class="text-sm text-blue-600 hover:text-blue-800">+ Add another step</button>
        </div>
    </div>

    <!-- Submit -->
    <div class="flex gap-3">
        <button type="submit"
                class="px-6 py-2.5 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
            <?= $isEdit ? 'Save Changes' : 'Create Automation' ?>
        </button>
        <a href="<?= base_url($isEdit ? 'automations/' . ($automation['id'] ?? '') : 'automations') ?>"
           class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</a>
    </div>
</form>
</div>

<script>
function automationBuilder() {
    const d = window.__atData;
    let stepIdCounter = 0;
    return {
        triggerType: d.automation?.trigger_type || 'new_message_received',
        tc: {
            keywords:  d.triggerConfig?.keywords || '',
            match_all: !!d.triggerConfig?.match_all,
            tag_id:    d.triggerConfig?.tag_id || '',
            schedule:  d.triggerConfig?.schedule || 'daily',
            time:      d.triggerConfig?.time || '09:00',
            day:       d.triggerConfig?.day || 'monday',
        },
        steps:     [],
        templates: d.templates || [],
        tags:      d.tags || [],
        team:      d.team || [],
        stages:    d.stages || [],
        appointmentTypes: d.appointmentTypes || [],

        init() {
            if (d.stepsForForm && d.stepsForForm.length > 0) {
                this.steps = d.stepsForForm.map((s) => ({
                    _id:    ++stepIdCounter,
                    type:   s.type,
                    config: s.config || {},
                }));
            }
        },
        addStep() {
            this.steps.push({ _id: ++stepIdCounter, type: 'send_message', config: {} });
        },
        removeStep(idx) {
            this.steps.splice(idx, 1);
        },
        moveUp(idx) {
            if (idx === 0) return;
            const tmp = this.steps[idx - 1];
            this.steps[idx - 1] = this.steps[idx];
            this.steps[idx] = tmp;
        },
        moveDown(idx) {
            if (idx >= this.steps.length - 1) return;
            const tmp = this.steps[idx + 1];
            this.steps[idx + 1] = this.steps[idx];
            this.steps[idx] = tmp;
        },
        onSubmit() {
            document.getElementById('steps_json').value = JSON.stringify(
                this.steps.map(s => ({
                    type: s.type,
                    config: s.config
                }))
            );
        },
    };
}
</script>
<?= $this->endSection() ?>
