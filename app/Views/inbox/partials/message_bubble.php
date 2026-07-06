<?php
helper(['format', 'auth', 'role']);
$translateLanguages = [
    'English', 'Spanish', 'French', 'German', 'Portuguese', 'Italian',
    'Arabic', 'Hindi', 'Bengali', 'Tamil', 'Telugu', 'Marathi', 'Gujarati',
    'Kannada', 'Malayalam', 'Punjabi', 'Urdu', 'Chinese', 'Japanese', 'Russian',
];
$isCustomer = $msg['sender_type'] === 'customer';
$isSystem   = $msg['sender_type'] === 'system';
$quotePreview = function (array $q): string {
    return match ($q['content_type']) {
        'image'    => '📷 Photo' . ($q['content_text'] ? ': ' . mb_strimwidth($q['content_text'], 0, 40, '...') : ''),
        'video'    => '🎥 Video',
        'audio'    => !empty($q['is_voice_note']) ? '🎤 Voice message' : '🎵 Audio',
        'sticker'  => '💟 Sticker',
        'document' => '📄 ' . ($q['media_filename'] ?? 'Document'),
        'location' => '📍 Location',
        'template' => 'Template: ' . ($q['template_name'] ?? ''),
        'flow'     => '📅 Appointment booking',
        'catalog'  => '🛒 Catalog',
        'product', 'product_list' => '🛍️ Product',
        'order'    => '🧾 Order',
        'contacts' => '👤 Contact',
        default    => mb_strimwidth((string) ($q['content_text'] ?? ''), 0, 60, '...'),
    };
};
?>
<?php if ($isSystem): ?>
<div class="flex justify-center my-1.5" data-msg-id="<?= esc($msg['id']) ?>">
    <span class="wa-system"><?= esc($msg['content_text']) ?></span>
</div>

<?php elseif ($isCustomer): ?>
<div class="flex justify-start group/msg" data-msg-id="<?= esc($msg['id']) ?>">
    <div class="wa-bubble wa-in" x-data="{
        showReact: false,
        showLangPicker: false,
        translated: null,
        translating: false,
        async translateMsg(lang) {
            if (this.translating) return;
            this.showLangPicker = false;
            this.translating = true;
            try {
                const fd = new FormData();
                fd.append('message_id', '<?= esc($msg['id']) ?>');
                fd.append('target_language', lang);
                fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
                const res  = await fetch('<?= base_url('api/ai/translate-incoming') ?>', { method: 'POST', body: fd });
                const data = await res.json();
                this.translated = data.success ? data.text : ('Error: ' + (data.error || 'failed'));
            } catch (e) { this.translated = 'Network error'; }
            this.translating = false;
        }
    }" @mouseenter="showReact = true" @mouseleave="showReact = false">
        <div class="wa-react-trigger" x-show="showReact" x-cloak @click.stop="showReact = 'pick'">
            <?= rx_icon('smile', 'w-3.5 h-3.5') ?>
        </div>
        <div class="wa-react-picker" x-show="showReact === 'pick'" x-cloak @click.away="showReact = false">
            <?php foreach (['👍', '❤️', '😂', '😮', '😢', '🙏'] as $emoji): ?>
            <span @click="reactToMessage('<?= esc($msg['id']) ?>', '<?= $emoji ?>')"><?= $emoji ?></span>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($msg['quoted'])): ?>
        <div class="wa-quote">
            <div class="wa-quote-sender"><?= $msg['quoted']['sender_type'] === 'customer' ? esc($contact['name'] ?? 'Contact') : 'You' ?></div>
            <div class="wa-quote-text"><?= esc($quotePreview($msg['quoted'])) ?></div>
        </div>
        <?php endif; ?>
        <?= view('inbox/partials/message_content', ['msg' => $msg]) ?>
        <?php if ($msg['content_type'] === 'text' && !empty($msg['content_text'])): ?>
        <div x-show="!translated" x-cloak class="mt-1 relative" @click.away="showLangPicker = false">
            <button @click.stop="showLangPicker = !showLangPicker" class="text-[11px] text-[#008069] hover:underline" :disabled="translating">
                <span x-show="!translating">Translate</span>
                <span x-show="translating">Translating…</span>
            </button>
            <div x-show="showLangPicker" x-cloak
                 class="absolute z-10 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 w-36 max-h-56 overflow-y-auto">
                <?php foreach ($translateLanguages as $lang): ?>
                <button @click.stop="translateMsg('<?= esc($lang) ?>')"
                        class="block w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"><?= esc($lang) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div x-show="translated" x-cloak class="mt-1 pt-1 border-t border-black/5">
            <div class="text-[13px] text-[#111b21]/80 italic" x-text="translated"></div>
            <button @click.stop="translated = null" class="text-[10px] text-[#008069] hover:underline mt-0.5">Translate to another language</button>
        </div>
        <?php endif; ?>
        <span class="wa-meta"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
        <div style="clear:both"></div>
        <?php if (!empty($msg['reactions'])): ?>
        <div class="wa-reaction-badge"><?= implode('', array_unique(array_column($msg['reactions'], 'emoji'))) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="flex justify-end group/msg" data-msg-id="<?= esc($msg['id']) ?>">
    <div class="wa-bubble wa-out" x-data="{ showReact: false }" @mouseenter="showReact = true" @mouseleave="showReact = false">
        <div class="wa-react-trigger" x-show="showReact" x-cloak @click.stop="showReact = 'pick'">
            <?= rx_icon('smile', 'w-3.5 h-3.5') ?>
        </div>
        <div class="wa-react-picker" x-show="showReact === 'pick'" x-cloak @click.away="showReact = false">
            <?php foreach (['👍', '❤️', '😂', '😮', '😢', '🙏'] as $emoji): ?>
            <span @click="reactToMessage('<?= esc($msg['id']) ?>', '<?= $emoji ?>')"><?= $emoji ?></span>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($msg['quoted'])): ?>
        <div class="wa-quote">
            <div class="wa-quote-sender"><?= $msg['quoted']['sender_type'] === 'customer' ? esc($contact['name'] ?? 'Contact') : 'You' ?></div>
            <div class="wa-quote-text"><?= esc($quotePreview($msg['quoted'])) ?></div>
        </div>
        <?php endif; ?>
        <?= view('inbox/partials/message_content', ['msg' => $msg, 'outgoing' => true]) ?>
        <span class="wa-meta">
            <?= date('H:i', strtotime($msg['created_at'])) ?>
            <?php if ($msg['status'] === 'sending'): ?><span class="wa-tick"><?= rx_icon('clock', 'w-4 h-4', '!text-current') ?></span>
            <?php elseif ($msg['status'] === 'sent'): ?><span class="wa-tick"><?= rx_icon('check', 'w-4 h-4', '!text-current') ?></span>
            <?php elseif ($msg['status'] === 'delivered'): ?><span class="wa-tick"><?= rx_icon('check-double', 'w-4 h-4', '!text-current') ?></span>
            <?php elseif ($msg['status'] === 'read'): ?><span class="wa-tick-read"><?= rx_icon('check-double', 'w-4 h-4', '!text-current') ?></span>
            <?php elseif ($msg['status'] === 'failed'): ?><span class="wa-tick-fail"><?= rx_icon('x', 'w-4 h-4', '!text-current') ?></span>
            <?php endif; ?>
        </span>
        <div style="clear:both"></div>
        <?php if ($msg['status'] === 'failed' && $msg['error_message']): ?>
        <div class="text-xs text-red-500 mt-1"><?= esc($msg['error_message']) ?></div>
        <?php endif; ?>
        <?php if (!empty($msg['reactions'])): ?>
        <div class="wa-reaction-badge"><?= implode('', array_unique(array_column($msg['reactions'], 'emoji'))) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
