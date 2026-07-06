<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div x-data="{
    statuses: [],
    showModal: false,
    editStatus: null,
    form: { name: '', color: '#3B82F6', auto_reply_message: '', reply_mode: 'static', ai_instruction: '', template_id: '', template_header_url: '' },
    colors: ['#3B82F6','#EF4444','#10B981','#F59E0B','#8B5CF6','#EC4899','#14B8A6','#F97316','#6366F1','#84CC16','#06B6D4','#A855F7'],
    templates: <?= esc(json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'language' => $t['language'], 'needs_header_url' => $t['needs_header_url']], $templates ?? [])), 'html') ?>,
    async load() {
        const res = await fetch('<?= base_url('api/lead-statuses') ?>');
        this.statuses = await res.json();
    },
    templateName(id) {
        const t = this.templates.find(t => t.id === id);
        return t ? t.name : '(deleted template)';
    },
    selectedTemplateNeedsHeaderUrl() {
        const t = this.templates.find(t => t.id === this.form.template_id);
        return t ? t.needs_header_url : false;
    },
    openCreate() { this.editStatus = null; this.form = { name: '', color: '#3B82F6', auto_reply_message: '', reply_mode: 'static', ai_instruction: '', template_id: '', template_header_url: '' }; this.showModal = true; },
    openEdit(s) { this.editStatus = s; this.form = { name: s.name, color: s.color, auto_reply_message: s.auto_reply_message || '', reply_mode: s.reply_mode || 'static', ai_instruction: s.ai_instruction || '', template_id: s.template_id || '', template_header_url: s.template_header_url || '' }; this.showModal = true; },
    async save() {
        const fd = new FormData();
        fd.append('name', this.form.name);
        fd.append('color', this.form.color);
        fd.append('auto_reply_message', this.form.auto_reply_message);
        fd.append('reply_mode', this.form.reply_mode);
        fd.append('ai_instruction', this.form.ai_instruction);
        fd.append('template_id', this.form.template_id);
        fd.append('template_header_url', this.form.template_header_url);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const url = this.editStatus
            ? '<?= base_url('api/lead-statuses') ?>/' + this.editStatus.id
            : '<?= base_url('api/lead-statuses') ?>';
        const res = await fetch(url, { method: 'POST', body: fd });
        if (res.ok) { this.showModal = false; await this.load(); }
        else { const d = await res.json(); alert(d.error || 'Error saving status'); }
    },
    async deleteStatus(s) {
        if (!confirm('Delete status ' + s.name + '? Conversations on this status will be cleared to none.')) return;
        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const res = await fetch('<?= base_url('api/lead-statuses') ?>/' + s.id, { method: 'DELETE', body: fd });
        if (res.ok) await this.load();
        else alert('Error deleting status');
    }
}" x-init="load()">

<div class="mb-2">
    <h1 class="text-2xl font-bold text-gray-900">Lead Statuses</h1>
    <p class="text-sm text-gray-500 mt-0.5">Set per-status in the Inbox. Add an auto-reply message and it's sent to the customer on WhatsApp automatically the moment you change a conversation to that status.</p>
</div>

<div class="flex items-center justify-end mb-4 mt-4">
    <button @click="openCreate()" class="px-4 py-2 text-sm bg-blue-900 text-white rounded-lg hover:bg-blue-800">+ New Status</button>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Reply</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <template x-if="statuses.length === 0">
                <tr><td colspan="3" class="text-center py-12 text-gray-400 text-sm">No lead statuses yet. Create your first one!</td></tr>
            </template>
            <template x-for="s in statuses" :key="s.id">
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-4 h-4 rounded-full flex-shrink-0" :style="'background-color:' + s.color"></div>
                            <span class="text-sm font-medium text-gray-900" x-text="s.name"></span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 max-w-md truncate">
                        <template x-if="s.reply_mode === 'ai'">
                            <span class="inline-flex items-center gap-1 text-purple-700"><?= rx_icon('lightning', 'w-3.5 h-3.5') ?> <span x-text="s.ai_instruction || 'AI personalized reply'"></span></span>
                        </template>
                        <template x-if="s.reply_mode === 'template'">
                            <span class="inline-flex items-center gap-1 text-teal-700"><?= rx_icon('document', 'w-3.5 h-3.5') ?> <span x-text="'Template: ' + templateName(s.template_id)"></span></span>
                        </template>
                        <template x-if="!s.reply_mode || s.reply_mode === 'static'">
                            <span x-text="s.auto_reply_message || '— none —'"></span>
                        </template>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button @click="openEdit(s)" class="text-xs px-2 py-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded">Edit</button>
                            <button @click="deleteStatus(s)" class="text-xs px-2 py-1 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded">Delete</button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div x-show="showModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold mb-4" x-text="editStatus ? 'Edit Status' : 'New Status'"></h3>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status Name</label>
                <input type="text" x-model="form.name" placeholder="e.g. Qualified"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="color in colors" :key="color">
                        <button @click="form.color = color" type="button"
                                :style="'background-color:' + color"
                                :class="form.color === color ? 'ring-2 ring-offset-1 ring-blue-900 scale-110' : ''"
                                class="w-7 h-7 rounded-full transition-transform"></button>
                    </template>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reply Type</label>
                <div class="space-y-2">
                    <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg border" :class="form.reply_mode === 'static' ? 'border-blue-400 bg-blue-50' : 'border-gray-200'">
                        <input type="radio" value="static" x-model="form.reply_mode" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Static Message</span>
                            <span class="block text-xs text-gray-400">Same fixed text every time. Only works within WhatsApp's 24h reply window.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg border" :class="form.reply_mode === 'ai' ? 'border-purple-400 bg-purple-50' : 'border-gray-200'">
                        <input type="radio" value="ai" x-model="form.reply_mode" class="mt-0.5 text-purple-600 focus:ring-purple-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">AI Personalized</span>
                            <span class="block text-xs text-gray-400">OpenAI writes a fresh message per customer. Also only works within the 24h window.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg border" :class="form.reply_mode === 'template' ? 'border-teal-400 bg-teal-50' : 'border-gray-200'">
                        <input type="radio" value="template" x-model="form.reply_mode" class="mt-0.5 text-teal-600 focus:ring-teal-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Approved Template</span>
                            <span class="block text-xs text-gray-400">Sends a Meta-approved template — works even outside the 24h window, e.g. re-engaging an old lead.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div x-show="form.reply_mode === 'ai'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">AI Instruction</label>
                <textarea x-model="form.ai_instruction" rows="2" placeholder="e.g. Thank them warmly for their interest and let them know we'll call within 24 hours."
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                <p class="text-xs text-gray-400 mt-1">Tell the AI what the message should accomplish — it writes the actual wording per customer, using their name and recent messages as context.</p>
            </div>

            <div x-show="form.reply_mode === 'static'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">Auto-Reply Message (optional)</label>
                <textarea x-model="form.auto_reply_message" rows="3" placeholder="e.g. Thanks {{name}}! We've noted your interest and will follow up shortly."
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <p class="text-xs text-gray-400 mt-1">Sent automatically on WhatsApp when a conversation is set to this status. Use <code>{{name}}</code> for the contact's name. Leave blank to send nothing.</p>
            </div>

            <div x-show="form.reply_mode === 'template'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                <select x-model="form.template_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <option value="">— Select template —</option>
                    <template x-for="t in templates" :key="t.id">
                        <option :value="t.id" x-text="t.name + ' (' + t.language + ')'"></option>
                    </template>
                </select>
                <p class="text-xs text-gray-400 mt-1">Only Meta-approved templates show here. Manage them under Templates.</p>
                <p class="text-xs text-amber-600 mt-1" x-show="templates.length === 0">No approved templates yet — create one under Templates first.</p>

                <div class="mt-3" x-show="selectedTemplateNeedsHeaderUrl()" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Header Image/Video/Document URL</label>
                    <input type="text" x-model="form.template_header_url" placeholder="https://example.com/image.jpg"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <p class="text-xs text-amber-600 mt-1">This template's header needs a real media URL — WhatsApp rejects the send without one (can't be a placeholder).</p>
                </div>
            </div>

            <!-- Preview -->
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">Preview:</span>
                <span class="text-xs px-3 py-1 rounded-full text-white" :style="'background-color:' + form.color" x-text="form.name || 'Status Name'"></span>
            </div>
        </div>

        <div class="flex gap-2 mt-5">
            <button @click="save()" class="flex-1 bg-blue-900 text-white py-2 rounded-lg text-sm hover:bg-blue-800" x-text="editStatus ? 'Update' : 'Create'"></button>
            <button @click="showModal = false" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">Cancel</button>
        </div>
    </div>
</div>

</div>
<?= $this->endSection() ?>
