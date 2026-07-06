<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>[x-cloak]{display:none!important}</style>

<script>
window.__csrfName = '<?= csrf_token() ?>';
window.__csrfHash = '<?= csrf_hash() ?>';

function tplFormData(init) {
    return {
        name:           init.name           || '',
        category:       init.category       || 'utility',
        language:       init.language       || 'en',
        headerType:     init.headerType     || 'none',
        headerContent:  init.headerContent  || '',
        bodyText:       init.bodyText       || '',
        footerText:     init.footerText     || '',
        buttons:        init.buttons        || [],
        sampleValues:   init.sampleValues   || {},
        carouselCards:  init.carouselCards  || [{ imageUrl: '', bodyText: '', buttons: [], previewUrl: '', uploadState: 'idle', _uploadError: '' }],

        get variableCount() {
            const m = this.bodyText.match(/\{\{\d+\}\}/g);
            return m ? [...new Set(m)].length : 0;
        },
        get variableList() {
            const m = this.bodyText.match(/\{\{(\d+)\}\}/g);
            if (!m) return [];
            return [...new Set(m)].map(v => v.replace(/\{\{|\}\}/g, '')).sort((a, b) => +a - +b);
        },
        addVariable() {
            const n = this.variableCount + 1;
            const tail = this.bodyText.slice(-1);
            this.bodyText += (tail === '' || tail === ' ' ? '' : ' ') + '{{' + n + '}}';
        },
        addButton(type) {
            if (this.buttons.length >= 3) return;
            this.buttons.push({ type: type, text: '', url: '', phone: '' });
        },
        removeButton(idx) { this.buttons.splice(idx, 1); },

        // Carousel helpers
        addCarouselCard() {
            if (this.carouselCards.length >= 10) return;
            this.carouselCards.push({ imageUrl: '', bodyText: '', buttons: [], previewUrl: '', uploadState: 'idle' });
        },
        removeCarouselCard(idx) {
            if (this.carouselCards.length <= 1) return;
            this.carouselCards.splice(idx, 1);
        },
        addCarouselButton(cIdx) {
            if (this.carouselCards[cIdx].buttons.length >= 2) return;
            this.carouselCards[cIdx].buttons.push({ type: 'QUICK_REPLY', text: '', url: '' });
        },
        removeCarouselButton(cIdx, bIdx) {
            this.carouselCards[cIdx].buttons.splice(bIdx, 1);
        },
        async uploadCardImage(event, cIdx) {
            const file = event.target.files[0];
            if (!file) return;
            // Instant local preview via FileReader
            const reader = new FileReader();
            reader.onload = e => { this.carouselCards[cIdx].previewUrl = e.target.result; };
            reader.readAsDataURL(file);
            // Upload to server
            this.carouselCards[cIdx].uploadState = 'uploading';
            const fd = new FormData();
            fd.append('image', file);
            fd.append(window.__csrfName, window.__csrfHash);
            try {
                const r = await fetch('<?= base_url('templates/upload-media') ?>', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.url) {
                    this.carouselCards[cIdx].imageUrl    = data.url;
                    this.carouselCards[cIdx].uploadState = 'done';
                } else {
                    this.carouselCards[cIdx].uploadState = 'error';
                    this.carouselCards[cIdx]._uploadError = data.error || 'Upload failed';
                }
            } catch(e) {
                this.carouselCards[cIdx].uploadState = 'error';
                this.carouselCards[cIdx]._uploadError = 'Network error';
            }
        },

        get previewBody() {
            let t = this.bodyText;
            for (const [k, v] of Object.entries(this.sampleValues)) {
                if (v) t = t.replace(new RegExp('\\{\\{' + k + '\\}\\}', 'g'), v);
            }
            return t;
        },
        get previewBodyHtml() {
            let t = this.previewBody;
            if (!t) return '<span style="color:#bbb;font-style:italic;font-size:12px">Message body will appear here…</span>';
            t = t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            t = t.replace(/\*([^*\n]{1,200})\*/g, '<strong>$1</strong>');
            t = t.replace(/_([^_\n]{1,200})_/g, '<em>$1</em>');
            t = t.replace(/~([^~\n]{1,200})~/g, '<del>$1</del>');
            t = t.replace(/\{\{(\w+)\}\}/g, '<mark style="background:#fff3cd;color:#d97706;border-radius:3px;padding:0 3px;font-size:11px;font-family:monospace;font-style:normal">{{$1}}</mark>');
            t = t.replace(/\n/g, '<br>');
            return t;
        },
        htmlEscape(t) {
            if (!t) return '<span style="color:#bbb;font-style:italic;font-size:11px">Card body…</span>';
            t = t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            t = t.replace(/\*([^*\n]{1,200})\*/g,'<strong>$1</strong>');
            t = t.replace(/_([^_\n]{1,200})_/g,'<em>$1</em>');
            t = t.replace(/\n/g,'<br>');
            return t;
        }
    };
}
</script>

<div class="max-w-7xl mx-auto" x-data="tplFormData({})">

<!-- Page header -->
<div class="flex items-center gap-3 mb-5">
    <a href="<?= base_url('templates') ?>" class="text-gray-400 hover:text-blue-600 text-sm">← Templates</a>
    <h1 class="text-xl font-bold text-gray-900">New Template</h1>
</div>

<!-- Guideline banner -->
<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 text-xs text-blue-800">
    <p class="font-semibold mb-1 text-sm">WhatsApp Template Guidelines</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-blue-700">
        <span>• <strong>Marketing</strong> — Promotions, requires opt-in</span>
        <span>• Variables: <code class="bg-blue-100 px-1 rounded">{{1}}</code> <code class="bg-blue-100 px-1 rounded">{{2}}</code></span>
        <span>• <strong>Utility</strong> — Transactional, order updates</span>
        <span>• Formatting: <code class="bg-blue-100 px-1 rounded">*bold*</code> <code class="bg-blue-100 px-1 rounded">_italic_</code> <code class="bg-blue-100 px-1 rounded">~strike~</code></span>
        <span>• <strong>Authentication</strong> — OTP, password reset</span>
        <span>• Body max 1024 · Footer max 60 · Max 3 buttons</span>
    </div>
</div>

<form action="<?= base_url('templates') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        <!-- ── Left: Form ────────────────────────────────────────────────── -->
        <div class="flex-1 min-w-0 space-y-4">

            <!-- Basic Info -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Basic Info</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Template Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" x-model="name" required
                           placeholder="e.g. welcome_message, order_update"
                           pattern="[a-z0-9_]+"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">Lowercase letters, numbers and underscores only — no spaces</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                        <select name="category" x-model="category"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="marketing">Marketing</option>
                            <option value="utility" selected>Utility</option>
                            <option value="authentication">Authentication</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                        <select name="language" x-model="language"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <optgroup label="Common">
                                <option value="en">English</option>
                                <option value="ar">Arabic</option>
                                <option value="id">Indonesian</option>
                                <option value="ms">Malay</option>
                                <option value="es">Spanish</option>
                                <option value="pt">Portuguese</option>
                                <option value="fr">French</option>
                                <option value="de">German</option>
                            </optgroup>
                            <optgroup label="South Asian">
                                <option value="hi">Hindi</option>
                                <option value="bn">Bengali</option>
                                <option value="gu">Gujarati</option>
                                <option value="kn">Kannada</option>
                                <option value="ml">Malayalam</option>
                                <option value="mr">Marathi</option>
                                <option value="pa">Punjabi</option>
                                <option value="ta">Tamil</option>
                                <option value="te">Telugu</option>
                                <option value="ur">Urdu</option>
                            </optgroup>
                            <optgroup label="East Asian">
                                <option value="ja">Japanese</option>
                                <option value="ko">Korean</option>
                                <option value="zh">Chinese (Simplified)</option>
                                <option value="vi">Vietnamese</option>
                                <option value="th">Thai</option>
                                <option value="km">Khmer</option>
                                <option value="tl">Filipino</option>
                            </optgroup>
                            <optgroup label="European">
                                <option value="cs">Czech</option>
                                <option value="da">Danish</option>
                                <option value="el">Greek</option>
                                <option value="fi">Finnish</option>
                                <option value="ga">Irish</option>
                                <option value="he">Hebrew</option>
                                <option value="hr">Croatian</option>
                                <option value="hu">Hungarian</option>
                                <option value="it">Italian</option>
                                <option value="lt">Lithuanian</option>
                                <option value="lv">Latvian</option>
                                <option value="nl">Dutch</option>
                                <option value="nb">Norwegian</option>
                                <option value="pl">Polish</option>
                                <option value="ro">Romanian</option>
                                <option value="ru">Russian</option>
                                <option value="sk">Slovak</option>
                                <option value="sl">Slovenian</option>
                                <option value="sq">Albanian</option>
                                <option value="sr">Serbian</option>
                                <option value="sv">Swedish</option>
                                <option value="tr">Turkish</option>
                                <option value="uk">Ukrainian</option>
                            </optgroup>
                            <optgroup label="African">
                                <option value="af">Afrikaans</option>
                                <option value="sw">Swahili</option>
                                <option value="zu">Zulu</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Header <span class="font-normal normal-case text-gray-400">(optional)</span></h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="header_type" x-model="headerType"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="none">None</option>
                            <option value="text">Text</option>
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                            <option value="document">Document</option>
                            <option value="carousel">Carousel</option>
                        </select>
                    </div>
                    <div x-show="headerType !== 'none' && headerType !== 'carousel'">
                        <label class="block text-sm font-medium text-gray-700 mb-1"
                               x-text="headerType === 'text' ? 'Header Text' : 'Media URL / Handle'"></label>
                        <input type="text" name="header_content" x-model="headerContent"
                               :placeholder="headerType === 'text' ? 'Your header text' : 'https://...'"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <template x-if="headerType === 'carousel'">
                        <div class="col-span-1 flex items-end pb-1">
                            <span class="inline-flex items-center gap-1.5 text-xs px-3 py-2 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg font-medium">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="7" width="6" height="10" rx="1" stroke-width="2"/><rect x="9" y="4" width="6" height="16" rx="1" stroke-width="2"/><rect x="16" y="7" width="6" height="10" rx="1" stroke-width="2"/></svg>
                                Up to 10 image cards below
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Carousel Cards (shown only when headerType === 'carousel') -->
            <div class="bg-white rounded-xl border border-purple-200 p-5 space-y-4" x-show="headerType === 'carousel'" x-cloak>
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-xs font-bold text-purple-500 uppercase tracking-widest">Carousel Cards</h2>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="carouselCards.length + ' / 10 cards — each card needs an image, body text, and up to 2 buttons'"></p>
                    </div>
                    <button type="button" @click="addCarouselCard()"
                            x-show="carouselCards.length < 10"
                            class="text-xs px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium flex items-center gap-1.5">
                        + Add Card
                    </button>
                </div>

                <template x-for="(card, cIdx) in carouselCards" :key="cIdx">
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <!-- Card header bar -->
                        <div class="flex items-center justify-between bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                            <span class="text-xs font-semibold text-gray-600 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-[11px]"
                                      x-text="cIdx + 1"></span>
                                Card <span x-text="cIdx + 1"></span>
                            </span>
                            <button type="button" @click="removeCarouselCard(cIdx)"
                                    x-show="carouselCards.length > 1"
                                    class="text-gray-400 hover:text-red-500 text-xs px-2 py-1 rounded hover:bg-red-50 transition-colors">
                                Remove
                            </button>
                        </div>

                        <div class="p-4 space-y-3">
                            <!-- Image: URL or Upload -->
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    Card Image <span class="text-red-500">*</span>
                                </label>

                                <!-- Preview thumbnail (shown once an image is chosen) -->
                                <div x-show="card.previewUrl || card.imageUrl" class="mb-2 relative inline-block">
                                    <img :src="card.previewUrl || card.imageUrl" alt="Preview"
                                         class="h-20 w-32 object-cover rounded-lg border border-gray-200 shadow-sm">
                                    <!-- Upload spinner overlay -->
                                    <div x-show="card.uploadState === 'uploading'"
                                         class="absolute inset-0 bg-white/70 flex items-center justify-center rounded-lg">
                                        <svg class="w-5 h-5 text-purple-600 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                        </svg>
                                    </div>
                                    <!-- Done tick -->
                                    <div x-show="card.uploadState === 'done'"
                                         class="absolute top-1 right-1 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <!-- Remove / change button -->
                                    <button type="button"
                                            @click="card.imageUrl='';card.previewUrl='';card.uploadState='idle'"
                                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 rounded-full text-white text-xs flex items-center justify-center hover:bg-red-600 shadow">×</button>
                                </div>

                                <!-- Input row (hidden once image is selected) -->
                                <div x-show="!card.previewUrl && !card.imageUrl" class="flex gap-2 items-stretch">
                                    <input type="text" x-model="card.imageUrl"
                                           placeholder="Paste image URL…"
                                           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                                    <span class="flex items-center text-xs text-gray-400 font-medium">or</span>
                                    <!-- Upload from local -->
                                    <label class="flex items-center gap-1.5 cursor-pointer px-3 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-lg text-xs font-medium transition-colors flex-shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12V4m0 0L8 8m4-4l4 4"/></svg>
                                        Upload
                                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                                               class="hidden"
                                               @change="uploadCardImage($event, cIdx)">
                                    </label>
                                </div>

                                <!-- Error message -->
                                <p x-show="card.uploadState === 'error'"
                                   class="text-xs text-red-500 mt-1"
                                   x-text="card._uploadError || 'Upload failed'"></p>
                            </div>

                            <!-- Card body text -->
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Card Body Text <span class="text-red-500">*</span></label>
                                <textarea x-model="card.bodyText"
                                          :name="'carousel_card_body_' + cIdx"
                                          rows="2" maxlength="160"
                                          placeholder="Describe this card…"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none"></textarea>
                                <p class="text-right text-[10px] text-gray-400 mt-0.5" x-text="(card.bodyText || '').length + ' / 160'"></p>
                            </div>

                            <!-- Card buttons (max 2) -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-600">Buttons <span class="text-gray-400">(max 2)</span></span>
                                    <div class="flex gap-1" x-show="card.buttons.length < 2">
                                        <button type="button" @click="addCarouselButton(cIdx)"
                                                class="text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded border border-gray-200 font-medium">Quick Reply</button>
                                        <button type="button" @click="carouselCards[cIdx].buttons.push({ type: 'URL', text: '', url: '' })"
                                                class="text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded border border-gray-200 font-medium inline-flex items-center gap-1"><?= rx_icon('link', 'w-3.5 h-3.5') ?> URL</button>
                                    </div>
                                </div>
                                <template x-for="(cbtn, bIdx) in card.buttons" :key="bIdx">
                                    <div class="flex items-center gap-2 bg-gray-50 rounded-lg p-2 border border-gray-200">
                                        <span class="text-[10px] font-semibold text-gray-500 bg-white border border-gray-200 rounded px-1.5 py-0.5 flex-shrink-0"
                                              x-text="cbtn.type === 'URL' ? 'URL' : 'Reply'"></span>
                                        <input type="text" x-model="cbtn.text"
                                               :name="'carousel_card_' + cIdx + '_btn_text_' + bIdx"
                                               placeholder="Button label"
                                               class="flex-1 border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-400">
                                        <template x-if="cbtn.type === 'URL'">
                                            <input type="text" x-model="cbtn.url"
                                                   :name="'carousel_card_' + cIdx + '_btn_url_' + bIdx"
                                                   placeholder="https://..."
                                                   class="flex-1 border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-400">
                                        </template>
                                        <button type="button" @click="removeCarouselButton(cIdx, bIdx)"
                                                class="text-gray-400 hover:text-red-500 text-base leading-none w-5">×</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <input type="hidden" name="carousel_cards" :value="JSON.stringify(carouselCards)">
            </div>

            <!-- Body -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                        Message Body <span class="text-red-500 font-normal">*</span>
                    </h2>
                    <button type="button" @click="addVariable()"
                            class="flex items-center gap-1 text-xs px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg border border-blue-200 font-medium transition-colors">
                        + Add Variable
                    </button>
                </div>
                <textarea name="body_text" x-model="bodyText" rows="6" required maxlength="1024"
                          placeholder="Hi {{1}}, your order *{{2}}* has been confirmed. Track here: {{3}}"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none font-mono"></textarea>
                <div class="flex items-center justify-between text-xs text-gray-400">
                    <span x-text="variableCount + ' variable(s) · use *bold* _italic_ ~strikethrough~'"></span>
                    <span :class="bodyText.length > 900 ? 'text-orange-500 font-semibold' : ''"
                          x-text="bodyText.length + ' / 1024'"></span>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Footer <span class="font-normal normal-case text-gray-400">(optional)</span></h2>
                <input type="text" name="footer_text" x-model="footerText" maxlength="60"
                       placeholder="Reply STOP to unsubscribe"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400" x-text="footerText.length + ' / 60'"></p>
            </div>

            <!-- Buttons -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Buttons <span class="font-normal normal-case">(max 3)</span></h2>
                    <div class="flex gap-1.5" x-show="buttons.length < 3">
                        <button type="button" @click="addButton('QUICK_REPLY')"
                                class="text-xs px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-lg border border-gray-200 font-medium">Quick Reply</button>
                        <button type="button" @click="addButton('URL')"
                                class="text-xs px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-lg border border-gray-200 font-medium inline-flex items-center gap-1"><?= rx_icon('link', 'w-4 h-4') ?> URL</button>
                        <button type="button" @click="addButton('PHONE_NUMBER')"
                                class="text-xs px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-lg border border-gray-200 font-medium inline-flex items-center gap-1"><?= rx_icon('phone', 'w-4 h-4') ?> Phone</button>
                    </div>
                </div>
                <div x-show="buttons.length === 0" class="text-xs text-gray-400 text-center py-3 border-2 border-dashed border-gray-200 rounded-lg">
                    No buttons added yet — click above to add
                </div>
                <template x-for="(btn, idx) in buttons" :key="idx">
                    <div class="flex items-center gap-2 bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <span class="text-xs font-semibold text-gray-500 w-22 flex-shrink-0 bg-white border border-gray-200 rounded px-2 py-1"
                              x-text="btn.type === 'QUICK_REPLY' ? 'Reply' : btn.type === 'URL' ? 'URL' : 'Phone'"></span>
                        <input type="text" :name="'btn_text_' + idx" x-model="btn.text" placeholder="Button label"
                               class="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <template x-if="btn.type === 'URL'">
                            <input type="text" :name="'btn_url_' + idx" x-model="btn.url" placeholder="https://..."
                                   class="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </template>
                        <template x-if="btn.type === 'PHONE_NUMBER'">
                            <input type="text" :name="'btn_phone_' + idx" x-model="btn.phone" placeholder="+91..."
                                   class="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </template>
                        <button type="button" @click="removeButton(idx)"
                                class="text-gray-400 hover:text-red-500 text-xl leading-none w-6 flex-shrink-0">×</button>
                    </div>
                </template>
                <input type="hidden" name="buttons" :value="buttons.length ? JSON.stringify(buttons) : ''">
            </div>

            <!-- Sample Values (shown only when variables exist) -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3" x-show="variableCount > 0">
                <div>
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Sample Values <span class="text-red-500">*</span></h2>
                    <p class="text-xs text-gray-400 mt-1">Required by Meta for template approval — shows reviewers what real data looks like</p>
                </div>
                <template x-for="v in variableList" :key="v">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-mono text-blue-600 bg-blue-50 rounded px-2 py-1 w-14 text-center flex-shrink-0"
                              x-text="'{{' + v + '}}'"></span>
                        <input type="text"
                               :placeholder="'Sample value for {{' + v + '}}'"
                               @input="sampleValues[v] = $event.target.value"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </template>
                <input type="hidden" name="sample_values"
                       :value="variableCount > 0 ? JSON.stringify({ body: variableList.map(v => sampleValues[v] || '') }) : ''">
            </div>

        </div><!-- /form -->

        <!-- ── Right: Phone Preview ──────────────────────────────────────── -->
        <div class="lg:w-72 flex-shrink-0 lg:sticky lg:top-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 text-center">Live Preview</p>

            <!-- Phone outer frame -->
            <div class="relative mx-auto bg-gray-900 rounded-[2.8rem] shadow-2xl border-[6px] border-gray-800" style="width:272px">

                <!-- Top notch -->
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-20 h-5 bg-gray-900 rounded-b-2xl z-10"></div>

                <!-- Screen -->
                <div class="bg-white rounded-[2.2rem] overflow-hidden flex flex-col" style="height:540px">

                    <!-- Status bar -->
                    <div class="bg-[#075E54] flex items-center justify-between px-5 pt-5 pb-1 flex-shrink-0">
                        <span class="text-white text-xs font-semibold">12:02</span>
                        <div class="flex items-center gap-1 text-white text-xs">
                            <svg width="12" height="10" viewBox="0 0 12 10" fill="currentColor"><rect x="0" y="5" width="2" height="5"/><rect x="3" y="3" width="2" height="7"/><rect x="6" y="1" width="2" height="9"/><rect x="9" y="0" width="2" height="10"/></svg>
                            <svg width="14" height="10" viewBox="0 0 14 10" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 7 C3 4 5 2.5 7 2.5 S11 4 13 7"/><path d="M3.5 8.5 C4.5 7 5.5 6 7 6 S9.5 7 10.5 8.5"/><circle cx="7" cy="10" r="1.2" fill="currentColor" stroke="none"/></svg>
                            <span class="text-[10px] border border-white/50 rounded-sm px-0.5">▮▮▮</span>
                        </div>
                    </div>

                    <!-- WA App bar -->
                    <div class="bg-[#075E54] px-3 pb-2 flex items-center gap-2.5 flex-shrink-0">
                        <span class="text-white/70 text-base">←</span>
                        <div class="w-8 h-8 bg-green-400 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0">P</div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white text-sm font-semibold leading-tight truncate" x-text="name || 'Preview'"></div>
                            <div class="text-green-200 text-[10px] opacity-80">online</div>
                        </div>
                        <div class="flex items-center gap-2 text-white/70 text-base"><?= rx_icon('video', 'w-4 h-4', '!text-white/70') ?> <?= rx_icon('phone', 'w-4 h-4', '!text-white/70') ?></div>
                    </div>

                    <!-- Chat background -->
                    <div class="flex-1 overflow-y-auto p-3" style="background:#e5ddd5">
                        <div class="flex flex-col gap-1.5 pb-2">

                            <!-- Standard message bubble (not carousel) -->
                            <template x-if="headerType !== 'carousel'">
                                <div class="ml-auto max-w-[92%]">
                                    <div class="bg-white rounded-xl rounded-tr-none shadow-sm overflow-hidden">
                                        <template x-if="headerType === 'image'">
                                            <div class="bg-gray-200" style="height:110px">
                                                <img x-show="headerContent" :src="headerContent"
                                                     @error="$el.style.display='none'" @load="$el.style.display=''"
                                                     class="w-full h-full object-cover" style="height:110px">
                                                <div x-show="!headerContent" class="flex flex-col items-center justify-center gap-1 h-full">
                                                    <?= rx_icon('image', 'w-8 h-8', 'mx-auto') ?>
                                                    <span class="text-xs text-gray-500">Image</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="headerType === 'video'">
                                            <div class="bg-gray-700 flex flex-col items-center justify-center gap-1" style="height:110px">
                                                <?= rx_icon('video', 'w-8 h-8', 'mx-auto !text-gray-300') ?>
                                                <span class="text-xs text-gray-300">Video</span>
                                            </div>
                                        </template>
                                        <template x-if="headerType === 'document'">
                                            <div class="bg-gray-100 flex items-center justify-center gap-2" style="height:52px">
                                                <?= rx_icon('document', 'w-6 h-6') ?>
                                                <span class="text-xs text-gray-600">Document</span>
                                            </div>
                                        </template>
                                        <template x-if="headerType === 'text' && headerContent">
                                            <div class="px-3 pt-2.5 pb-1 text-xs font-bold text-gray-900"
                                                 x-text="headerContent"></div>
                                        </template>
                                        <div class="px-3 py-2 text-xs text-gray-800 leading-relaxed"
                                             x-html="previewBodyHtml"
                                             style="min-height:32px;word-break:break-word"></div>
                                        <div x-show="footerText" class="px-3 pb-1 text-[10px] text-gray-400"
                                             x-text="footerText"></div>
                                        <div class="px-3 pb-2 flex justify-end">
                                            <span class="text-[10px] text-gray-400">12:02 <?= rx_icon('check-double', 'w-3.5 h-3.5') ?></span>
                                        </div>
                                    </div>
                                    <template x-for="(btn, i) in buttons" :key="i">
                                        <div class="bg-white mt-0.5 rounded-xl text-center py-2 shadow-sm text-xs text-blue-600 font-semibold flex items-center justify-center gap-1.5"
                                             style="border-top:1px solid #f0f0f0">
                                            <span x-text="btn.text || 'Button'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Carousel preview -->
                            <template x-if="headerType === 'carousel'">
                                <div class="w-full">
                                    <!-- Main body bubble (above carousel) -->
                                    <template x-if="bodyText">
                                        <div class="ml-auto max-w-[92%] mb-1.5">
                                            <div class="bg-white rounded-xl rounded-tr-none shadow-sm overflow-hidden">
                                                <div class="px-3 py-2 text-xs text-gray-800 leading-relaxed"
                                                     x-html="previewBodyHtml"
                                                     style="word-break:break-word"></div>
                                                <div class="px-3 pb-2 flex justify-end">
                                                    <span class="text-[10px] text-gray-400">12:02 <?= rx_icon('check-double', 'w-3.5 h-3.5') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Carousel cards (horizontal scroll) -->
                                    <div class="flex gap-2 overflow-x-auto pb-1" style="scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch">
                                        <template x-for="(card, ci) in carouselCards" :key="ci">
                                            <div class="flex-shrink-0 bg-white rounded-xl shadow-sm overflow-hidden flex flex-col"
                                                 style="width:148px;scroll-snap-align:start">
                                                <!-- Image -->
                                                <div class="flex-shrink-0 relative overflow-hidden" style="height:80px">
                                                    <template x-if="card.imageUrl">
                                                        <img :src="card.imageUrl" alt=""
                                                             class="w-full h-full object-cover"
                                                             @error="$el.style.display='none'">
                                                    </template>
                                                    <template x-if="!card.imageUrl">
                                                        <div class="w-full h-full bg-gray-200 flex flex-col items-center justify-center gap-0.5">
                                                            <?= rx_icon('image', 'w-6 h-6', 'mx-auto') ?>
                                                            <span class="text-[9px] text-gray-400">Image</span>
                                                        </div>
                                                    </template>
                                                    <!-- Card number badge -->
                                                    <span class="absolute top-1 left-1 w-4 h-4 rounded-full bg-purple-600 text-white text-[9px] font-bold flex items-center justify-center"
                                                          x-text="ci + 1"></span>
                                                </div>
                                                <!-- Card body -->
                                                <div class="px-2 py-1.5 text-[10px] text-gray-800 leading-snug flex-1"
                                                     style="min-height:28px;word-break:break-word;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden"
                                                     x-html="htmlEscape(card.bodyText)"></div>
                                                <!-- Card buttons -->
                                                <template x-for="(cbtn, bi) in card.buttons" :key="bi">
                                                    <div class="border-t border-gray-100 text-center py-1.5 text-[10px] text-blue-600 font-semibold flex items-center justify-center gap-1">
                                                        <span x-text="cbtn.text || 'Button'"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                    <!-- Scroll hint dots -->
                                    <div class="flex gap-1 justify-center mt-1.5" x-show="carouselCards.length > 1">
                                        <template x-for="(c, di) in carouselCards" :key="di">
                                            <div class="w-1.5 h-1.5 rounded-full"
                                                 :class="di === 0 ? 'bg-gray-600' : 'bg-gray-300'"></div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                        </div>
                    </div>

                    <!-- Message input bar -->
                    <div class="flex-shrink-0 flex items-center gap-2 px-2 py-2" style="background:#f0f0f0">
                        <span class="px-1"><?= rx_icon('smile', 'w-5 h-5', '!text-gray-400') ?></span>
                        <div class="flex-1 bg-white rounded-full px-3 py-1.5 text-xs text-gray-400">Message</div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm flex-shrink-0"
                             style="background:#25D366"><?= rx_icon('mic', 'w-4 h-4', '!text-white') ?></div>
                    </div>

                </div><!-- /screen -->
            </div><!-- /phone shell -->

            <!-- Hint below phone -->
            <p class="text-xs text-gray-400 text-center mt-3">Preview updates as you type</p>
        </div><!-- /preview -->

    </div><!-- /flex row -->

    <!-- Save bar -->
    <div class="flex gap-3 mt-6 pt-4 border-t border-gray-200">
        <button type="submit"
                class="px-6 py-2.5 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium shadow-sm">
            Save as Draft
        </button>
        <a href="<?= base_url('templates') ?>"
           class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 font-medium">
            Cancel
        </a>
    </div>

</form>
</div>

<?= $this->endSection() ?>
