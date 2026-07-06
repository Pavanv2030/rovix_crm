<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div x-data="{
    tags: [],
    showModal: false,
    editTag: null,
    form: { name: '', color: '#3B82F6' },
    colors: ['#3B82F6','#EF4444','#10B981','#F59E0B','#8B5CF6','#EC4899','#14B8A6','#F97316','#6366F1','#84CC16','#06B6D4','#A855F7'],
    async load() {
        const res = await fetch('<?= base_url('api/tags') ?>');
        this.tags = await res.json();
    },
    openCreate() { this.editTag = null; this.form = { name: '', color: '#3B82F6' }; this.showModal = true; },
    openEdit(tag) { this.editTag = tag; this.form = { name: tag.name, color: tag.color }; this.showModal = true; },
    async save() {
        const fd = new FormData();
        fd.append('name', this.form.name);
        fd.append('color', this.form.color);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const url = this.editTag
            ? '<?= base_url('api/tags') ?>/' + this.editTag.id
            : '<?= base_url('api/tags') ?>';
        const res = await fetch(url, { method: 'POST', body: fd });
        if (res.ok) { this.showModal = false; await this.load(); }
        else { const d = await res.json(); alert(d.error || 'Error saving tag'); }
    },
    async deleteTag(tag) {
        if (!confirm('Delete tag ' + tag.name + '? It will be removed from all contacts.')) return;
        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const res = await fetch('<?= base_url('api/tags') ?>/' + tag.id, { method: 'DELETE', body: fd });
        if (res.ok) await this.load();
        else alert('Error deleting tag');
    }
}" x-init="load()">

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Tags</h1>
    <button @click="openCreate()" class="px-4 py-2 text-sm bg-blue-900 text-white rounded-lg hover:bg-blue-800">+ New Tag</button>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Tag</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Contacts</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <template x-if="tags.length === 0">
                <tr><td colspan="3" class="text-center py-12 text-gray-400 text-sm">No tags yet. Create your first tag!</td></tr>
            </template>
            <template x-for="tag in tags" :key="tag.id">
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-4 h-4 rounded-full flex-shrink-0" :style="'background-color:' + tag.color"></div>
                            <span class="text-sm font-medium text-gray-900" x-text="tag.name"></span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500" x-text="tag.contact_count + ' contacts'"></td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button @click="openEdit(tag)" class="text-xs px-2 py-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded">Edit</button>
                            <button @click="deleteTag(tag)" class="text-xs px-2 py-1 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded">Delete</button>
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
        <h3 class="text-lg font-semibold mb-4" x-text="editTag ? 'Edit Tag' : 'New Tag'"></h3>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tag Name</label>
                <input type="text" x-model="form.name" placeholder="e.g. Hot Lead"
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
                <div class="flex items-center gap-2 mt-2">
                    <input type="color" x-model="form.color" class="w-8 h-8 rounded cursor-pointer border border-gray-300">
                    <span class="text-sm text-gray-500" x-text="form.color"></span>
                </div>
            </div>

            <!-- Preview -->
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">Preview:</span>
                <span class="text-xs px-3 py-1 rounded-full text-white" :style="'background-color:' + form.color" x-text="form.name || 'Tag Name'"></span>
            </div>
        </div>

        <div class="flex gap-2 mt-5">
            <button @click="save()" class="flex-1 bg-blue-900 text-white py-2 rounded-lg text-sm hover:bg-blue-800" x-text="editTag ? 'Update' : 'Create'"></button>
            <button @click="showModal = false" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">Cancel</button>
        </div>
    </div>
</div>

</div>
<?= $this->endSection() ?>
