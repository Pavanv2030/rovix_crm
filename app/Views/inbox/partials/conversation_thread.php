<?php helper(['format', 'auth', 'role']); ?>
<script>
window.__inboxTemplates = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'body' => $t['body_text'], 'language' => $t['language']], $templates ?? [])) ?>;
</script>

<div class="flex flex-col h-full" x-data="{
    messageText: '',
    sending: false,
    showTagModal: false,
    showNoteModal: false,
    showTemplateModal: false,
    showCatalogModal: false,
    catalogProducts: [],
    catalogLoading: false,
    showBookingModal: false,
    bookingTypes: [],
    bookingLoading: false,
    selectedTemplateId: '',
    templatePreview: '',
    noteText: '',
    translating: false,
    rewriting: false,
    showTranslateMenu: false,
    async translateOutgoing(lang) {
        if (!this.messageText.trim() || this.translating) return;
        this.showTranslateMenu = false;
        this.translating = true;
        const fd = new FormData();
        fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
        fd.append('text', this.messageText);
        fd.append('target_language', lang);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        try {
            const res  = await fetch('<?= base_url('api/ai/translate-outgoing') ?>', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) this.messageText = data.text;
            else alert(data.error || 'Translation failed');
        } catch(e) { alert('Network error'); }
        this.translating = false;
    },
    async rewriteOutgoing() {
        if (!this.messageText.trim() || this.rewriting) return;
        this.rewriting = true;
        const fd = new FormData();
        fd.append('text', this.messageText);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        try {
            const res  = await fetch('<?= base_url('api/ai/rewrite') ?>', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) this.messageText = data.text;
            else alert(data.error || 'Rewrite failed');
        } catch(e) { alert('Network error'); }
        this.rewriting = false;
    },
    async sendMessage() {
        if (!this.messageText.trim()) return;
        this.sending = true;
        const formData = new FormData();
        formData.append('conversation_id', '<?= esc($conversation['id']) ?>');
        formData.append('content_type', 'text');
        formData.append('content_text', this.messageText);
        formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        try {
            const res = await fetch('<?= base_url('api/whatsapp/send') ?>', { method: 'POST', body: formData });
            if (res.ok) { this.messageText = ''; window.RovixNav.refresh(); }
            else { alert('Failed to send message'); }
        } catch(e) { alert('Network error'); }
        this.sending = false;
    },
    async changeStatus(status) {
        const fd = new FormData();
        fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
        fd.append('status', status);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        await fetch('<?= base_url('api/conversations/status') ?>', { method: 'POST', body: fd });
        window.RovixNav.refresh();
    },
    async changeLeadStatus(statusId) {
        const fd = new FormData();
        fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
        fd.append('lead_status_id', statusId);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const res  = await fetch('<?= base_url('api/conversations/lead-status') ?>', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) window.RovixNav.refresh();
        else alert(data.error || 'Failed to update status');
    },
    onTemplateChange(id) {
        const t = (window.__inboxTemplates || []).find(t => t.id === id);
        this.templatePreview = t ? t.body : '';
    },
    async sendTemplate() {
        if (!this.selectedTemplateId) return;
        this.sending = true;
        const fd = new FormData();
        fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
        fd.append('template_id', this.selectedTemplateId);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        try {
            const res = await fetch('<?= base_url('api/whatsapp/send-template') ?>', { method: 'POST', body: fd });
            if (res.ok) { this.showTemplateModal = false; this.selectedTemplateId = ''; this.templatePreview = ''; window.RovixNav.refresh(); }
            else { const d = await res.json(); alert(d.error || 'Failed to send template'); }
        } catch(e) { alert('Network error'); }
        this.sending = false;
    },
    async addNote() {
        if (!this.noteText.trim()) return;
        const fd = new FormData();
        fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
        fd.append('note_text', this.noteText);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const res = await fetch('<?= base_url('api/conversations/note') ?>', { method: 'POST', body: fd });
        if (res.ok) { this.noteText = ''; this.showNoteModal = false; alert('Note saved!'); }
    },
    async openBookingModal() {
        this.showBookingModal = true;
        if (this.bookingTypes.length) return;
        this.bookingLoading = true;
        try {
            const res  = await fetch('<?= base_url('api/appointments/types') ?>');
            const data = await res.json();
            this.bookingTypes = data.types || [];
        } catch(e) {}
        this.bookingLoading = false;
    },
    async sendBookingFlow(typeId) {
        this.sending = true;
        this.showBookingModal = false;
        try {
            const fd = new FormData();
            fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
            fd.append('appointment_type_id', typeId);
            fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            const res  = await fetch('<?= base_url('api/appointments/send-flow') ?>', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { this.sending = false; window.RovixNav.refresh(); return; }
            alert('Error: ' + (data.error || 'Failed to send booking flow'));
        } catch(e) { alert('Network error'); }
        this.sending = false;
    },
    async openCatalogModal() {
        this.showCatalogModal = true;
        if (this.catalogProducts.length) return;
        this.catalogLoading = true;
        try {
            const res  = await fetch('<?= base_url('api/catalog/products') ?>');
            const data = await res.json();
            this.catalogProducts = data.products || [];
        } catch(e) {}
        this.catalogLoading = false;
    },
    async sendAllCatalog() {
        this.sending = true;
        this.showCatalogModal = false;
        try {
            const fd = new FormData();
            fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
            fd.append('body_text', 'Browse our products!');
            fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            const res  = await fetch('<?= base_url('api/catalog/send-catalog') ?>', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { this.sending = false; window.RovixNav.refresh(); return; }
            alert('Error: ' + (data.error || 'Failed to send catalog'));
        } catch(e) { alert('Network error'); }
        this.sending = false;
    },
    async sendProduct(retailerId, productName) {
        this.sending = true;
        this.showCatalogModal = false;
        try {
            const fd = new FormData();
            fd.append('conversation_id', '<?= esc($conversation['id']) ?>');
            fd.append('product_retailer_id', retailerId);
            fd.append('body_text', 'Check out this product!');
            fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            const r    = await fetch('<?= base_url('api/catalog/send-product') ?>', { method: 'POST', body: fd });
            const json = await r.json();
            if (json.success) { this.sending = false; window.RovixNav.refresh(); return; }
            alert('Error: ' + (json.error || 'Failed to send product'));
        } catch(e) { alert('Network error'); }
        this.sending = false;
    },
    async reactToMessage(messageId, emoji) {
        const fd = new FormData();
        fd.append('message_id', messageId);
        fd.append('emoji', emoji);
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        try {
            const res = await fetch('<?= base_url('api/whatsapp/react') ?>', { method: 'POST', body: fd });
            const d   = await res.json();
            if (d.success) window.RovixNav.refresh();
            else alert(d.error || 'Failed to react');
        } catch(e) { alert('Network error'); }
    }
}">

    <!-- Catalog Product Picker Modal -->
    <div x-show="showCatalogModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @click.self="showCatalogModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 flex-shrink-0">
                <h3 class="font-semibold text-gray-900">Send Catalog / Product</h3>
                <button @click="showCatalogModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <!-- Send full catalog button -->
            <div class="p-4 border-b border-gray-100 flex-shrink-0">
                <button @click="sendAllCatalog()"
                        class="w-full flex items-center gap-3 px-4 py-3 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-xl transition-colors text-left">
                    <div class="w-9 h-9 rounded-lg bg-amber-500 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-amber-800">Send Full Catalog</p>
                        <p class="text-xs text-amber-600">Sends a "View Catalog" button with all products</p>
                    </div>
                </button>
            </div>

            <!-- Product list -->
            <div class="flex-1 overflow-y-auto p-4">
                <p class="text-xs text-gray-500 mb-3 font-medium uppercase tracking-wide">Or send a specific product</p>

                <div x-show="catalogLoading" class="text-center py-8 text-gray-400 text-sm">Loading products...</div>
                <div x-show="!catalogLoading && catalogProducts.length === 0" class="text-center py-8 text-gray-400 text-sm">
                    No products found. Sync catalog first.
                </div>

                <template x-for="p in catalogProducts" :key="p.id">
                    <div class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl border border-gray-100 mb-2">
                        <img x-show="p.image_url" :src="p.image_url" class="w-12 h-12 rounded-lg object-cover flex-shrink-0 bg-gray-100">
                        <div x-show="!p.image_url" class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate" x-text="p.name"></p>
                            <p class="text-xs text-gray-400 truncate" x-text="p.retailer_id"></p>
                            <p class="text-xs font-bold text-amber-600 mt-0.5" x-text="p.price || ''"></p>
                        </div>
                        <button @click="sendProduct(p.retailer_id, p.name)"
                                class="px-3 py-1.5 text-xs font-medium text-white bg-blue-900 rounded-lg hover:bg-blue-800 flex-shrink-0">
                            Send
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Appointment Booking Modal -->
    <div x-show="showBookingModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @click.self="showBookingModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-900">Send Appointment Booking</h3>
                <button @click="showBookingModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <div class="p-4 max-h-96 overflow-y-auto">
                <p class="text-xs text-gray-500 mb-3">Select an appointment type to send a booking flow. Customer will see a native calendar picker in WhatsApp.</p>
                <div x-show="bookingLoading" class="text-center py-8 text-gray-400 text-sm">Loading types...</div>
                <div x-show="!bookingLoading && bookingTypes.length === 0" class="text-center py-8">
                    <p class="text-sm text-gray-500 mb-3">No appointment types found.</p>
                    <a href="<?= base_url('appointments/types') ?>" target="_blank"
                       class="text-xs text-blue-600 hover:underline">Create appointment types →</a>
                </div>
                <template x-for="t in bookingTypes" :key="t.id">
                    <div class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl border border-gray-100 mb-2">
                        <div class="w-10 h-10 rounded-xl bg-sky-100 flex items-center justify-center flex-shrink-0"><?= rx_icon('calendar', 'w-5 h-5') ?></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900" x-text="t.name"></p>
                            <p class="text-xs text-gray-400" x-text="t.duration_minutes + ' min · ' + t.currency + ' ' + parseFloat(t.price).toFixed(2)"></p>
                        </div>
                        <button @click="sendBookingFlow(t.id)"
                                class="px-3 py-1.5 text-xs font-medium text-white bg-sky-600 rounded-lg hover:bg-sky-700 flex-shrink-0">
                            Send
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Thread Header -->
    <div class="px-4 py-2.5 flex items-center justify-between flex-shrink-0" style="background:#f0f2f5;">
        <div class="flex items-center">
            <div class="w-10 h-10 rounded-full bg-[#dfe5e7] text-[#54656f] flex items-center justify-center font-medium text-base mr-3">
                <?= strtoupper(substr($contact['name'] ?? $contact['phone'] ?? '?', 0, 1)) ?>
            </div>
            <div>
                <div class="font-medium text-[15px] text-[#111b21] leading-tight flex items-center gap-2">
                    <?= esc($contact['name'] ?? 'Unknown') ?>
                    <!-- 24h window badge -->
                    <span class="wa-window-timer wa-window-badge text-xs px-2 py-0.5 rounded-full font-medium"
                          data-ts="<?= esc($conversation['last_customer_message_at'] ?? '') ?>"></span>
                </div>
                <div class="text-[13px] text-[#667781]"><?= esc(format_phone($contact['phone'] ?? '')) ?></div>
            </div>
            <!-- Tags -->
            <div class="ml-4 flex flex-wrap gap-1">
                <?php foreach ($tags as $tag): ?>
                <span class="text-xs px-2 py-0.5 rounded-full text-white" style="background-color: <?= esc($tag['color'] ?? '#3B82F6') ?>">
                    <?= esc($tag['name']) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-1">
            <?php if (!empty($leadStatuses)): ?>
            <?php $currentLeadStatus = null; foreach ($leadStatuses as $ls) { if ($ls['id'] === ($conversation['lead_status_id'] ?? null)) { $currentLeadStatus = $ls; break; } } ?>
            <select @change="changeLeadStatus($event.target.value)"
                    class="text-[13px] font-medium border-none rounded-full px-3 py-1.5 mr-1 focus:outline-none cursor-pointer"
                    style="background-color: <?= $currentLeadStatus['color'] ?? '#F3F4F6' ?>1a; color: <?= $currentLeadStatus['color'] ?? '#54656f' ?>;">
                <option value="" <?= !$currentLeadStatus ? 'selected' : '' ?>>Lead Status: None</option>
                <?php foreach ($leadStatuses as $ls): ?>
                <option value="<?= esc($ls['id']) ?>" <?= ($currentLeadStatus && $currentLeadStatus['id'] === $ls['id']) ? 'selected' : '' ?>><?= esc($ls['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($conversation['status'] !== 'closed'): ?>
            <button @click="changeStatus('closed')"
                    class="px-3 py-1.5 text-[13px] font-medium text-[#54656f] hover:bg-[#d9dde0] rounded-full transition-colors">
                Close
            </button>
            <?php else: ?>
            <button @click="changeStatus('open')"
                    class="px-3 py-1.5 text-[13px] font-medium text-[#008069] hover:bg-[#d9dde0] rounded-full transition-colors">
                Reopen
            </button>
            <?php endif; ?>

            <!-- More Actions -->
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" title="Menu" class="p-2 text-[#54656f] hover:bg-[#d9dde0] rounded-full transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
                    </svg>
                </button>
                <div x-show="open" @click.away="open = false" x-cloak
                     class="absolute right-0 mt-1 w-52 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-50">
                    <button @click="showNoteModal = true; open = false"
                            class="flex items-center gap-2.5 w-full text-left px-4 py-2.5 text-sm text-[#111b21] hover:bg-[#f5f6f6]">
                        <?= rx_icon('note', 'w-4 h-4') ?> Add Note
                    </button>
                    <button @click="showTagModal = true; open = false"
                            class="flex items-center gap-2.5 w-full text-left px-4 py-2.5 text-sm text-[#111b21] hover:bg-[#f5f6f6]">
                        <?= rx_icon('tag', 'w-4 h-4') ?> Add Tag
                    </button>
                    <a href="<?= base_url('contacts/' . ($contact['id'] ?? $conversation['contact_id'] ?? '')) ?>"
                       class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#111b21] hover:bg-[#f5f6f6]">
                        <?= rx_icon('user', 'w-4 h-4') ?> View Contact
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Thread -->
    <div class="flex-1 overflow-y-auto p-4 space-y-3" id="message-thread">
        <?php if (empty($messages)): ?>
            <div class="text-center text-gray-400 text-sm py-8">No messages yet. Start a conversation!</div>
        <?php endif; ?>

        <?php
        $lastDate = null;
        foreach ($messages as $msg):
            $msgDate = date('Y-m-d', strtotime($msg['created_at']));
            $today   = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            if ($msgDate !== $lastDate):
                $lastDate = $msgDate;
                $dateLabel = $msgDate === $today ? 'Today' : ($msgDate === $yesterday ? 'Yesterday' : date('d M Y', strtotime($msg['created_at'])));
        ?>
        <div class="flex justify-center my-3">
            <span class="wa-date-pill"><?= esc($dateLabel) ?></span>
        </div>
        <?php endif; ?>

        <?= view('inbox/partials/message_bubble', ['msg' => $msg, 'contact' => $contact]) ?>

        <?php endforeach; ?>
    </div>

    <!-- Composer -->
    <div class="wa-composer flex-shrink-0 px-4 py-2.5">
        <div class="flex items-end gap-2">
            <!-- Action icons (borderless, WhatsApp style) -->
            <button @click="showTemplateModal = true"
                    title="Send Template"
                    class="p-2 text-[#54656f] hover:bg-[#d9dde0] rounded-full transition-colors flex-shrink-0 mb-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </button>
            <button @click="openCatalogModal()"
                    :disabled="sending"
                    title="Send Catalog / Product"
                    class="p-2 text-[#54656f] hover:bg-[#d9dde0] rounded-full transition-colors flex-shrink-0 mb-0.5 disabled:opacity-40">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                </svg>
            </button>
            <button @click="openBookingModal()"
                    :disabled="sending"
                    title="Send Appointment Booking"
                    class="p-2 text-[#54656f] hover:bg-[#d9dde0] rounded-full transition-colors flex-shrink-0 mb-0.5 disabled:opacity-40">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Z"/>
                </svg>
            </button>
            <div class="relative" @click.away="showTranslateMenu = false">
                <button @click="showTranslateMenu = !showTranslateMenu"
                        :disabled="sending || translating || !messageText.trim()"
                        title="Translate to a language"
                        class="p-2 text-[#54656f] hover:bg-[#d9dde0] rounded-full transition-colors flex-shrink-0 mb-0.5 disabled:opacity-40">
                    <svg x-show="!translating" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m6.334-12.138a24.7 24.7 0 0110.336 4.877M14 7l1 2"/>
                    </svg>
                    <svg x-show="translating" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/>
                    </svg>
                </button>
                <div x-show="showTranslateMenu" x-cloak
                     class="absolute bottom-full mb-1 left-0 bg-white border border-gray-200 rounded-lg shadow-lg py-1 w-40 max-h-56 overflow-y-auto z-10">
                    <?php foreach (['English', 'Spanish', 'French', 'German', 'Portuguese', 'Italian', 'Arabic', 'Hindi', 'Bengali', 'Tamil', 'Telugu', 'Marathi', 'Gujarati', 'Kannada', 'Malayalam', 'Punjabi', 'Urdu', 'Chinese', 'Japanese', 'Russian'] as $lang): ?>
                    <button @click="translateOutgoing('<?= esc($lang) ?>')"
                            class="block w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"><?= esc($lang) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <button @click="rewriteOutgoing()"
                    :disabled="sending || rewriting || !messageText.trim()"
                    title="AI Rewrite (more professional tone)"
                    class="p-2 text-[#54656f] hover:bg-[#d9dde0] rounded-full transition-colors flex-shrink-0 mb-0.5 disabled:opacity-40">
                <svg x-show="!rewriting" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                </svg>
                <svg x-show="rewriting" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/>
                </svg>
            </button>

            <!-- Input (white pill on gray bar, WhatsApp style) -->
            <textarea x-model="messageText"
                      @keydown.enter.prevent="if (!$event.shiftKey) sendMessage()"
                      placeholder="Type a message"
                      rows="1"
                      class="wa-flat-input flex-1 resize-none bg-white rounded-lg px-4 py-[9px] text-[15px] text-[#111b21] placeholder-[#54656f] focus:outline-none max-h-32 overflow-y-auto"
                      style="field-sizing: content; box-shadow: none !important;"></textarea>

            <!-- Send (round green, icon only) -->
            <button @click="sendMessage()"
                    :disabled="sending || !messageText.trim()"
                    :class="sending || !messageText.trim() ? 'bg-[#8696a0] cursor-not-allowed' : 'bg-[#25d366] hover:bg-[#1faa55]'"
                    title="Send"
                    class="w-11 h-11 rounded-full text-white flex items-center justify-center transition-colors flex-shrink-0">
                <svg x-show="!sending" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-0.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3.4 20.4l17.45-7.48c.81-.35.81-1.49 0-1.84L3.4 3.6c-.66-.29-1.39.2-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/>
                </svg>
                <svg x-show="sending" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/>
                </svg>
            </button>
        </div>
    </div>

<!-- Add Note Modal -->
<div x-show="showNoteModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold mb-3">Add Note</h3>
        <textarea x-model="noteText" rows="4" placeholder="Enter note..."
                  class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
        <div class="flex gap-2 mt-3">
            <button @click="addNote()" class="flex-1 bg-blue-900 text-white py-2 rounded-lg text-sm hover:bg-blue-800">Save Note</button>
            <button @click="showNoteModal = false" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">Cancel</button>
        </div>
    </div>
</div>

<!-- Add Tag Modal -->
<div x-show="showTagModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold mb-3">Add Tag</h3>
        <div class="space-y-2 max-h-48 overflow-y-auto">
            <?php foreach ($allTags as $tag): ?>
            <form method="POST" action="<?= base_url('api/conversations/tag') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="conversation_id" value="<?= esc($conversation['id']) ?>">
                <input type="hidden" name="tag_id" value="<?= esc($tag['id']) ?>">
                <button type="submit" class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                    <span class="w-3 h-3 rounded-full" style="background-color: <?= esc($tag['color'] ?? '#3B82F6') ?>"></span>
                    <span class="text-sm"><?= esc($tag['name']) ?></span>
                </button>
            </form>
            <?php endforeach; ?>
            <?php if (empty($allTags)): ?>
            <p class="text-sm text-gray-400 text-center py-4">No tags created yet. Create tags in Contacts settings.</p>
            <?php endif; ?>
        </div>
        <button @click="showTagModal = false" class="w-full mt-3 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">Close</button>
    </div>
</div>

<!-- Send Template Modal -->
<div x-show="showTemplateModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Select Template</h3>
            <button @click="showTemplateModal = false; selectedTemplateId=''; templatePreview=''"
                    class="bg-red-500 hover:bg-red-600 text-white text-xs font-semibold px-4 py-1.5 rounded-full transition-colors">
                CLOSE
            </button>
        </div>
        <!-- Body -->
        <div class="px-6 py-4">
            <p class="text-sm font-semibold text-red-500 mb-4">Note: This is payable according to template categories.</p>
            <select x-model="selectedTemplateId" @change="onTemplateChange($event.target.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Select Template</option>
                <?php foreach ($templates ?? [] as $t): ?>
                <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?> (<?= esc($t['language'] ?? 'en') ?>)</option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($templates ?? [])): ?>
            <p class="text-xs text-gray-400 text-center mt-3">No approved templates. Go to Templates to create one.</p>
            <?php endif; ?>
            <div x-show="templatePreview" x-cloak class="mt-3 bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700 whitespace-pre-wrap border border-gray-200 max-h-36 overflow-y-auto" x-text="templatePreview"></div>
        </div>
        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 px-6 pb-5">
            <button @click="onTemplateChange(selectedTemplateId)"
                    class="px-6 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
                PREVIEW
            </button>
            <button @click="sendTemplate()" :disabled="sending || !selectedTemplateId"
                    :class="sending || !selectedTemplateId ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                    class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-full transition-colors">
                <span x-text="sending ? 'SENDING...' : 'SEND'"></span>
            </button>
        </div>
    </div>
</div>
</div>

<script>
    // Auto-scroll to bottom of thread
    (() => {
        const thread = document.getElementById('message-thread');
        if (thread) thread.scrollTop = thread.scrollHeight;
    })();

    // Poll for new messages so the open thread updates itself (incoming
    // customer replies, delivery ticks from another tab) without a manual
    // page refresh. RovixNav swaps #app-shell's innerHTML instead of doing
    // a real page load, so any setInterval from a previous conversation view
    // would otherwise keep running forever — clear it before starting a new one.
    (() => {
        const conversationId = '<?= esc($conversation['id']) ?>';
        const lastMsg        = <?= json_encode(end($messages) ?: null) ?>;

        let afterAt = lastMsg ? lastMsg.created_at : '1970-01-01 00:00:00';
        let afterId = lastMsg ? lastMsg.id : '';

        if (window.__inboxMsgPoll) clearInterval(window.__inboxMsgPoll);

        window.__inboxMsgPoll = setInterval(async () => {
            try {
                const url = '<?= base_url('api/inbox/messages') ?>/' + conversationId
                    + '?after_at=' + encodeURIComponent(afterAt) + '&after_id=' + encodeURIComponent(afterId);
                const res  = await fetch(url);
                if (!res.ok) return;
                const data = await res.json();
                if (!data.html) return;

                const thread  = document.getElementById('message-thread');
                if (!thread) { clearInterval(window.__inboxMsgPoll); return; }

                const nearBottom = thread.scrollTop + thread.clientHeight >= thread.scrollHeight - 100;
                thread.insertAdjacentHTML('beforeend', data.html);
                if (window.Alpine) window.Alpine.initTree(thread);
                if (nearBottom) thread.scrollTop = thread.scrollHeight;

                afterAt = data.last_at;
                afterId = data.last_id;
            } catch (e) { /* network hiccup, retry next tick */ }
        }, 4000);
    })();
</script>
