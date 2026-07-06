<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<script>
window.__templates = <?= json_encode(array_map(fn($t) => [
    'name'         => $t['name'],
    'language'     => $t['language'],
    'category'     => $t['category'],
    'body_text'    => $t['body_text'],
    'header_type'  => $t['header_type'],
    'header_content' => $t['header_content'] ?? '',
    'footer_text'  => $t['footer_text'] ?? '',
    'buttons'      => json_decode($t['buttons'] ?? '[]', true) ?? [],
    'var_count'    => preg_match_all('/\{\{\d+\}\}/', $t['body_text']),
], $templates), JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__tags = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $tags), JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="max-w-5xl mx-auto" x-data="{
    templateName: '',
    audienceType: 'all',
    selectedTags: [],
    recipientCount: 0,
    countLoading: false,
    varSources: {},

    get selectedTemplate() {
        return window.__templates.find(t => t.name === this.templateName) || null;
    },
    get variableList() {
        if (!this.selectedTemplate) return [];
        const m = this.selectedTemplate.body_text.match(/\{\{(\d+)\}\}/g);
        if (!m) return [];
        return [...new Set(m)].map(v => v.replace(/\{\{|\}\}/g, '')).sort((a,b)=>a-b);
    },
    get previewBody() {
        if (!this.selectedTemplate) return '';
        let t = this.selectedTemplate.body_text;
        for (const v of this.variableList) {
            const label = {name:'[Contact Name]', phone:'[Phone]', company:'[Company]', email:'[Email]'}[this.varSources[v]] || (this.varSources[v] ? this.varSources[v] : '{{' + v + '}}');
            t = t.replace(new RegExp('\\{\\{' + v + '\\}\\}','g'), label);
        }
        return t;
    },
    async updateCount() {
        this.countLoading = true;
        try {
            const r = await fetch(window.__BASE + 'api/broadcasts/count-recipients', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ audience_type: this.audienceType, tag_ids: this.selectedTags })
            });
            const d = await r.json();
            this.recipientCount = d.count || 0;
        } catch(e) { this.recipientCount = 0; }
        this.countLoading = false;
    },
    toggleTag(id) {
        const i = this.selectedTags.indexOf(id);
        if (i === -1) this.selectedTags.push(id); else this.selectedTags.splice(i, 1);
        this.updateCount();
    },
    init() { this.updateCount(); }
}">

<div class="flex items-center gap-3 mb-6">
    <a href="<?= base_url('broadcasts') ?>" class="text-gray-400 hover:text-gray-600">← Broadcasts</a>
    <h1 class="text-xl font-bold text-gray-900">New Broadcast</h1>
</div>

<?php if (empty($templates)): ?>
<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 mb-6 text-sm text-yellow-800">
    No approved templates found. <a href="<?= base_url('templates/create') ?>" class="underline font-medium">Create and submit a template</a> for approval first.
</div>
<?php endif; ?>

<form action="<?= base_url('broadcasts') ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="audience_type" :value="audienceType">
    <template x-for="tid in selectedTags" :key="tid">
        <input type="hidden" name="tag_ids[]" :value="tid">
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <div class="lg:col-span-3 space-y-4">

            <!-- Basic Info -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Campaign Info</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. June Offer Campaign"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Template <span class="text-red-500">*</span></label>
                    <select name="template_name" x-model="templateName" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Select approved template —</option>
                        <?php foreach ($templates as $t): ?>
                        <option value="<?= esc($t['name']) ?>"><?= esc($t['name']) ?> (<?= esc($t['language']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div x-show="selectedTemplate && ['image','video','document'].includes(selectedTemplate.header_type)" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Header Media URL <span class="text-red-500">*</span>
                        <span class="ml-1 text-xs text-gray-400 font-normal">(publicly accessible image/video/document link)</span>
                    </label>
                    <input type="url" name="header_media_url" placeholder="https://example.com/image.jpg"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Audience -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Audience</h2>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="audienceType" @change="updateCount()" value="all" class="text-blue-600">
                        <span class="text-sm font-medium text-gray-700">All Contacts</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="audienceType" @change="updateCount()" value="tags" class="text-blue-600">
                        <span class="text-sm font-medium text-gray-700">Filter by Tags</span>
                    </label>
                </div>

                <div x-show="audienceType === 'tags'" class="space-y-2">
                    <p class="text-xs text-gray-500">Select tags to target:</p>
                    <?php if (empty($tags)): ?>
                    <p class="text-sm text-gray-400">No tags available. <a href="<?= base_url('settings/tags') ?>" class="underline text-blue-600">Create tags first.</a></p>
                    <?php else: ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($tags as $tag): ?>
                        <button type="button" @click="toggleTag('<?= $tag['id'] ?>')"
                                :class="selectedTags.includes('<?= $tag['id'] ?>') ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'"
                                class="text-xs px-3 py-1.5 border rounded-full transition-colors"><?= esc($tag['name']) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-center gap-3">
                    <span class="text-2xl font-bold text-blue-700" x-text="recipientCount"></span>
                    <div>
                        <p class="text-sm font-medium text-blue-800">contacts will receive this broadcast</p>
                        <p x-show="countLoading" class="text-xs text-blue-500">Calculating...</p>
                    </div>
                </div>
            </div>

            <!-- Variable Mapping -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3" x-show="selectedTemplate && variableList.length > 0">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Variable Mapping</h2>
                <p class="text-xs text-gray-500">Map each template variable to a contact field or custom text:</p>
                <template x-for="v in variableList" :key="v">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-mono text-blue-600 w-12 flex-shrink-0" x-text="'{{' + v + '}}'"></span>
                        <select :name="'var_source[' + v + ']'" x-model="varSources[v]"
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Select field —</option>
                            <option value="name">Contact Name</option>
                            <option value="phone">Phone Number</option>
                            <option value="company">Company</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                </template>
            </div>

            <!-- Advanced Settings -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Advanced Settings</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Batch Size
                        <span class="ml-1 text-xs text-gray-400 font-normal">(messages per batch, max 50)</span>
                    </label>
                    <div class="flex items-center gap-4" x-data="{ batchSize: 50 }">
                        <input type="range" name="batch_size" min="1" max="50" x-model="batchSize"
                               class="flex-1 accent-blue-600">
                        <span class="text-sm font-bold text-blue-700 w-8 text-center" x-text="batchSize"></span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        Meta allows max 80 msg/sec. Lower batch size = slower but safer for large campaigns.
                        Recommended: <strong>50</strong> for normal use, <strong>10–20</strong> for testing.
                    </p>
                </div>
            </div>

        </div>

        <!-- Preview -->
        <div class="lg:col-span-2">
            <div class="sticky top-4 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Preview</h2>
                <div class="bg-[#e5ddd5] rounded-2xl p-4 min-h-32" x-show="selectedTemplate">
                    <div class="bg-white rounded-xl rounded-tl-none shadow-sm max-w-xs p-3 space-y-2">
                        <template x-if="selectedTemplate && selectedTemplate.header_type === 'text' && selectedTemplate.header_content">
                            <p class="font-semibold text-gray-800 text-sm border-b pb-2" x-text="selectedTemplate.header_content"></p>
                        </template>
                        <template x-if="selectedTemplate && selectedTemplate.header_type === 'image' && selectedTemplate.header_content">
                            <img :src="selectedTemplate.header_content.trim()" alt="Header image" class="w-full h-32 object-cover rounded-lg" @error="$el.remove()">
                        </template>
                        <template x-if="selectedTemplate && (selectedTemplate.header_type === 'video' || selectedTemplate.header_type === 'document')">
                            <div class="bg-gray-100 rounded-lg h-16 flex items-center justify-center gap-2 text-xs text-gray-500 uppercase" x-text="selectedTemplate.header_type + ' header'"></div>
                        </template>
                        <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap" x-text="previewBody || 'Select a template...'"></p>
                        <template x-if="selectedTemplate && selectedTemplate.footer_text">
                            <p class="text-xs text-gray-400" x-text="selectedTemplate.footer_text"></p>
                        </template>
                        <p class="text-right text-xs text-gray-400">12:00 <?= rx_icon('check-double', 'w-3.5 h-3.5') ?></p>
                    </div>
                    <template x-if="selectedTemplate && selectedTemplate.buttons.length > 0">
                        <div class="space-y-1 mt-2 max-w-xs">
                            <template x-for="btn in selectedTemplate.buttons" :key="btn.text">
                                <div class="bg-white rounded-xl text-center py-2 text-sm text-blue-600 font-medium shadow-sm" x-text="btn.text"></div>
                            </template>
                        </div>
                    </template>
                </div>
                <div x-show="!selectedTemplate" class="bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 p-8 text-center text-gray-400 text-sm">Select a template to preview</div>
            </div>
        </div>

    </div>

    <div class="flex gap-3 mt-6">
        <button type="submit" class="px-6 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">Save as Draft</button>
        <a href="<?= base_url('broadcasts') ?>" class="px-6 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</a>
    </div>
</form>
</div>
<?= $this->endSection() ?>
