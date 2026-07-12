<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$qualityColors = [
    'high'    => 'bg-green-100 text-green-700',
    'medium'  => 'bg-amber-100 text-amber-700',
    'low'     => 'bg-red-100 text-red-700',
    'unknown' => 'bg-gray-100 text-gray-500',
];
$statusBg = [
    'draft'     => 'bg-gray-100 text-gray-600',
    'pending'   => 'bg-yellow-100 text-yellow-700',
    'approved'  => 'bg-green-100 text-green-700',
    'rejected'  => 'bg-red-100 text-red-700',
    'paused'    => 'bg-orange-100 text-orange-700',
    'disabled'  => 'bg-gray-100 text-gray-500',
    'in_appeal' => 'bg-blue-100 text-blue-700',
];
$catBg = [
    'marketing'      => 'bg-purple-50 text-purple-700',
    'utility'        => 'bg-blue-50 text-blue-700',
    'authentication' => 'bg-orange-50 text-orange-700',
];
?>

<div x-data="{ activeTab: 'all' }">

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Message Templates</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage WhatsApp message templates for broadcasts and automations</p>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="fetchFromMeta()" id="fetch-btn"
                class="flex items-center gap-1.5 px-3 py-2 border border-blue-300 text-blue-700 text-sm rounded-lg hover:bg-blue-50 font-medium transition-colors">
            <span id="fetch-icon"><?= rx_icon('cloud', 'w-4 h-4') ?></span> Fetch from Meta
        </button>
        <a href="<?= base_url('templates/create') ?>"
           class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
            + New Template
        </a>
    </div>
</div>

<!-- Flash messages -->
<?php if (session()->getFlashdata('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>
<div id="fetch-result" class="hidden mb-4 px-4 py-3 rounded-lg text-sm"></div>

<!-- Status Tabs -->
<div class="flex gap-1 mb-5 bg-gray-100 p-1 rounded-lg w-fit">
    <?php
    $tabs      = ['all' => 'All', 'draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    $tabColors = ['all' => 'bg-gray-500', 'draft' => 'bg-gray-400', 'pending' => 'bg-yellow-500', 'approved' => 'bg-green-500', 'rejected' => 'bg-red-500'];
    foreach ($tabs as $key => $label):
    ?>
    <button @click="activeTab = '<?= $key ?>'"
            :class="activeTab === '<?= $key ?>' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
            class="px-3 py-1.5 text-sm rounded-md font-medium flex items-center gap-1.5 transition-all">
        <?= $label ?>
        <span class="text-xs px-1.5 py-0.5 rounded-full text-white <?= $tabColors[$key] ?>"><?= $statusCounts[$key] ?></span>
    </button>
    <?php endforeach; ?>
</div>

<!-- Template Grid -->
<?php if (empty($templates)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
    <div class="mb-3"><?= rx_icon('clipboard', 'w-12 h-12', 'mx-auto') ?></div>
    <p class="text-gray-500 mb-2">No templates yet.</p>
    <p class="text-sm text-gray-400 mb-4">Create a template or click <strong>Fetch from Meta</strong> to import your existing ones.</p>
    <a href="<?= base_url('templates/create') ?>" class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800">
        Create Template
    </a>
</div>
<?php else: ?>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($templates as $t):
        $quality = strtolower($t['quality_score'] ?? 'unknown');
        $qc      = $qualityColors[$quality] ?? $qualityColors['unknown'];
        $sc      = $statusBg[$t['status']] ?? 'bg-gray-100 text-gray-600';
        $cc      = $catBg[$t['category']] ?? 'bg-gray-100 text-gray-600';
        $buttons = json_decode($t['buttons'] ?? 'null', true) ?? [];
    ?>
    <div x-show="activeTab === 'all' || activeTab === '<?= $t['status'] ?>'"
         class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-3 hover:shadow-md transition-shadow"
         id="tpl-<?= esc($t['id']) ?>">

        <!-- Card Header row -->
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="font-semibold text-gray-900 text-sm font-mono truncate" title="<?= esc($t['name']) ?>">
                    <?= esc($t['name']) ?>
                </h3>
                <div class="flex flex-wrap items-center gap-1 mt-1">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $cc ?>">
                        <?= ucfirst($t['category']) ?>
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-mono uppercase">
                        <?= esc($t['language']) ?>
                    </span>
                    <?php if ($quality !== 'unknown'): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium inline-flex items-center gap-1 <?= $qc ?>">
                        <?= rx_icon('star', 'w-3.5 h-3.5') ?> <?= ucfirst($quality) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                <span class="text-xs px-2 py-1 rounded-full font-medium <?= $sc ?>">
                    <?= ucfirst($t['status']) ?>
                </span>
                <?php if ($t['created_at']): ?>
                <span class="text-xs text-gray-400"><?= date('d M Y', strtotime($t['created_at'])) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Header indicator -->
        <?php if ($t['header_type'] !== 'none'): ?>
        <div class="text-xs text-gray-400 flex items-center gap-1">
            <?php
            $headerIcons = ['text' => 'T', 'image' => rx_icon('image', 'w-4 h-4'), 'video' => rx_icon('video', 'w-4 h-4'), 'document' => rx_icon('document', 'w-4 h-4')];
            echo ($headerIcons[$t['header_type']] ?? '') . ' ' . ucfirst($t['header_type']) . ' header';
            if ($t['header_type'] === 'text' && $t['header_content']): echo ': <em>' . esc(mb_substr($t['header_content'], 0, 40)) . '</em>'; endif;
            ?>
        </div>
        <?php endif; ?>

        <!-- Body preview -->
        <p class="text-sm text-gray-600 leading-relaxed bg-gray-50 rounded-lg px-3 py-2 flex-1">
            <?= esc(mb_substr($t['body_text'], 0, 120)) ?><?= mb_strlen($t['body_text']) > 120 ? '…' : '' ?>
        </p>

        <?php if ($t['footer_text']): ?>
        <p class="text-xs text-gray-400 italic -mt-1"><?= esc($t['footer_text']) ?></p>
        <?php endif; ?>

        <?php if (!empty($buttons)): ?>
        <div class="flex flex-wrap gap-1">
            <?php foreach (array_slice($buttons, 0, 3) as $btn): ?>
            <span class="text-xs px-2 py-0.5 border border-blue-200 text-blue-600 rounded">
                <?= esc($btn['text'] ?? '') ?>
                <?php if (!empty($btn['url'])): ?><span class="text-gray-400">↗</span><?php endif; ?>
            </span>
            <?php endforeach; ?>
            <?php if (count($buttons) > 3): ?><span class="text-xs text-gray-400">+<?= count($buttons) - 3 ?> more</span><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex items-center gap-1 pt-2 border-t border-gray-100 mt-auto flex-wrap">

            <!-- Preview -->
            <button onclick="showPreview(<?= htmlspecialchars(json_encode([
                'name'           => $t['name'],
                'header_type'    => $t['header_type'],
                'header_content' => $t['header_content'],
                'body_text'      => $t['body_text'],
                'footer_text'    => $t['footer_text'],
                'buttons'        => $buttons,
            ]), ENT_QUOTES) ?>)"
                    class="text-xs px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors font-medium">
                Preview
            </button>

            <!-- Summary -->
            <a href="<?= base_url('templates/' . $t['id'] . '/summary') ?>"
               class="text-xs px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors font-medium">
                Summary
            </a>

            <!-- View -->
            <a href="<?= base_url('templates/' . $t['id']) ?>"
               class="text-xs px-2 py-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">
                View
            </a>

            <?php if (in_array($t['status'], ['draft', 'rejected'])): ?>
            <a href="<?= base_url('templates/' . $t['id'] . '/edit') ?>"
               class="text-xs px-2 py-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">
                Edit
            </a>
            <?php endif; ?>

            <?php if ($t['status'] === 'draft'): ?>
            <form action="<?= base_url('templates/' . $t['id'] . '/submit') ?>" method="POST" class="inline">
                <?= csrf_field() ?>
                <button type="submit"
                        class="text-xs px-2 py-1 text-green-600 hover:bg-green-50 rounded transition-colors">
                    Submit
                </button>
            </form>
            <?php endif; ?>

            <?php if (in_array($t['status'], ['pending', 'approved'])): ?>
            <form action="<?= base_url('templates/' . $t['id'] . '/refresh') ?>" method="POST" class="inline">
                <?= csrf_field() ?>
                <button type="submit"
                        class="text-xs px-2 py-1 text-gray-500 hover:bg-gray-50 rounded transition-colors">
                    ↻ Refresh
                </button>
            </form>
            <?php endif; ?>

            <?php if (in_array($t['status'], ['draft', 'rejected'])): ?>
            <form action="<?= base_url('templates/' . $t['id'] . '/delete') ?>" method="POST"
                  class="inline ml-auto" onsubmit="return confirm('Delete this template?')">
                <?= csrf_field() ?>
                <button type="submit"
                        class="text-xs px-2 py-1 text-red-500 hover:bg-red-50 rounded transition-colors">
                    Delete
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
</div><!-- /x-data -->

<!-- ── Preview Modal ────────────────────────────────────────────────────── -->
<div id="preview-modal"
     class="fixed inset-0 z-50 flex items-center justify-center px-4"
     style="display:none;">
    <div class="absolute inset-0 bg-black/40" onclick="closePreview()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 overflow-hidden flex flex-col max-h-[85vh]">

        <!-- Modal header -->
        <div class="bg-green-700 text-white px-4 py-3 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center text-sm font-bold">W</div>
                <span class="text-sm font-semibold" id="prev-name">Template</span>
            </div>
            <button onclick="closePreview()" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
        </div>

        <!-- Chat area -->
        <div class="bg-[#e5ddd5] min-h-48 p-4 overflow-y-auto">
            <div class="max-w-[90%] bg-white rounded-xl shadow-sm overflow-hidden" id="prev-bubble">

                <!-- Header -->
                <div id="prev-header" class="hidden">
                    <div id="prev-header-text" class="hidden px-3 pt-3 pb-1 font-semibold text-gray-900 text-sm"></div>
                    <div id="prev-header-image" class="hidden bg-gray-200 h-40 flex items-center justify-center"><?= rx_icon('image', 'w-12 h-12', 'mx-auto') ?></div>
                    <div id="prev-header-video" class="hidden bg-gray-300 h-40 flex items-center justify-center"><?= rx_icon('video', 'w-12 h-12', 'mx-auto') ?></div>
                    <div id="prev-header-document" class="hidden bg-gray-100 h-16 flex items-center justify-center gap-2 text-sm text-gray-600"><?= rx_icon('document', 'w-6 h-6') ?>Document</div>
                </div>

                <!-- Body -->
                <div class="px-3 py-3 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed" id="prev-body"></div>

                <!-- Footer -->
                <div id="prev-footer-wrap" class="hidden px-3 pb-2">
                    <div class="text-xs text-gray-400" id="prev-footer"></div>
                </div>

                <!-- Timestamp -->
                <div class="px-3 pb-2 text-right">
                    <span class="text-xs text-gray-400">10:30 AM <?= rx_icon('check-double', 'w-3.5 h-3.5') ?></span>
                </div>

                <!-- Buttons -->
                <div id="prev-buttons" class="hidden border-t border-gray-100"></div>
            </div>
        </div>

        <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-400 text-center flex-shrink-0">
            WhatsApp template preview — actual formatting may vary
        </div>
    </div>
</div>

<script>
const FETCH_URL   = <?= json_encode(base_url('templates/fetch-from-meta'), JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const ICON_CLOUD  = <?= json_encode(rx_icon('cloud', 'w-4 h-4'), JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
window.__csrfName = '<?= csrf_token() ?>';
window.__csrfHash = '<?= csrf_hash() ?>';

// ── Fetch from Meta ────────────────────────────────────────────────────────
async function fetchFromMeta() {
    const btn  = document.getElementById('fetch-btn');
    const icon = document.getElementById('fetch-icon');
    const res  = document.getElementById('fetch-result');
    btn.disabled = true;
    icon.textContent = '…';
    res.className = 'hidden mb-4 px-4 py-3 rounded-lg text-sm';

    try {
        const r = await fetch(FETCH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ [window.__csrfName]: window.__csrfHash }),
        });
        const data = await r.json();
        if (data.success) {
            res.className = 'mb-4 px-4 py-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800';
            let msg = `Synced ${data.synced} template(s) from Meta.`;
            if (data.waba_corrected) {
                msg += ' Your WhatsApp Business Account ID was auto-corrected in Settings.';
            }
            res.textContent = msg + ' Refreshing…';
            setTimeout(() => location.reload(), 1500);
        } else {
            res.className = 'mb-4 px-4 py-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-700';
            res.textContent = (data.error || 'Fetch failed');
        }
    } catch (e) {
        res.className = 'mb-4 px-4 py-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-700';
        res.textContent = 'Network error';
    }

    icon.innerHTML = ICON_CLOUD;
    btn.disabled = false;
}

// ── Preview Modal ──────────────────────────────────────────────────────────
function showPreview(tpl) {
    document.getElementById('prev-name').textContent = tpl.name;

    // Header
    const hdr = document.getElementById('prev-header');
    ['text','image','video','document'].forEach(t => {
        document.getElementById('prev-header-'+t).classList.add('hidden');
    });
    if (tpl.header_type && tpl.header_type !== 'none') {
        hdr.classList.remove('hidden');
        const el = document.getElementById('prev-header-' + tpl.header_type);
        if (el) {
            el.classList.remove('hidden');
            if (tpl.header_type === 'text') {
                el.textContent = tpl.header_content || '';
            } else if (tpl.header_type === 'image' && tpl.header_content) {
                el.innerHTML = `<img src="${escHtml(tpl.header_content.trim())}" alt="Header image" class="w-full h-40 object-cover" onerror="this.remove()">`;
            }
        }
    } else {
        hdr.classList.add('hidden');
    }

    // Body — highlight {{variables}}
    const body = document.getElementById('prev-body');
    body.innerHTML = escHtml(tpl.body_text || '')
        .replace(/\{\{(\w+)\}\}/g, '<span class="bg-yellow-100 text-yellow-800 rounded px-0.5">{{$1}}</span>');

    // Footer
    const fw = document.getElementById('prev-footer-wrap');
    const ft = document.getElementById('prev-footer');
    if (tpl.footer_text) {
        ft.textContent = tpl.footer_text;
        fw.classList.remove('hidden');
    } else {
        fw.classList.add('hidden');
    }

    // Buttons
    const btnsEl = document.getElementById('prev-buttons');
    if (tpl.buttons && tpl.buttons.length > 0) {
        btnsEl.classList.remove('hidden');
        btnsEl.innerHTML = tpl.buttons.map(b =>
            `<div class="flex items-center justify-center gap-1 py-2.5 border-t border-gray-100 text-sm text-blue-600 font-medium">
                ${escHtml(b.text || '')}
             </div>`
        ).join('');
    } else {
        btnsEl.classList.add('hidden');
        btnsEl.innerHTML = '';
    }

    document.getElementById('preview-modal').style.display = 'flex';
}

function closePreview() {
    document.getElementById('preview-modal').style.display = 'none';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview(); });
</script>

<?= $this->endSection() ?>
