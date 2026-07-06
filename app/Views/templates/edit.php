<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<script>
window.__tpl = <?= json_encode([
    'name'          => $template['name'],
    'category'      => $template['category'],
    'language'      => $template['language'],
    'headerType'    => $template['header_type'],
    'headerContent' => $template['header_content'] ?? '',
    'bodyText'      => $template['body_text'],
    'footerText'    => $template['footer_text'] ?? '',
    'buttons'       => json_decode($template['buttons'] ?? '[]', true) ?? [],
    'sampleValues'  => array_combine(
        array_map(fn($i) => (string)($i + 1), array_keys(json_decode($template['sample_values'] ?? '{}', true)['body'] ?? [])),
        json_decode($template['sample_values'] ?? '{}', true)['body'] ?? []
    ),
], JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function tplFormData(init) {
    return {
        name:          init.name          || '',
        category:      init.category      || 'utility',
        language:      init.language      || 'en',
        headerType:    init.headerType    || 'none',
        headerContent: init.headerContent || '',
        bodyText:      init.bodyText      || '',
        footerText:    init.footerText    || '',
        buttons:       init.buttons       || [],
        sampleValues:  init.sampleValues  || {},

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
        }
    };
}
</script>

<div class="max-w-7xl mx-auto" x-data="tplFormData(window.__tpl)">

<!-- Page header -->
<div class="flex items-center gap-3 mb-5">
    <a href="<?= base_url('templates/' . $template['id']) ?>" class="text-gray-400 hover:text-blue-600 text-sm">← Template</a>
    <h1 class="text-xl font-bold text-gray-900">Edit Template</h1>
    <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full font-mono"><?= esc($template['name']) ?></span>
</div>

<?php if (session()->getFlashdata('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>

<form action="<?= base_url('templates/' . $template['id']) ?>" method="POST">
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
                           pattern="[a-z0-9_]+"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">Lowercase letters, numbers and underscores only</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" x-model="category"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="marketing">Marketing</option>
                            <option value="utility">Utility</option>
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
                        </select>
                    </div>
                    <div x-show="headerType !== 'none'">
                        <label class="block text-sm font-medium text-gray-700 mb-1"
                               x-text="headerType === 'text' ? 'Header Text' : 'Media URL / Handle'"></label>
                        <input type="text" name="header_content" x-model="headerContent"
                               :placeholder="headerType === 'text' ? 'Your header text' : 'https://...'"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
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
                    No buttons — click above to add
                </div>
                <template x-for="(btn, idx) in buttons" :key="idx">
                    <div class="flex items-center gap-2 bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <span class="text-xs font-semibold text-gray-500 flex-shrink-0 bg-white border border-gray-200 rounded px-2 py-1"
                              x-text="btn.type === 'QUICK_REPLY' ? 'Reply' : btn.type === 'URL' ? 'URL' : 'Phone'"></span>
                        <input type="text" x-model="btn.text" placeholder="Button label"
                               class="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <template x-if="btn.type === 'URL'">
                            <input type="text" x-model="btn.url" placeholder="https://..."
                                   class="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </template>
                        <template x-if="btn.type === 'PHONE_NUMBER'">
                            <input type="text" x-model="btn.phone" placeholder="+91..."
                                   class="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </template>
                        <button type="button" @click="removeButton(idx)"
                                class="text-gray-400 hover:text-red-500 text-xl leading-none w-6 flex-shrink-0">×</button>
                    </div>
                </template>
                <input type="hidden" name="buttons" :value="buttons.length ? JSON.stringify(buttons) : ''">
            </div>

            <!-- Sample Values -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3" x-show="variableCount > 0">
                <div>
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Sample Values <span class="text-red-500">*</span></h2>
                    <p class="text-xs text-gray-400 mt-1">Required by Meta for template approval</p>
                </div>
                <template x-for="v in variableList" :key="v">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-mono text-blue-600 bg-blue-50 rounded px-2 py-1 w-14 text-center flex-shrink-0"
                              x-text="'{{' + v + '}}'"></span>
                        <input type="text"
                               :value="sampleValues[v] || ''"
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

                            <div class="ml-auto max-w-[92%]">
                                <!-- Card -->
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

            <p class="text-xs text-gray-400 text-center mt-3">Preview updates as you type</p>
        </div><!-- /preview -->

    </div><!-- /flex row -->

    <!-- Save bar -->
    <div class="flex gap-3 mt-6 pt-4 border-t border-gray-200">
        <button type="submit"
                class="px-6 py-2.5 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium shadow-sm">
            Save Changes
        </button>
        <a href="<?= base_url('templates/' . $template['id']) ?>"
           class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 font-medium">
            Cancel
        </a>
    </div>

</form>
</div>

<?= $this->endSection() ?>
