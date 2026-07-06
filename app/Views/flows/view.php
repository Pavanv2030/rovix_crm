<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$isActive   = (bool)$flow['is_active'];
$keywords   = json_decode($flow['trigger_keywords'] ?? '[]', true) ?? [];
$activeTab  = $activeTab ?? 'diagram';
$nodeLabels = [
    'start'         => ['icon' => '▶',  'label' => 'Start'],
    'send_message'  => ['icon' => rx_icon('chat', 'w-4 h-4'), 'label' => 'Send Message'],
    'send_buttons'  => ['icon' => rx_icon('radio', 'w-4 h-4'), 'label' => 'Send Buttons'],
    'send_list'     => ['icon' => rx_icon('clipboard', 'w-4 h-4'), 'label' => 'Send List'],
    'send_media'    => ['icon' => rx_icon('image', 'w-4 h-4'), 'label' => 'Send Media'],
    'collect_input' => ['icon' => rx_icon('pencil', 'w-4 h-4'), 'label' => 'Collect Input'],
    'condition'     => ['icon' => rx_icon('branch', 'w-4 h-4'), 'label' => 'Condition'],
    'set_tag'       => ['icon' => rx_icon('tag', 'w-4 h-4'), 'label' => 'Set Tag'],
    'handoff'       => ['icon' => rx_icon('user', 'w-4 h-4'), 'label' => 'Handoff'],
    'end'           => ['icon' => rx_icon('flag', 'w-4 h-4'), 'label' => 'End'],
];
$statusColors = [
    'active'     => 'bg-blue-100 text-blue-800',
    'completed'  => 'bg-green-100 text-green-800',
    'handed_off' => 'bg-purple-100 text-purple-800',
    'failed'     => 'bg-red-100 text-red-800',
    'timed_out'  => 'bg-amber-100 text-amber-800',
];
?>

<!-- Header -->
<div class="flex items-center justify-between mb-5">
    <div class="flex items-center gap-3">
        <a href="<?= base_url('flows') ?>" class="text-sm text-gray-400 hover:text-gray-600">← Flows</a>
        <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <?= rx_icon('branch', 'w-5 h-5') ?> <?= esc($flow['name']) ?>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $isActive ? 'Active' : 'Paused' ?>
            </span>
        </h1>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= base_url('flows/' . $flow['id'] . '/test') ?>"
           class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 font-medium">
            ▶ Test
        </a>
        <a href="<?= base_url('flows/' . $flow['id'] . '/edit') ?>"
           class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
            Edit
        </a>
        <form method="POST" action="<?= base_url('flows/' . $flow['id'] . '/toggle') ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="px-3 py-1.5 border border-gray-300 text-sm rounded-lg hover:bg-gray-50 text-gray-700">
                <?= $isActive ? 'Pause' : 'Activate' ?>
            </button>
        </form>
        <form method="POST" action="<?= base_url('flows/' . $flow['id'] . '/duplicate') ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="px-3 py-1.5 border border-gray-300 text-sm rounded-lg hover:bg-gray-50 text-gray-700">Copy</button>
        </form>
        <form method="POST" action="<?= base_url('flows/' . $flow['id'] . '/delete') ?>" class="inline"
              onsubmit="return confirm('Delete this flow?')">
            <?= csrf_field() ?>
            <button type="submit" class="px-3 py-1.5 border border-red-200 text-sm rounded-lg hover:bg-red-50 text-red-600">Delete</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-3 gap-5">

    <!-- ── Left Panel ───────────────────────────────────────────────────── -->
    <div class="col-span-1 space-y-4">

        <!-- Stats -->
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Overview</h3>
            <div class="grid grid-cols-2 gap-3">
                <?php
                $activeRuns    = count(array_filter($runs, fn($r) => $r['status'] === 'active'));
                $completedRuns = count(array_filter($runs, fn($r) => $r['status'] === 'completed'));
                ?>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-gray-800"><?= number_format($flow['execution_count'] ?? 0) ?></div>
                    <div class="text-xs text-gray-400 mt-0.5">Total Runs</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-blue-600"><?= $activeRuns ?></div>
                    <div class="text-xs text-gray-400 mt-0.5">Active Now</div>
                </div>
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-green-600"><?= $completedRuns ?></div>
                    <div class="text-xs text-gray-400 mt-0.5">Completed</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-gray-600"><?= count($nodes) ?></div>
                    <div class="text-xs text-gray-400 mt-0.5">Nodes</div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="text-xs text-gray-500 mb-2 font-medium">Keywords</div>
                <div class="flex flex-wrap gap-1">
                    <?php foreach ($keywords as $kw): ?>
                    <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full"><?= esc($kw) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Node list -->
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Nodes (<?= count($nodes) ?>)</h3>
            <?php if (empty($nodes)): ?>
            <p class="text-xs text-gray-400 italic">No nodes yet.</p>
            <?php else: ?>
            <div class="space-y-1">
                <?php foreach ($nodes as $node):
                    $nInfo  = $nodeLabels[$node['node_type']] ?? ['icon' => rx_icon('clipboard', 'w-4 h-4'), 'label' => $node['node_type']];
                    $config = json_decode($node['config'] ?? '{}', true) ?? [];
                    $preview = '';
                    if (!empty($config['message_text']))  $preview = mb_substr($config['message_text'], 0, 40);
                    elseif (!empty($config['prompt_text'])) $preview = mb_substr($config['prompt_text'], 0, 40);
                    elseif (!empty($config['body_text']))   $preview = mb_substr($config['body_text'], 0, 40);
                ?>
                <div class="flex items-start gap-2 py-2 border-b border-gray-50 last:border-0">
                    <span><?= $nInfo['icon'] ?></span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-gray-700"><?= $nInfo['label'] ?></div>
                        <?php if ($preview): ?>
                        <div class="text-xs text-gray-400 truncate"><?= esc($preview) ?>…</div>
                        <?php endif; ?>
                        <div class="text-xs text-gray-300 font-mono"><?= esc($node['node_key']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right Panel: Tabs ─────────────────────────────────────────────── -->
    <div class="col-span-2">
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

            <!-- Tab bar -->
            <div class="border-b border-gray-200 flex" id="tab-bar">
                <?php
                $tabs = [
                    ['id' => 'diagram', 'label' => rx_icon('map', 'w-4 h-4') . ' Flow Diagram'],
                    ['id' => 'logs',    'label' => rx_icon('clipboard', 'w-4 h-4') . ' Execution Logs'],
                    ['id' => 'test',    'label' => '▶ Test Console'],
                ];
                foreach ($tabs as $tab):
                    $isActive2 = ($activeTab === $tab['id']);
                ?>
                <button onclick="switchTab('<?= $tab['id'] ?>')"
                        id="tab-btn-<?= $tab['id'] ?>"
                        class="px-5 py-3.5 text-sm font-medium border-b-2 transition-colors <?= $isActive2
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <?= $tab['label'] ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- ── Tab: Flow Diagram ──────────────────────────────────────── -->
            <div id="tab-diagram" class="tab-panel" style="<?= $activeTab === 'diagram' ? 'display:block;' : 'display:none;' ?>">
                <?php if (empty($nodes)): ?>
                <div class="p-10 text-center">
                    <div class="mb-3"><?= rx_icon('map', 'w-12 h-12', 'mx-auto') ?></div>
                    <p class="text-sm text-gray-500">No nodes yet.</p>
                    <a href="<?= base_url('flows/' . $flow['id'] . '/edit') ?>"
                       class="inline-block mt-3 text-sm text-blue-600 hover:text-blue-700 font-medium">Open Editor →</a>
                </div>
                <?php else: ?>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.60/dist/drawflow.min.css">
                <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.60/dist/drawflow.min.js"></script>
                <div id="diagram-canvas" style="height: 420px; background: #f8fafc;"></div>
                <div class="px-4 py-2 border-t border-gray-100 text-xs text-gray-400 bg-gray-50">
                    Read-only diagram — <a href="<?= base_url('flows/' . $flow['id'] . '/edit') ?>" class="text-blue-500 hover:underline">open editor</a> to make changes
                </div>
                <script>
                (() => {
                    const df = new Drawflow(document.getElementById('diagram-canvas'));
                    df.start();
                    df.import(<?= json_encode($drawflowData, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>);
                    df.editor_mode = 'fixed';  // read-only
                })();
                </script>
                <?php endif; ?>
            </div>

            <!-- ── Tab: Execution Logs ────────────────────────────────────── -->
            <div id="tab-logs" class="tab-panel" style="<?= $activeTab === 'logs' ? 'display:block;' : 'display:none;' ?>">

                <!-- Filter bar -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                    <span class="text-xs text-gray-500">Filter:</span>
                    <?php foreach (['all', 'active', 'completed', 'handed_off', 'timed_out', 'failed'] as $f): ?>
                    <button onclick="filterLogs('<?= $f ?>')"
                            id="log-filter-<?= $f ?>"
                            class="log-filter px-2.5 py-1 text-xs rounded-full border transition-colors <?= $f === 'all' ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 text-gray-600 hover:border-gray-300' ?>">
                        <?= ucfirst(str_replace('_', ' ', $f)) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($runs)): ?>
                <div class="p-10 text-center">
                    <div class="mb-2"><?= rx_icon('inbox-empty', 'w-12 h-12', 'mx-auto') ?></div>
                    <p class="text-sm text-gray-500">No executions yet.</p>
                    <p class="text-xs text-gray-400 mt-1">Runs appear when a contact sends a trigger keyword.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="logs-table">
                        <thead>
                            <tr class="bg-gray-50 text-left">
                                <th class="px-4 py-3 text-xs font-medium text-gray-500">Contact</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500">Current Node</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500">Started</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500">Last Update</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($runs as $run):
                                $sc = $statusColors[$run['status']] ?? 'bg-gray-100 text-gray-600';
                            ?>
                            <tr class="hover:bg-gray-50 log-row" data-status="<?= esc($run['status']) ?>">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">
                                    <?= esc(substr($run['contact_id'], 0, 8)) ?>…
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?= $sc ?>">
                                        <?= esc($run['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">
                                    <?= esc($run['current_node_key'] ?? '—') ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    <?= esc($run['started_at'] ?? '—') ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    <?= esc($run['updated_at'] ?? '—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-2 border-t border-gray-100 text-xs text-gray-400 bg-gray-50">
                    Showing last <?= count($runs) ?> runs
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Tab: Test Console ──────────────────────────────────────── -->
            <div id="tab-test" class="tab-panel flex flex-col" style="height: 520px; <?= $activeTab === 'test' ? 'display:flex;' : 'display:none;' ?>">

                <!-- Chat messages -->
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
                    <div class="text-center text-xs text-gray-400 py-2" id="chat-intro">
                        Test console ready. Type any message to trigger the flow.
                    </div>
                </div>

                <!-- State bar -->
                <div class="bg-blue-50 border-t border-blue-100 px-4 py-2 text-xs text-gray-600 flex items-center gap-4">
                    <span>Node: <code id="state-node" class="font-mono bg-white px-1 rounded">—</code></span>
                    <span>Waiting: <code id="state-waiting" class="font-mono bg-white px-1 rounded">—</code></span>
                    <span class="flex-1 min-w-0 truncate">Vars: <code id="state-vars" class="font-mono">{}</code></span>
                </div>

                <!-- Input -->
                <div class="border-t border-gray-200 bg-white px-4 py-3 flex gap-2">
                    <input type="text" id="test-input"
                           placeholder="Type a message and press Enter…"
                           class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400">
                    <button onclick="sendTestMessage()"
                            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
                        Send
                    </button>
                    <button onclick="resetTest()"
                            class="px-4 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-600">
                        Reset
                    </button>
                </div>
            </div>

        </div><!-- /tab container -->
    </div>
</div>

<!-- ── Scripts ──────────────────────────────────────────────────────────────── -->
<script>
const FLOW_ID        = <?= json_encode($flow['id']) ?>;
const TEST_MSG_URL   = <?= json_encode(base_url('flows/' . $flow['id'] . '/test-message'), JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const TEST_RESET_URL = <?= json_encode(base_url('flows/' . $flow['id'] . '/test-reset'),   JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const CI_CSRF_TOKEN  = document.cookie.match(/csrf_token=([^;]+)/)?.[1] ?? '';

// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(id) {
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('[id^="tab-btn-"]').forEach(b => {
        b.className = b.className.replace('border-blue-600 text-blue-600', 'border-transparent text-gray-500 hover:text-gray-700');
    });
    const panel = document.getElementById('tab-' + id);
    if (panel) panel.style.display = id === 'test' ? 'flex' : 'block';
    const btn = document.getElementById('tab-btn-' + id);
    if (btn) btn.className = btn.className.replace('border-transparent text-gray-500 hover:text-gray-700', 'border-blue-600 text-blue-600');
}

// ── Log filter ─────────────────────────────────────────────────────────────
function filterLogs(status) {
    document.querySelectorAll('.log-filter').forEach(b => {
        const isThis = b.id === 'log-filter-' + status;
        b.className = b.className
            .replace('bg-blue-600 text-white border-blue-600', 'border-gray-200 text-gray-600 hover:border-gray-300')
            .replace('border-gray-200 text-gray-600 hover:border-gray-300', 'border-gray-200 text-gray-600 hover:border-gray-300');
        if (isThis) b.className = b.className.replace('border-gray-200 text-gray-600 hover:border-gray-300', 'bg-blue-600 text-white border-blue-600');
    });
    document.querySelectorAll('.log-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}

// ── Test Console ───────────────────────────────────────────────────────────
document.getElementById('test-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') sendTestMessage();
});

async function sendTestMessage() {
    const input = document.getElementById('test-input');
    const msg   = input.value.trim();
    if (!msg) return;

    addChatMessage('user', msg);
    input.value = '';
    input.disabled = true;

    try {
        const res  = await fetch(TEST_MSG_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ message: msg }),
        });
        const data = await res.json();

        if (data.error) {
            addChatMessage('system', '<?= rx_icon('warning', 'w-4 h-4') ?> ' + data.error);
        } else {
            for (const r of (data.responses || [])) {
                renderResponse(r);
            }
            updateStateBar(data);
        }
    } catch (err) {
        addChatMessage('system', '<?= rx_icon('warning', 'w-4 h-4') ?> Network error');
    }

    input.disabled = false;
    input.focus();
}

function renderResponse(r) {
    if (r.type === 'text') {
        addChatMessage('bot', escHtml(r.text));
    } else if (r.type === 'buttons') {
        let html = `<div class="font-medium mb-2">${escHtml(r.text)}</div>`;
        html += '<div class="flex flex-wrap gap-2">';
        for (const btn of (r.buttons || [])) {
            html += `<button onclick="document.getElementById('test-input').value='${escHtml(btn.title)}'; sendTestMessage();"
                             class="px-3 py-1 border border-blue-300 text-blue-700 rounded-full text-xs hover:bg-blue-50">
                         ${escHtml(btn.title)}
                     </button>`;
        }
        html += '</div>';
        addChatMessage('bot', html, true);
    } else if (r.type === 'list') {
        let html = `<div><?= rx_icon('clipboard', 'w-4 h-4') ?> <strong>${escHtml(r.text || 'List Options')}</strong></div>`;
        if (r.button) {
            html += `<div class="text-xs text-gray-400 mt-1 mb-2">[${escHtml(r.button)}]</div>`;
        }
        html += '<div class="space-y-3 mt-2">';
        for (const sec of (r.sections || [])) {
            if (sec.title) {
                html += `<div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">${escHtml(sec.title)}</div>`;
            }
            html += '<div class="flex flex-col gap-1.5">';
            for (const row of (sec.rows || [])) {
                html += `<button onclick="document.getElementById('test-input').value='${escHtml(row.title)}'; sendTestMessage();"
                                 class="w-full text-left px-3 py-2 border border-gray-200 rounded-lg text-xs hover:bg-gray-50 flex flex-col transition-all">
                             <span class="font-medium text-blue-600">${escHtml(row.title)}</span>
                             ${row.description ? `<span class="text-gray-400 text-[10px] mt-0.5">${escHtml(row.description)}</span>` : ''}
                         </button>`;
            }
            html += '</div>';
        }
        html += '</div>';
        addChatMessage('bot', html, true);
    } else if (r.type === 'media') {
        let html = '';
        if (r.media_type === 'image' && r.url) {
            html += `<img src="${escHtml(r.url)}" alt="Media image" class="max-w-full h-auto rounded-lg mb-1.5 border border-gray-100">`;
        } else {
            html += `${r.media_type === 'image' ? '<?= rx_icon('image', 'w-4 h-4') ?>' : '<?= rx_icon('paperclip', 'w-4 h-4') ?>'} <span class="text-xs text-gray-500">${escHtml(r.url)}</span>`;
        }
        if (r.caption) {
            html += `${html ? '<br>' : ''}<span class="text-xs">${escHtml(r.caption)}</span>`;
        }
        addChatMessage('bot', html, true);
    } else if (r.type === 'media_buttons') {
        let html = '';
        if (r.media_type === 'image' && r.url) {
            html += `<img src="${escHtml(r.url)}" alt="Media image" class="max-w-full h-auto rounded-lg mb-2 border border-gray-100">`;
        } else if (r.media_type === 'video' && r.url) {
            html += `<video src="${escHtml(r.url)}" controls class="max-w-full h-auto rounded-lg mb-2 border border-gray-100"></video>`;
        } else {
            html += `<div class="mb-2">${r.media_type === 'image' ? '<?= rx_icon('image', 'w-4 h-4') ?>' : '<?= rx_icon('video', 'w-4 h-4') ?>'} <span class="text-xs text-gray-400">${escHtml(r.url)}</span></div>`;
        }
        html += `<div class="mb-2 text-sm">${escHtml(r.text)}</div><div class="flex flex-wrap gap-2">`;
        for (const btn of (r.buttons || [])) {
            html += `<button onclick="document.getElementById('test-input').value='${escHtml(btn.title)}'; sendTestMessage();"
                             class="px-3 py-1 border border-blue-300 text-blue-700 rounded-full text-xs hover:bg-blue-50">
                         ${escHtml(btn.title)}
                     </button>`;
        }
        html += '</div>';
        addChatMessage('bot', html, true);
    } else if (r.type === 'url_button') {
        const html = `<div class="mb-2 text-sm">${escHtml(r.text)}</div>`
            + (r.footer ? `<div class="text-xs text-gray-400 mb-2">${escHtml(r.footer)}</div>` : '')
            + `<a href="${escHtml(r.button_url)}" target="_blank" rel="noopener"
                  class="inline-block px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 font-medium">
                  <?= rx_icon('link', 'w-4 h-4') ?> ${escHtml(r.button_text)}
               </a>`;
        addChatMessage('bot', html, true);
    } else if (r.type === 'location_request') {
        const html = `<div class="mb-2 text-sm">${escHtml(r.text)}</div>`
            + `<div class="text-xs text-gray-400 italic"><?= rx_icon('pin', 'w-4 h-4') ?> Type any text to simulate a location (e.g. "12.9716,77.5946")</div>`;
        addChatMessage('bot', html, true);
    } else if (r.type === 'system') {
        addChatMessage('system', r.text);
    }
}

function addChatMessage(type, html, isHtml = false) {
    const container = document.getElementById('chat-messages');
    const intro = document.getElementById('chat-intro');
    if (intro) intro.remove();

    const wrap = document.createElement('div');
    const inner = document.createElement('div');

    if (type === 'user') {
        wrap.className = 'flex justify-end';
        inner.className = 'bg-blue-600 text-white px-3 py-2 rounded-xl rounded-tr-sm max-w-xs text-sm';
    } else if (type === 'bot') {
        wrap.className = 'flex justify-start';
        inner.className = 'bg-white border border-gray-200 px-3 py-2 rounded-xl rounded-tl-sm max-w-xs text-sm shadow-sm';
    } else {
        wrap.className = 'flex justify-center';
        inner.className = 'bg-gray-100 text-gray-500 text-xs px-3 py-1 rounded-full italic';
    }

    if (isHtml) inner.innerHTML = html;
    else inner.innerHTML = html;

    wrap.appendChild(inner);
    container.appendChild(wrap);
    container.scrollTop = container.scrollHeight;
}

function updateStateBar(data) {
    document.getElementById('state-node').textContent    = data.current_node || '—';
    document.getElementById('state-vars').textContent    = JSON.stringify(data.vars || {});
    if (data.is_complete) {
        document.getElementById('test-input').disabled = true;
        document.getElementById('state-waiting').textContent = 'done';
    }
}

async function resetTest() {
    await fetch(TEST_RESET_URL, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    document.getElementById('chat-messages').innerHTML =
        '<div class="text-center text-xs text-gray-400 py-2" id="chat-intro">Flow reset. Type any message to start again.</div>';
    document.getElementById('state-node').textContent    = '—';
    document.getElementById('state-vars').textContent    = '{}';
    document.getElementById('state-waiting').textContent = '—';
    document.getElementById('test-input').disabled = false;
    document.getElementById('test-input').focus();
}

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?= $this->endSection() ?>
