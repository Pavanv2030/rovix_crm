<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div x-data="{
    fields: [],
    showModal: false,
    editField: null,
    form: { field_name: '', field_type: 'text', field_options: '' },
    async load() {
        const res = await fetch('<?= base_url('api/custom-fields') ?>');
        this.fields = await res.json();
    },
    openCreate() { this.editField = null; this.form = { field_name: '', field_type: 'text', field_options: '' }; this.showModal = true; },
    openEdit(f) {
        this.editField = f;
        const opts = f.field_options ? JSON.parse(f.field_options).join(', ') : '';
        this.form = { field_name: f.field_name, field_type: f.field_type, field_options: opts };
        this.showModal = true;
    },
    async save() {
        const fd = new FormData();
        fd.append('field_name', this.form.field_name);
        fd.append('field_type', this.form.field_type);
        fd.append('field_options', this.form.field_options);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const url = this.editField
            ? '<?= base_url('api/custom-fields') ?>/' + this.editField.id
            : '<?= base_url('api/custom-fields') ?>';
        const res = await fetch(url, { method: 'POST', body: fd });
        if (res.ok) { this.showModal = false; await this.load(); }
        else { const d = await res.json(); alert(d.error || 'Error'); }
    },
    async deleteField(f) {
        if (!confirm('Delete field ' + f.field_name + '? All values will be removed.')) return;
        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const res = await fetch('<?= base_url('api/custom-fields') ?>/' + f.id, { method: 'DELETE', body: fd });
        if (res.ok) await this.load();
    }
}" x-init="load()">

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Custom Fields</h1>
    <button @click="openCreate()" class="px-4 py-2 text-sm bg-blue-900 text-white rounded-lg hover:bg-blue-800">+ New Field</button>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Field Name</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Options</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <template x-if="fields.length === 0">
                <tr><td colspan="4" class="text-center py-12 text-gray-400 text-sm">No custom fields yet. Add your first!</td></tr>
            </template>
            <template x-for="f in fields" :key="f.id">
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900" x-text="f.field_name"></td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 capitalize" x-text="f.field_type"></span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500" x-text="f.field_options ? JSON.parse(f.field_options).join(', ') : '—'"></td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button @click="openEdit(f)" class="text-xs px-2 py-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded">Edit</button>
                            <button @click="deleteField(f)" class="text-xs px-2 py-1 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded">Delete</button>
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
        <h3 class="text-lg font-semibold mb-4" x-text="editField ? 'Edit Field' : 'New Custom Field'"></h3>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Field Name</label>
                <input type="text" x-model="form.field_name" placeholder="e.g. Lead Source"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div x-show="!editField">
                <label class="block text-sm font-medium text-gray-700 mb-1">Field Type</label>
                <select x-model="form.field_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="dropdown">Dropdown</option>
                </select>
            </div>

            <div x-show="form.field_type === 'dropdown'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Options (comma-separated)</label>
                <input type="text" x-model="form.field_options" placeholder="Website, Referral, Ad, Walk-in"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">Separate options with commas</p>
            </div>
        </div>

        <div class="flex gap-2 mt-5">
            <button @click="save()" class="flex-1 bg-blue-900 text-white py-2 rounded-lg text-sm hover:bg-blue-800" x-text="editField ? 'Update' : 'Create'"></button>
            <button @click="showModal = false" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">Cancel</button>
        </div>
    </div>
</div>

</div>
<?= $this->endSection() ?>
