<?php
// Inside .wa-bubble both incoming (white) and outgoing (green) use dark text.
$outgoing   = $outgoing ?? false;
$textClass  = 'text-gray-800';
$smallClass = 'text-gray-500';
?>

<?php if ($msg['content_type'] === 'text'): ?>
    <p class="text-sm <?= $textClass ?> whitespace-pre-wrap break-words"><?= esc($msg['content_text']) ?></p>

<?php elseif ($msg['content_type'] === 'image'): ?>
    <?php if ($msg['media_url']): ?>
    <img src="<?= base_url('api/media/download/' . esc($msg['media_url'])) ?>"
         class="max-w-full rounded-lg cursor-pointer" style="max-height: 250px; object-fit: cover;"
         onclick="window.open(this.src)">
    <?php endif; ?>
    <?php if ($msg['content_text']): ?>
    <p class="text-sm <?= $textClass ?> mt-1"><?= esc($msg['content_text']) ?></p>
    <?php endif; ?>

<?php elseif ($msg['content_type'] === 'video'): ?>
    <?php if ($msg['media_url']): ?>
    <video controls class="max-w-full rounded-lg" style="max-height: 250px;">
        <source src="<?= base_url('api/media/download/' . esc($msg['media_url'])) ?>">
    </video>
    <?php endif; ?>

<?php elseif ($msg['content_type'] === 'audio'): ?>
    <?php if ($msg['media_url']): ?>
    <?php if (!empty($msg['is_voice_note'])): ?>
    <div class="flex items-center gap-2 min-w-[220px]">
        <?= rx_icon('mic', 'w-5 h-5') ?>
        <audio controls class="flex-1" style="height: 32px;">
            <source src="<?= base_url('api/media/download/' . esc($msg['media_url'])) ?>">
        </audio>
    </div>
    <?php else: ?>
    <audio controls class="w-full">
        <source src="<?= base_url('api/media/download/' . esc($msg['media_url'])) ?>">
    </audio>
    <?php endif; ?>
    <?php endif; ?>

<?php elseif ($msg['content_type'] === 'sticker'): ?>
    <?php if ($msg['media_url']): ?>
    <img src="<?= base_url('api/media/download/' . esc($msg['media_url'])) ?>"
         class="cursor-pointer" style="width: 120px; height: 120px; object-fit: contain;"
         onclick="window.open(this.src)">
    <?php else: ?>
    <p class="text-sm <?= $smallClass ?> italic">Sticker</p>
    <?php endif; ?>

<?php elseif ($msg['content_type'] === 'flow'): ?>
    <?php $flowData = json_decode($msg['content_text'] ?? '{}', true); ?>
    <p class="text-sm <?= $textClass ?> whitespace-pre-wrap break-words mb-2"><?= esc($flowData['body'] ?? '') ?></p>
    <div class="flex items-center justify-center gap-1.5 mt-1 px-3 py-1.5 text-xs text-center rounded-lg border border-sky-400 text-sky-700">
        <?= rx_icon('calendar', 'w-3.5 h-3.5') ?>
        <?= esc($flowData['button'] ?? 'Open') ?>
    </div>

<?php elseif ($msg['content_type'] === 'document'): ?>
    <a href="<?= base_url('api/media/download/' . esc($msg['media_url'])) ?>"
       target="_blank"
       class="flex items-center gap-2 <?= $textClass ?> hover:opacity-80">
        <?= rx_icon('document', 'w-5 h-5') ?>
        <span class="text-sm underline"><?= esc($msg['media_filename'] ?? 'Document') ?></span>
    </a>

<?php elseif ($msg['content_type'] === 'template'): ?>
    <div class="<?= $textClass ?>">
        <span class="text-xs <?= $smallClass ?>">Template: <?= esc($msg['template_name'] ?? 'Unknown') ?></span>
        <?php if ($msg['content_text']): ?>
        <p class="text-sm mt-1"><?= esc($msg['content_text']) ?></p>
        <?php endif; ?>
    </div>

<?php elseif ($msg['content_type'] === 'buttons'): ?>
    <?php $btnData = json_decode($msg['content_text'] ?? '{}', true); ?>
    <p class="text-sm <?= $textClass ?> whitespace-pre-wrap break-words mb-2"><?= esc($btnData['body'] ?? '') ?></p>
    <?php foreach ($btnData['buttons'] ?? [] as $btn): ?>
    <div class="mt-1 px-3 py-1.5 text-xs text-center rounded-lg border border-gray-300 text-sky-700">
        <?= esc($btn) ?>
    </div>
    <?php endforeach; ?>

<?php elseif ($msg['content_type'] === 'location'): ?>
    <p class="text-sm <?= $textClass ?>"><?= rx_icon('pin', 'w-4 h-4') ?> <?= esc($msg['content_text']) ?></p>

<?php elseif ($msg['content_type'] === 'catalog'): ?>
    <p class="text-sm <?= $textClass ?> mb-2"><?= esc($msg['content_text']) ?></p>
    <div class="flex items-center gap-1.5 mt-1 px-3 py-1.5 text-xs text-center rounded-lg border border-amber-400 text-amber-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
        </svg>
        View Catalog
    </div>

<?php elseif ($msg['content_type'] === 'product'): ?>
    <div class="flex items-center gap-2">
        <?= rx_icon('bag', 'w-5 h-5') ?>
        <p class="text-sm <?= $textClass ?>"><?= esc($msg['content_text']) ?></p>
    </div>

<?php elseif ($msg['content_type'] === 'product_list'): ?>
    <div class="flex items-center gap-2">
        <?= rx_icon('clipboard', 'w-5 h-5') ?>
        <p class="text-sm <?= $textClass ?>"><?= esc($msg['content_text']) ?></p>
    </div>

<?php elseif ($msg['content_type'] === 'order'): ?>
    <div class="flex items-center gap-2">
        <?= rx_icon('cart', 'w-5 h-5') ?>
        <p class="text-sm <?= $textClass ?>"><?= esc($msg['content_text']) ?></p>
    </div>

<?php elseif (in_array($msg['content_type'], ['button', 'interactive'], true)): ?>
    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 text-xs font-medium <?= $textClass ?>">
        <?= rx_icon('reply', 'w-3.5 h-3.5') ?>
        <?= esc($msg['content_text']) ?>
    </div>

<?php elseif ($msg['content_type'] === 'contacts'): ?>
    <div class="flex items-center gap-2">
        <?= rx_icon('user', 'w-5 h-5') ?>
        <p class="text-sm <?= $textClass ?>"><?= esc($msg['content_text']) ?></p>
    </div>

<?php elseif ($msg['content_type'] === 'unsupported'): ?>
    <p class="text-sm <?= $smallClass ?> italic flex items-center gap-1.5">
        <?= rx_icon('warning', 'w-4 h-4') ?>
        <?= esc($msg['content_text'] ?? 'Unsupported message') ?>
    </p>

<?php else: ?>
    <p class="text-sm <?= $textClass ?>"><?= esc($msg['content_text'] ?? '[Unsupported message]') ?></p>
<?php endif; ?>
