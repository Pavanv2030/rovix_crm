<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper(['format', 'auth']);
$activeConversation = $activeConversation ?? null;
$messages           = $messages ?? [];
$contact            = $contact ?? null;
$tags               = $tags ?? [];
$allTags            = $allTags ?? [];
$agents             = $agents ?? [];
?>

<script>
window.__conversations = <?= json_encode($conversations, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div id="inbox-app" class="flex h-full -m-6 overflow-hidden" x-data="{
    searchQuery: '',
    selectedStatus: '<?= esc($selectedStatus) ?>',
    conversations: window.__conversations || [],
    get filteredConversations() {
        return this.conversations.filter(c => {
            const q = this.searchQuery.toLowerCase();
            const matchSearch = !q ||
                (c.contact_name && c.contact_name.toLowerCase().includes(q)) ||
                (c.phone && c.phone.includes(q)) ||
                (c.last_message_text && c.last_message_text.toLowerCase().includes(q));
            const matchStatus = this.selectedStatus === 'all' || c.status === this.selectedStatus;
            return matchSearch && matchStatus;
        });
    }
}">

    <!-- Left Sidebar: Conversation List -->
    <div class="w-80 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col">

        <!-- Header -->
        <div class="px-3 pt-3 pb-2">
            <div class="flex items-center justify-between mb-2 px-1">
                <h2 class="text-[19px] font-bold text-[#111b21]">Chats</h2>
                <button @click="window.location.reload()" title="Refresh" class="p-2 text-[#54656f] hover:bg-[#f0f2f5] rounded-full transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>

            <!-- Search (WhatsApp style: pill, icon inside, no border) -->
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#54656f]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input type="text" x-model="searchQuery" placeholder="Search or start a new chat"
                       class="wa-flat-input w-full pl-11 pr-4 py-[7px] text-[13px] bg-[#f0f2f5] rounded-lg focus:outline-none placeholder-[#54656f] text-[#111b21]">
            </div>

            <!-- Filter chips (WhatsApp style) -->
            <div class="flex mt-2 gap-1.5">
                <?php foreach (['all' => 'All', 'open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed'] as $s => $label): ?>
                <button @click="selectedStatus = '<?= $s ?>'"
                        :class="selectedStatus === '<?= $s ?>' ? 'bg-[#e7fce3] text-[#008069] font-medium' : 'bg-[#f0f2f5] text-[#54656f] hover:bg-[#e9edef]'"
                        class="text-xs px-3 py-1.5 rounded-full transition-colors">
                    <?= $label ?><?php if ($s !== 'all' && isset($statusCounts[$s]) && $statusCounts[$s] > 0): ?> <span class="font-semibold"><?= $statusCounts[$s] ?></span><?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Conversation List -->
        <div class="flex-1 overflow-y-auto">
            <template x-if="filteredConversations.length === 0">
                <div class="p-8 text-center text-gray-400 text-sm">No conversations found</div>
            </template>

            <template x-for="conv in filteredConversations" :key="conv.id">
                <a :href="'<?= base_url('inbox/conversation') ?>/' + conv.id"
                   :class="conv.id === '<?= $activeConversation['id'] ?? '' ?>' ? 'bg-[#f0f2f5]' : 'hover:bg-[#f5f6f6]'"
                   class="flex items-center pl-3 pr-3 cursor-pointer transition-colors">

                    <!-- Avatar (WhatsApp neutral) -->
                    <div class="flex-shrink-0 w-[49px] h-[49px] rounded-full bg-[#dfe5e7] text-[#54656f] flex items-center justify-center font-medium text-lg mr-3">
                        <span x-text="(conv.contact_name || conv.phone || '?').charAt(0).toUpperCase()"></span>
                    </div>

                    <!-- Content (bottom border only under text, like WhatsApp) -->
                    <div class="flex-1 min-w-0 py-3 border-b border-[#e9edef]">
                        <div class="flex items-baseline justify-between">
                            <span class="text-[15px] text-[#111b21] truncate"
                                  :class="conv.unread_count > 0 ? 'font-semibold' : ''"
                                  x-text="conv.contact_name || conv.phone"></span>
                            <span class="text-xs ml-2 flex-shrink-0"
                                  :class="conv.unread_count > 0 ? 'text-[#25d366] font-medium' : 'text-[#667781]'"
                                  x-text="conv.last_message_at ? conv.last_message_at.substring(11, 16) : ''"></span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            <span class="text-[13px] text-[#667781] truncate"
                                  x-text="conv.last_message_text ? conv.last_message_text.substring(0, 45) + (conv.last_message_text.length > 45 ? '...' : '') : 'No messages yet'"></span>
                            <template x-if="conv.unread_count > 0">
                                <span class="ml-2 bg-[#25d366] text-white text-[11px] font-medium rounded-full min-w-[20px] h-5 px-1.5 flex items-center justify-center flex-shrink-0"
                                      x-text="conv.unread_count"></span>
                            </template>
                        </div>
                        <!-- 24h window timer -->
                        <span class="wa-window-timer text-[10px] font-medium mt-0.5 block"
                              :data-ts="conv.last_customer_message_at || ''"></span>
                    </div>
                </a>
            </template>
        </div>
    </div>

    <!-- Right Panel: Conversation Thread -->
    <div class="flex-1 flex flex-col bg-gray-50 overflow-hidden">
        <?php if ($activeConversation): ?>
            <?= view('inbox/partials/conversation_thread', [
                'conversation'       => $activeConversation,
                'messages'           => $messages,
                'contact'            => $contact,
                'tags'               => $tags,
                'allTags'            => $allTags,
                'agents'             => $agents,
                'templates'          => $templates ?? [],
                'leadStatuses'       => $leadStatuses ?? [],
            ]) ?>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center border-b-[6px] border-[#25d366]" style="background:#f0f2f5;">
                <div class="text-center max-w-md px-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[280px] h-[190px] mx-auto mb-8 text-[#dfe4e7]" viewBox="0 0 303 172" fill="currentColor">
                        <path d="M229.6 0H73.4C32.9 0 0 32.9 0 73.4v25.2C0 139.1 32.9 172 73.4 172h156.2c40.5 0 73.4-32.9 73.4-73.4V73.4C303 32.9 270.1 0 229.6 0zM151.5 129c-24.8 0-45-20.2-45-45s20.2-45 45-45 45 20.2 45 45-20.2 45-45 45z" opacity="0.35"/>
                        <circle cx="151.5" cy="84" r="30" opacity="0.5"/>
                    </svg>
                    <h3 class="text-[28px] font-light text-[#41525d] mb-3">RovixAI Web</h3>
                    <p class="text-sm text-[#667781] leading-relaxed">Send and receive WhatsApp messages, catalogs and appointment bookings — all from one inbox.</p>
                    <p class="text-[13px] text-[#8696a0] mt-8 flex items-center justify-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm0 18c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8zm.5-13H11v6l5.2 3.1.8-1.2-4.5-2.7V7z"/></svg>
                        Select a conversation from the left to start chatting
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ── 24-hour WhatsApp session window timer ──────────────────────────────────
const WA_WINDOW_MS = 24 * 60 * 60 * 1000;

function waWindowLabel(tsStr) {
    if (!tsStr) return { text: '', cls: '' };
    // MySQL DATETIME → JS Date (treat as UTC or local — same offset as PHP server)
    const sentAt = new Date(tsStr.replace(' ', 'T'));
    const expiresAt = new Date(sentAt.getTime() + WA_WINDOW_MS);
    const remaining = expiresAt - Date.now();

    if (remaining <= 0) {
        return { text: 'Session Expired', cls: 'text-red-500 font-semibold' };
    }

    const totalSec = Math.floor(remaining / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    const label = h + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';

    let cls;
    if (remaining > 6 * 3600 * 1000)      cls = 'text-green-600';
    else if (remaining > 1 * 3600 * 1000) cls = 'text-amber-500';
    else                                   cls = 'text-red-500 font-semibold';

    return { text: 'Time left: ' + label, cls };
}

function refreshAllTimers() {
    document.querySelectorAll('.wa-window-timer').forEach(el => {
        const ts = el.dataset.ts || el.getAttribute('data-ts') || '';
        const { text, cls } = waWindowLabel(ts);

        if (el.classList.contains('wa-window-badge')) {
            // Prominent pill in the thread header
            el.textContent = text;
            if (!text) { el.style.display = 'none'; return; }
            el.style.display = '';
            const expired = text === 'Session Expired';
            el.className = 'wa-window-timer wa-window-badge text-xs px-2 py-0.5 rounded-full font-medium '
                + (expired ? 'bg-red-100 text-red-600' : cls.includes('red') ? 'bg-red-50 text-red-600' : cls.includes('amber') ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-700');
        } else {
            // Compact line in sidebar list
            el.textContent = text;
            el.className = 'wa-window-timer text-[10px] font-medium mt-0.5 block ' + cls;
        }
    });
}

// Run immediately, then every second. RovixNav swaps #app-shell's innerHTML
// instead of a real page load, so re-entering /inbox would otherwise stack
// another interval on top of the previous one — clear before restarting.
if (window.__inboxTimerPoll) clearInterval(window.__inboxTimerPoll);
refreshAllTimers();
window.__inboxTimerPoll = setInterval(refreshAllTimers, 1000);

// Re-run after Alpine finishes rendering (x-for creates elements asynchronously)
document.addEventListener('alpine:initialized', () => setTimeout(refreshAllTimers, 100));

// Poll the conversation list so it updates itself (new last message, unread
// badge, reordering) like a real chat app instead of needing a page reload.
(() => {
    if (window.__inboxListPoll) clearInterval(window.__inboxListPoll);

    window.__inboxListPoll = setInterval(async () => {
        try {
            const res = await fetch('<?= base_url('api/inbox/conversations') ?>');
            if (!res.ok) return;
            const data = await res.json();
            const el   = document.getElementById('inbox-app');
            if (!el || !window.Alpine) { clearInterval(window.__inboxListPoll); return; }
            window.Alpine.$data(el).conversations = data;
        } catch (e) { /* network hiccup, retry next tick */ }
    }, 4000);
})();
</script>

<?= $this->endSection() ?>
