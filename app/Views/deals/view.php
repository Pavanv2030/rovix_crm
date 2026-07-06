<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php helper(['auth', 'role']); ?>
<script>
window.__dealId     = '<?= esc($deal['id']) ?>';
window.__csrfName   = '<?= csrf_token() ?>';
window.__csrfHash   = '<?= csrf_hash() ?>';
window.__defaultMsg = <?= json_encode(
    'Hi ' . ($deal['contact_name'] ?? 'there') . ', this is a message regarding your deal *' . $deal['title'] . '*' .
    ($deal['value'] > 0 ? ' (₹' . number_format((float)$deal['value']) . ')' : '') .
    '. Please feel free to reach out if you have any questions. Thank you!',
    JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
</script>

<!-- Alpine scope wraps everything including modal -->
<div x-data="{
    showWa: false,
    waMsg: window.__defaultMsg,
    waSending: false,
    waResult: null,
    aiGenerating: false,
    openWa() { this.showWa = true; this.waMsg = window.__defaultMsg; this.waResult = null; },
    generateAiMsg() {
        this.aiGenerating = true;
        this.waResult = null;
        fetch('<?= base_url('api/deals') ?>/' + window.__dealId + '/generate-message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ [window.__csrfName]: window.__csrfHash })
        })
        .then(r => r.json())
        .then(data => {
            this.aiGenerating = false;
            if (data.success) {
                this.waMsg = data.message;
            } else {
                this.waResult = { ok: false, text: data.error || 'AI generation failed.' };
            }
        })
        .catch(() => {
            this.aiGenerating = false;
            this.waResult = { ok: false, text: 'Network error. Please try again.' };
        });
    },
    sendWhatsApp() {
        this.waSending = true;
        this.waResult  = null;
        fetch('<?= base_url('api/deals') ?>/' + window.__dealId + '/whatsapp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ message: this.waMsg, [window.__csrfName]: window.__csrfHash })
        })
        .then(r => r.json())
        .then(data => {
            this.waSending = false;
            if (data.success) {
                this.waResult = { ok: true, text: 'Message sent successfully!' };
                setTimeout(() => { this.showWa = false; this.waResult = null; }, 2000);
            } else {
                this.waResult = { ok: false, text: data.error || 'Failed to send.' };
            }
        })
        .catch(() => {
            this.waSending = false;
            this.waResult  = { ok: false, text: 'Network error. Please try again.' };
        });
    }
}">

    <!-- Page layout -->
    <div class="flex flex-col lg:flex-row gap-6">

        <!-- Left Panel -->
        <div class="w-full lg:w-72 flex-shrink-0 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between mb-3">
                    <h2 class="text-lg font-bold text-gray-900"><?= esc($deal['title']) ?></h2>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        <?= $deal['status'] === 'won' ? 'bg-green-100 text-green-700' : ($deal['status'] === 'lost' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                        <?= ucfirst($deal['status']) ?>
                    </span>
                </div>

                <div class="text-2xl font-bold text-green-600 mb-4">₹<?= number_format($deal['value']) ?></div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400 w-20 flex-shrink-0">Pipeline</span>
                        <span class="text-gray-700"><?= esc($deal['pipeline_name'] ?? '—') ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400 w-20 flex-shrink-0">Stage</span>
                        <?php if ($deal['stage_color']): ?>
                        <span class="px-2 py-0.5 rounded text-white text-xs" style="background-color: <?= esc($deal['stage_color']) ?>">
                            <?= esc($deal['stage_name'] ?? '—') ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-700"><?= esc($deal['stage_name'] ?? '—') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($deal['contact_name'] || $deal['contact_phone']): ?>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400 w-20 flex-shrink-0">Contact</span>
                        <a href="<?= base_url('contacts/' . $deal['contact_id']) ?>" class="text-blue-600 hover:underline">
                            <?= esc($deal['contact_name'] ?? $deal['contact_phone']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400 w-20 flex-shrink-0">Assigned</span>
                        <span class="text-gray-700"><?= esc($deal['agent_name'] ?? 'Unassigned') ?></span>
                    </div>
                    <?php if ($deal['expected_close_date']): ?>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400 w-20 flex-shrink-0">Close Date</span>
                        <span class="text-gray-700 <?= $deal['expected_close_date'] < date('Y-m-d') ? 'text-red-500 font-medium' : '' ?>">
                            <?= date('d M Y', strtotime($deal['expected_close_date'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-400 w-20 flex-shrink-0">Created</span>
                        <span class="text-gray-700"><?= date('d M Y', strtotime($deal['created_at'])) ?></span>
                    </div>
                </div>

                <?php if ($deal['notes']): ?>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Notes</p>
                    <p class="text-sm text-gray-600"><?= nl2br(esc($deal['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="space-y-2">
                <?php if ($deal['status'] !== 'won'): ?>
                <form action="<?= base_url('deals/' . $deal['id'] . '/status') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="won">
                    <button type="submit" class="w-full py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 font-medium">
                        <?= rx_icon('trophy', 'w-4 h-4', '!text-white') ?> Mark as Won
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($deal['status'] !== 'lost'): ?>
                <form action="<?= base_url('deals/' . $deal['id'] . '/status') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="lost">
                    <button type="submit" class="w-full py-2 bg-red-100 text-red-700 text-sm rounded-lg hover:bg-red-200 font-medium">
                        <?= rx_icon('x', 'w-4 h-4', '!text-red-700') ?> Mark as Lost
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($deal['status'] !== 'open'): ?>
                <form action="<?= base_url('deals/' . $deal['id'] . '/status') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="open">
                    <button type="submit" class="w-full py-2 bg-blue-100 text-blue-700 text-sm rounded-lg hover:bg-blue-200">
                        Reopen Deal
                    </button>
                </form>
                <?php endif; ?>

                <?php if (!empty($deal['contact_id'])): ?>
                <button type="button" @click="openWa()"
                        class="w-full py-2 flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm rounded-lg font-medium transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.122 1.532 5.852L.057 23.571a.75.75 0 0 0 .92.921l5.752-1.48A11.942 11.942 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.907 0-3.686-.52-5.205-1.424l-.373-.22-3.862.994.994-3.83-.24-.386A9.944 9.944 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                    </svg>
                    Send WhatsApp
                </button>
                <?php endif; ?>

                <a href="<?= base_url('deals/' . $deal['id'] . '/edit') ?>"
                   class="block text-center w-full py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                    Edit Deal
                </a>

                <?php if (has_min_role('admin')): ?>
                <form action="<?= base_url('deals/' . $deal['id'] . '/delete') ?>" method="POST"
                      onsubmit="return confirm('Delete this deal permanently?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full py-2 text-red-600 text-sm hover:text-red-700">Delete Deal</button>
                </form>
                <?php endif; ?>

                <a href="<?= base_url('pipelines/' . ($deal['pipeline_id'] ?? '') . '/board') ?>"
                   class="block text-center text-sm text-gray-500 hover:text-gray-700">← Back to Board</a>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="flex-1 bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-700 mb-4">Activity</h3>
            <div class="relative pl-6 border-l-2 border-gray-200 space-y-4">
                <div class="relative">
                    <div class="absolute -left-[1.6rem] w-4 h-4 rounded-full bg-blue-900 border-2 border-white"></div>
                    <div class="text-sm font-medium text-gray-800">Deal created</div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($deal['created_at'])) ?></div>
                </div>
                <?php if ($deal['status'] === 'won'): ?>
                <div class="relative">
                    <div class="absolute -left-[1.6rem] w-4 h-4 rounded-full bg-green-500 border-2 border-white"></div>
                    <div class="text-sm font-medium text-green-700">Marked as Won</div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($deal['updated_at'])) ?></div>
                </div>
                <?php elseif ($deal['status'] === 'lost'): ?>
                <div class="relative">
                    <div class="absolute -left-[1.6rem] w-4 h-4 rounded-full bg-red-500 border-2 border-white"></div>
                    <div class="text-sm font-medium text-red-700">Marked as Lost</div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($deal['updated_at'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- end flex layout -->

    <!-- WhatsApp Modal (inside x-data scope, outside flex) -->
    <div x-show="showWa"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.5); display: none;">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.122 1.532 5.852L.057 23.571a.75.75 0 0 0 .92.921l5.752-1.48A11.942 11.942 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.907 0-3.686-.52-5.205-1.424l-.373-.22-3.862.994.994-3.83-.24-.386A9.944 9.944 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 text-sm">Send WhatsApp Message</p>
                        <p class="text-xs text-gray-400">To: <?= esc($deal['contact_name'] ?? $deal['contact_phone'] ?? '—') ?></p>
                    </div>
                </div>
                <button type="button" @click="showWa = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            <!-- Message Textarea -->
            <div class="px-5 py-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Message</label>
                    <button type="button" @click="generateAiMsg()"
                            :disabled="aiGenerating"
                            class="flex items-center gap-1.5 px-3 py-1 text-xs font-medium bg-violet-50 hover:bg-violet-100 text-violet-700 rounded-lg border border-violet-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!aiGenerating">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                                <path d="M18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                            </svg>
                        </span>
                        <span x-show="aiGenerating">
                            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </span>
                        <span x-text="aiGenerating ? 'Generating...' : 'AI Write'"></span>
                    </button>
                </div>
                <textarea x-model="waMsg" rows="6"
                          class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400 resize-none"
                          placeholder="Type your message or click AI Write..."></textarea>
                <p class="text-xs text-gray-400 mt-1" x-text="waMsg.length + ' characters'"></p>
            </div>

            <!-- Result -->
            <div x-show="waResult" class="px-5 pb-3">
                <div :class="waResult && waResult.ok ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'"
                     class="text-sm px-3 py-2 rounded-lg border" x-text="waResult ? waResult.text : ''"></div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" @click="showWa = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                <button type="button" @click="sendWhatsApp()"
                        :disabled="waSending || waMsg.trim().length === 0"
                        class="px-5 py-2 bg-green-500 hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm rounded-lg font-medium flex items-center gap-2 transition-colors">
                    <svg x-show="waSending" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="waSending ? 'Sending...' : 'Send'">Send</span>
                </button>
            </div>
        </div>
    </div>

</div><!-- end x-data scope -->

<?= $this->endSection() ?>
