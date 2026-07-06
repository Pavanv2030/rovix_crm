<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php helper(['format']); ?>

<script>
window.__contacts = <?= json_encode($contacts, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div x-data="{
    searchQuery: '',
    selectedTags: [],
    contacts: window.__contacts || [],
    get filteredContacts() {
        return this.contacts.filter(c => {
            const q = this.searchQuery.toLowerCase();
            const matchSearch = !q ||
                (c.name && c.name.toLowerCase().includes(q)) ||
                (c.phone && c.phone.includes(q)) ||
                (c.email && c.email.toLowerCase().includes(q)) ||
                (c.company && c.company.toLowerCase().includes(q));
            const matchTags = this.selectedTags.length === 0 ||
                (c.tags && c.tags.some(t => this.selectedTags.includes(t.id)));
            return matchSearch && matchTags;
        });
    },
    toggleTag(id) {
        const idx = this.selectedTags.indexOf(id);
        idx === -1 ? this.selectedTags.push(id) : this.selectedTags.splice(idx, 1);
    },
    deleteContactId: null,
}">

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Contacts</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= number_format($totalCount) ?> total contacts</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= base_url('contacts/import') ?>"
           class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            Import CSV
        </a>
        <a href="<?= base_url('contacts/create') ?>"
           class="px-4 py-2 text-sm bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors font-medium">
            + New Contact
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex items-center gap-4 flex-wrap">
    <input type="text" x-model="searchQuery" placeholder="Search by name, phone, email..."
           class="flex-1 min-w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

    <!-- Tag filter -->
    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2">
            <span>Tags</span>
            <template x-if="selectedTags.length > 0">
                <span class="bg-blue-900 text-white text-xs rounded-full px-1.5" x-text="selectedTags.length"></span>
            </template>
            <span class="text-gray-400">▾</span>
        </button>
        <div x-show="open" @click.away="open = false" x-cloak
             class="absolute left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-20 max-h-48 overflow-y-auto">
            <?php foreach ($allTags as $tag): ?>
            <button @click="toggleTag('<?= esc($tag['id']) ?>')"
                    :class="selectedTags.includes('<?= esc($tag['id']) ?>') ? 'bg-blue-50' : ''"
                    class="flex items-center gap-2 w-full text-left px-3 py-2 text-sm hover:bg-gray-50">
                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: <?= esc($tag['color'] ?? '#3B82F6') ?>"></span>
                <span><?= esc($tag['name']) ?></span>
            </button>
            <?php endforeach; ?>
            <?php if (empty($allTags)): ?>
            <p class="text-xs text-gray-400 text-center py-3">No tags yet</p>
            <?php endif; ?>
        </div>
    </div>

    <button @click="selectedTags = []; searchQuery = ''"
            x-show="selectedTags.length > 0 || searchQuery"
            class="text-sm text-gray-500 hover:text-gray-700">Clear filters</button>

    <span class="text-sm text-gray-400 ml-auto" x-text="filteredContacts.length + ' contacts'"></span>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Company</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Channel</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Vertical</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Rep</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Created</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Follow-up</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="filteredContacts.length === 0">
                    <tr>
                        <td colspan="7" class="text-center py-12 text-gray-400 text-sm">
                            No contacts found. <a href="<?= base_url('contacts/create') ?>" class="text-blue-600 hover:underline">Add your first contact</a>
                        </td>
                    </tr>
                </template>

                <template x-for="c in filteredContacts" :key="c.id">
                    <tr class="hover:bg-gray-50 transition-colors">
                        <!-- Contact: Name + Phone -->
                        <td class="px-4 py-3">
                            <a :href="'<?= base_url('contacts') ?>/' + c.id" class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-blue-900 text-white flex items-center justify-center text-sm font-semibold flex-shrink-0"
                                     x-text="(c.name || c.phone || '?').charAt(0).toUpperCase()"></div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900" x-text="c.name || 'Unknown'"></div>
                                    <div class="text-xs text-gray-400" x-text="c.phone_normalized || c.phone || ''"></div>
                                </div>
                            </a>
                        </td>

                        <!-- Company -->
                        <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell" x-text="c.company || '—'"></td>

                        <!-- Channel -->
                        <td class="px-4 py-3 text-sm text-gray-600 hidden lg:table-cell" x-text="c.channel || '—'"></td>

                        <!-- Vertical -->
                        <td class="px-4 py-3 text-sm text-gray-600 hidden lg:table-cell" x-text="c.vertical || '—'"></td>

                        <!-- Status -->
                        <td class="px-4 py-3">
                            <template x-if="c.status">
                                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border"
                                      :class="{
                                          'bg-blue-50 text-blue-700 border-blue-200':   c.status === 'New',
                                          'bg-green-50 text-green-700 border-green-200': c.status === 'Active',
                                          'bg-yellow-50 text-yellow-700 border-yellow-200': c.status === 'Follow-up',
                                          'bg-red-50 text-red-700 border-red-200':     c.status === 'Lost',
                                          'bg-gray-100 text-gray-600 border-gray-200':  !['New','Active','Follow-up','Lost'].includes(c.status)
                                      }">
                                    <span class="w-1.5 h-1.5 rounded-full inline-block"
                                          :class="{
                                              'bg-blue-500':   c.status === 'New',
                                              'bg-green-500':  c.status === 'Active',
                                              'bg-yellow-500': c.status === 'Follow-up',
                                              'bg-red-500':    c.status === 'Lost',
                                              'bg-gray-400':   !['New','Active','Follow-up','Lost'].includes(c.status)
                                          }"></span>
                                    <span x-text="c.status"></span>
                                </span>
                            </template>
                        </td>

                        <!-- Rep -->
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <template x-if="c.agent_name">
                                <span class="text-sm text-gray-700" x-text="c.agent_name"></span>
                            </template>
                            <template x-if="!c.agent_name">
                                <span class="text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">Unassigned</span>
                            </template>
                        </td>

                        <!-- Created -->
                        <td class="px-4 py-3 text-sm text-gray-500 hidden lg:table-cell"
                            x-text="c.created_at ? c.created_at.substring(0,10) : '—'"></td>

                        <!-- Follow-up -->
                        <td class="px-4 py-3 text-sm hidden lg:table-cell"
                            :class="c.follow_up_date && c.follow_up_date < new Date().toISOString().substring(0,10) ? 'text-red-500 font-medium' : 'text-gray-500'"
                            x-text="c.follow_up_date || '—'"></td>

                        <!-- Actions -->
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a :href="'<?= base_url('contacts') ?>/' + c.id"
                                   class="text-xs px-2 py-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">View</a>
                                <a :href="'<?= base_url('contacts') ?>/' + c.id + '/edit'"
                                   class="text-xs px-2 py-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">Edit</a>
                                <button @click="deleteContactId = c.id"
                                        class="text-xs px-2 py-1 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded transition-colors">Delete</button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div x-show="deleteContactId" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Delete Contact?</h3>
        <p class="text-sm text-gray-500 mb-4">This will permanently delete the contact. Conversations and deals will remain but be unlinked.</p>
        <div class="flex gap-3">
            <form :action="'<?= base_url('contacts') ?>/' + deleteContactId + '/delete'" method="POST" class="flex-1">
                <?= csrf_field() ?>
                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg text-sm hover:bg-red-700">Delete</button>
            </form>
            <button @click="deleteContactId = null" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">Cancel</button>
        </div>
    </div>
</div>

</div>
<?= $this->endSection() ?>
