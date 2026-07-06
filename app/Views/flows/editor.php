<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= @filemtime(FCPATH . 'css/tailwind.css') ?: time() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.60/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.60/dist/drawflow.min.js"></script>
    <style>
        html, body { height: 100%; overflow: hidden; }

        #drawflow { width: 100%; height: 100%; background: #f8fafc; }

        .drawflow .drawflow-node {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            background: white;
            min-width: 200px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 0;
        }
        .drawflow .drawflow-node.selected {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
        }
        .drawflow .drawflow-node .drawflow-node-content { padding: 0; }

        .df-node-header {
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .df-node-body {
            padding: 10px 14px;
            font-size: 12px;
            color: #6b7280;
            min-height: 36px;
            word-break: break-word;
        }

        /* Palette items */
        .palette-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            cursor: grab;
            background: white;
            transition: all 0.15s;
            user-select: none;
        }
        .palette-item:hover { border-color: #3b82f6; background: #eff6ff; }
        .palette-item:active { cursor: grabbing; }

        /* Config panel inputs */
        .cfg-input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
        }
        .cfg-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
        .cfg-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 4px;
        }

        /* Tag-style keyword input */
        .keyword-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 12px;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php
$flowId       = $flow['id']         ?? '';
$flowName     = $flow['name']       ?? '';
$flowActive   = $flow['is_active']  ?? 1;
$rawKeywords  = json_decode($flow['trigger_keywords'] ?? '[]', true) ?? [];
$isEdit       = !empty($flowId);
$saveUrl      = $isEdit ? base_url('flows/' . $flowId) : base_url('flows');
?>

<div class="flex h-screen">

    <!-- ── Left Sidebar: Palette ─────────────────────────────────────────── -->
    <div class="w-56 bg-gray-50 border-r border-gray-200 flex flex-col">
        <div class="p-3 border-b border-gray-200">
            <a href="<?= base_url('flows') ?>"
               class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-800 mb-2">
                ← Flows
            </a>
            <div class="font-semibold text-sm text-gray-700">Node Palette</div>
            <div class="text-xs text-gray-400 mt-0.5">Drag onto canvas</div>
        </div>
        <div id="palette" class="flex-1 overflow-y-auto p-3 space-y-2"></div>
        <div class="p-3 border-t border-gray-200 bg-blue-50 text-xs text-gray-500 space-y-1">
            <div>• Drag nodes to canvas</div>
            <div>• Click node to configure</div>
            <div>• Draw lines to connect</div>
            <div>• Del key removes selected</div>
        </div>
    </div>

    <!-- ── Main Area ──────────────────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- Toolbar -->
        <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3">
            <input type="text" id="flow-name" value="<?= esc($flowName) ?>"
                   placeholder="Flow name…"
                   class="flex-1 text-base font-semibold border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-blue-400">

            <div class="flex items-center gap-2 ml-2">
                <span class="text-xs text-gray-500">Keywords:</span>
                <div id="kw-tags" class="flex items-center flex-wrap gap-1"></div>
                <input type="text" id="kw-input" placeholder="Add keyword + Enter"
                       class="text-xs border border-gray-200 rounded-lg px-2 py-1 w-36 focus:outline-none focus:border-blue-400">
            </div>

            <label class="flex items-center gap-2 text-sm cursor-pointer ml-2">
                <input type="checkbox" id="flow-active" <?= $flowActive ? 'checked' : '' ?>
                       class="w-4 h-4 rounded text-blue-600">
                Active
            </label>

            <button id="btn-save"
                    class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
                Save Flow
            </button>
            <a href="<?= base_url('flows') ?>"
               class="px-4 py-1.5 border border-gray-300 text-sm rounded-lg hover:bg-gray-50 text-gray-600">
                Cancel
            </a>
        </div>

        <!-- Canvas -->
        <div class="flex-1 relative overflow-hidden">
            <div id="drawflow"></div>

            <!-- Mini status bar -->
            <div id="status-bar"
                 class="absolute bottom-3 left-3 bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-500 shadow-sm pointer-events-none">
                Nodes: <span id="node-count">0</span> &nbsp;|&nbsp; Click a node to configure
            </div>
        </div>
    </div>

    <!-- ── Right Sidebar: Config Panel ───────────────────────────────────── -->
    <div id="config-panel"
         class="w-72 bg-white border-l border-gray-200 flex flex-col"
         style="display:none !important;">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
            <div>
                <div id="cfg-node-title" class="font-semibold text-sm text-gray-800"></div>
                <div id="cfg-node-key" class="text-xs text-gray-400 mt-0.5 font-mono"></div>
            </div>
            <button onclick="closeConfig()" class="text-gray-400 hover:text-gray-700 text-lg leading-none">×</button>
        </div>
        <div id="cfg-fields" class="flex-1 overflow-y-auto p-4 space-y-4"></div>
        <div class="p-4 border-t border-gray-200">
            <button onclick="applyConfig()"
                    class="w-full py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
                Apply
            </button>
        </div>
    </div>
</div>

<script>
const SAVE_URL    = <?= json_encode($saveUrl, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const IS_EDIT     = <?= $isEdit ? 'true' : 'false' ?>;
const INIT_DATA   = <?= $drawflowData ? json_encode($drawflowData, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) : 'null' ?>;
const NODE_SCHEMAS = <?= json_encode($nodeSchemas, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const FLOW_TAGS    = <?= json_encode($tags   ?? [], JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const FLOW_AGENTS  = <?= json_encode($agents ?? [], JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

let editor;
let selectedNodeId = null;
let keywords = <?= json_encode($rawKeywords) ?>;

// ── Init ────────────────────────────────────────────────────────────────────
(() => {
    const container = document.getElementById('drawflow');
    editor = new Drawflow(container);
    editor.reroute = true;
    editor.start();

    buildPalette();
    renderKeywordTags();

    if (INIT_DATA) {
        editor.import(INIT_DATA);
        // Re-render node HTML after import (Drawflow doesn't restore custom HTML on import)
        rebuildNodeHtml();
    } else {
        addNode('start', 80, 100);
    }

    updateNodeCount();

    // Events
    editor.on('nodeSelected',   id => openConfig(id));
    editor.on('nodeUnselected', ()  => closeConfig());
    editor.on('nodeCreated',    ()  => updateNodeCount());
    editor.on('nodeRemoved',    ()  => updateNodeCount());

    // Keyboard
    document.addEventListener('keydown', e => {
        if (e.key === 'Delete' && selectedNodeId && document.activeElement.tagName === 'BODY') {
            editor.removeNodeId('node-' + selectedNodeId);
            closeConfig();
        }
    });

    // Canvas drag-drop
    container.addEventListener('dragover', e => e.preventDefault());
    container.addEventListener('drop', e => {
        e.preventDefault();
        const type = e.dataTransfer.getData('node-type');
        if (!type) return;
        const rect = container.getBoundingClientRect();
        addNode(type, e.clientX - rect.left - editor.precanvas.getBoundingClientRect().left,
                      e.clientY - rect.top  - editor.precanvas.getBoundingClientRect().top);
    });

    // Keyword input
    document.getElementById('kw-input').addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const val = e.target.value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '');
            if (val && !keywords.includes(val)) {
                keywords.push(val);
                renderKeywordTags();
            }
            e.target.value = '';
        }
    });

    // Save button
    document.getElementById('btn-save').addEventListener('click', saveFlow);
})();

// ── Palette ─────────────────────────────────────────────────────────────────
function buildPalette() {
    const palette = document.getElementById('palette');
    for (const [type, schema] of Object.entries(NODE_SCHEMAS)) {
        const el = document.createElement('div');
        el.className = 'palette-item';
        el.draggable = true;
        el.innerHTML = `<span class="w-8 h-8 rounded-lg flex items-center justify-center text-white flex-shrink-0" style="background:${schema.color}">${schema.icon}</span>
                        <span class="text-xs font-medium text-gray-700">${schema.name}</span>`;
        el.addEventListener('dragstart', ev => ev.dataTransfer.setData('node-type', type));
        el.addEventListener('click',     ()  => addNode(type, 200 + Math.random()*200, 100 + Math.random()*200));
        palette.appendChild(el);
    }
}

// ── Add Node ────────────────────────────────────────────────────────────────
function addNode(type, x, y) {
    const schema  = NODE_SCHEMAS[type];
    if (!schema) return;

    const inputs  = (type === 'start') ? 0 : 1;
    const outputs = schema.has_multiple_outputs
        ? (type === 'condition' ? 2 : (schema.outputs?.length ?? 1))
        : (schema.has_single_output ? 1 : 0);

    const nodeId = editor.addNode(
        type, inputs, outputs, x, y, type,
        { type, config: {}, node_key: '' },
        buildNodeHtml(type, schema, {})
    );

    // Assign node_key = df_{id}
    const nodeData = editor.getNodeFromId(nodeId);
    nodeData.data.node_key = 'df_' + nodeId;
    editor.updateNodeDataFromId(nodeId, nodeData.data);

    // Label outputs for condition
    if (type === 'condition') {
        labelOutputs(nodeId, ['TRUE', 'FALSE']);
    }

    updateNodeCount();
    return nodeId;
}

function buildNodeHtml(type, schema, config) {
    const preview = getPreviewText(type, config);
    const bg      = schema.color + '18';
    const border  = schema.color;
    return `<div class="df-node-header" style="background:${bg}; border-bottom-color:${border}40;">
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-white flex-shrink-0" style="background:${schema.color}">${schema.icon}</span>
                <span>${schema.name}</span>
                <span class="ml-auto text-xs font-mono text-gray-400" id="nk-{{id}}"></span>
            </div>
            <div class="df-node-body" id="np-{{id}}">${preview || '<em>Click to configure</em>'}</div>`;
}

function getPreviewText(type, config) {
    if (!config) return '';
    if (type === 'send_message')  return esc(config.message_text || '');
    if (type === 'send_buttons')  return esc(config.body_text    || '');
    if (type === 'collect_input') return esc(config.prompt_text  || '');
    if (type === 'send_media')    return esc(config.media_url    || '');
    if (type === 'condition')     return esc(config.condition_type || '');
    if (type === 'set_tag')       return esc((config.action || 'add') + ' tag');
    if (type === 'handoff')       return 'Handoff to agent';
    if (type === 'end')           return 'End of flow';
    if (type === 'http_request')  return esc((config.method || 'GET') + ' ' + (config.url || ''));
    return '';
}

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function labelOutputs(nodeId, labels) {
    setTimeout(() => {
        const el = document.querySelector(`#node-${nodeId} .outputs`);
        if (!el) return;
        el.querySelectorAll('.output').forEach((out, i) => {
            if (!out.querySelector('.out-label')) {
                const span = document.createElement('span');
                span.className = 'out-label';
                span.style.cssText = 'font-size:10px;color:#6b7280;position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none';
                span.textContent = labels[i] || '';
                out.style.position = 'relative';
                out.appendChild(span);
            }
        });
    }, 50);
}

function rebuildNodeHtml() {
    // The server intentionally leaves html: '' for existing nodes (it can't
    // build the styled markup in PHP) and expects this to fill it in after
    // import. Patching editor.drawflow's internal data object alone does
    // nothing — Drawflow already painted the (blank) DOM during
    // editor.import(), so the actual node content in the page must be
    // written directly too.
    const allNodes = editor.export().drawflow.Home.data;
    for (const [id, node] of Object.entries(allNodes)) {
        const schema = NODE_SCHEMAS[node.name];
        if (!schema) continue;
        const config = node.data?.config ?? {};
        const html   = buildNodeHtml(node.name, schema, config).replace(/\{\{id\}\}/g, id);

        editor.drawflow.drawflow.Home.data[id].html = html;

        const contentEl = document.querySelector('#node-' + id + ' .drawflow_content_node');
        if (contentEl) contentEl.innerHTML = html;

        if (node.name === 'condition') labelOutputs(id, ['TRUE', 'FALSE']);
    }
}

// ── Config Panel ─────────────────────────────────────────────────────────────
function openConfig(nodeId) {
    selectedNodeId = nodeId;
    const nodeData = editor.getNodeFromId(nodeId);
    const type     = nodeData.data.type;
    const schema   = NODE_SCHEMAS[type];
    const config   = nodeData.data.config || {};
    const nodeKey  = nodeData.data.node_key || ('df_' + nodeId);

    document.getElementById('cfg-node-title').textContent = schema?.name || type;
    document.getElementById('cfg-node-key').textContent   = nodeKey;

    const fieldsEl = document.getElementById('cfg-fields');
    fieldsEl.innerHTML = '';

    if (!schema?.config_fields?.length) {
        fieldsEl.innerHTML = '<p class="text-xs text-gray-400 italic">No configuration for this node.</p>';
    } else {
        for (const field of schema.config_fields) {
            fieldsEl.appendChild(renderField(field, config));
        }
    }

    const panel = document.getElementById('config-panel');
    panel.style.cssText = 'display:flex !important; flex-direction:column;';
}

function closeConfig() {
    selectedNodeId = null;
    document.getElementById('config-panel').style.cssText = 'display:none !important;';
}

function renderField(field, config) {
    const wrap = document.createElement('div');
    const val  = config[field.name] !== undefined ? config[field.name] : (field.default ?? '');

    const labelEl = document.createElement('label');
    labelEl.className = 'cfg-label';
    labelEl.textContent = field.label + (field.required ? ' *' : '');
    labelEl.setAttribute('for', 'cfg_' + field.name);
    wrap.appendChild(labelEl);

    if (field.type === 'textarea') {
        const ta = document.createElement('textarea');
        ta.id = 'cfg_' + field.name;
        ta.className = 'cfg-input';
        ta.rows = 3;
        ta.placeholder = field.placeholder || '';
        ta.value = val;
        wrap.appendChild(ta);
    } else if (field.type === 'select') {
        const sel = document.createElement('select');
        sel.id = 'cfg_' + field.name;
        sel.className = 'cfg-input';
        for (const opt of (field.options || [])) {
            const o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.label;
            if (String(val) === String(opt.value)) o.selected = true;
            sel.appendChild(o);
        }
        wrap.appendChild(sel);
    } else if (field.type === 'button_list') {
        wrap.appendChild(renderButtonList(field, Array.isArray(val) ? val : []));
    } else if (field.type === 'list_sections' || field.type === 'form_fields' || field.type === 'key_value_list') {
        wrap.appendChild(renderRepeatable(field, Array.isArray(val) ? val : []));
    } else if (field.type === 'validation_rules') {
        // Same shape as 'select' — schema already provides the options list.
        const sel = document.createElement('select');
        sel.id = 'cfg_' + field.name;
        sel.className = 'cfg-input';
        for (const opt of (field.options || [])) {
            const o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.label;
            if (String(val) === String(opt.value)) o.selected = true;
            sel.appendChild(o);
        }
        wrap.appendChild(sel);
    } else if (field.type === 'tag_select') {
        const sel = document.createElement('select');
        sel.id = 'cfg_' + field.name;
        sel.className = 'cfg-input';
        const none = document.createElement('option');
        none.value = '';
        none.textContent = '— Select tag —';
        sel.appendChild(none);
        for (const t of FLOW_TAGS) {
            const o = document.createElement('option');
            o.value = t.id;
            o.textContent = t.name;
            if (String(val) === String(t.id)) o.selected = true;
            sel.appendChild(o);
        }
        wrap.appendChild(sel);
        if (!FLOW_TAGS.length) {
            const hint = document.createElement('p');
            hint.className = 'text-xs text-amber-600 mt-1';
            hint.textContent = 'No tags yet — create one in Settings → Tags first.';
            wrap.appendChild(hint);
        }
    } else if (field.type === 'agent_select') {
        const sel = document.createElement('select');
        sel.id = 'cfg_' + field.name;
        sel.className = 'cfg-input';
        const none = document.createElement('option');
        none.value = '';
        none.textContent = '— Any available agent —';
        sel.appendChild(none);
        for (const a of FLOW_AGENTS) {
            const o = document.createElement('option');
            o.value = a.user_id;
            o.textContent = a.full_name;
            if (String(val) === String(a.user_id)) o.selected = true;
            sel.appendChild(o);
        }
        wrap.appendChild(sel);
    } else if (field.type === 'node_select') {
        // Show a dropdown of current canvas nodes
        const sel = document.createElement('select');
        sel.id = 'cfg_' + field.name;
        sel.className = 'cfg-input';
        const none = document.createElement('option');
        none.value = '';
        none.textContent = '— None —';
        sel.appendChild(none);
        const dfData = editor.export().drawflow.Home.data;
        for (const [id, n] of Object.entries(dfData)) {
            if ('node-' + id === 'node-' + selectedNodeId) continue;
            const o = document.createElement('option');
            o.value = 'df_' + id;
            o.textContent = `df_${id} — ${NODE_SCHEMAS[n.name]?.name || n.name}`;
            if (val === 'df_' + id) o.selected = true;
            sel.appendChild(o);
        }
        wrap.appendChild(sel);
    } else {
        const inp = document.createElement('input');
        inp.type = (field.type === 'url') ? 'url' : 'text';
        inp.id = 'cfg_' + field.name;
        inp.className = 'cfg-input';
        inp.placeholder = field.placeholder || '';
        inp.value = val;
        wrap.appendChild(inp);
    }

    if (field.help) {
        const hint = document.createElement('p');
        hint.className = 'text-xs text-gray-400 mt-1';
        hint.textContent = field.help;
        wrap.appendChild(hint);
    }

    return wrap;
}

function renderButtonList(field, buttons) {
    const container = document.createElement('div');
    container.id    = 'cfg_' + field.name + '_container';
    container.className = 'space-y-3';

    const maxItems = field.max_items || 3;

    function addButtonRow(btn) {
        const row = document.createElement('div');
        row.className = 'border border-gray-200 rounded-lg p-2 space-y-2 bg-gray-50';

        for (const subField of (field.item_schema || [])) {
            const subWrap = document.createElement('div');
            const lbl = document.createElement('label');
            lbl.className = 'cfg-label';
            lbl.textContent = subField.label;
            subWrap.appendChild(lbl);

            if (subField.type === 'node_select') {
                const sel = document.createElement('select');
                sel.className = 'cfg-input btn-next-node';
                const none = document.createElement('option');
                none.value = '';
                none.textContent = '— None —';
                sel.appendChild(none);
                const dfData = editor.export().drawflow.Home.data;
                for (const [id, n] of Object.entries(dfData)) {
                    if ('node-' + id === 'node-' + selectedNodeId) continue;
                    const o = document.createElement('option');
                    o.value = 'df_' + id;
                    o.textContent = `df_${id} — ${NODE_SCHEMAS[n.name]?.name || n.name}`;
                    if ((btn[subField.name] || '') === 'df_' + id) o.selected = true;
                    sel.appendChild(o);
                }
                subWrap.appendChild(sel);
            } else {
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.className = 'cfg-input btn-field-' + subField.name;
                inp.placeholder = subField.placeholder || '';
                inp.value = btn[subField.name] || '';
                subWrap.appendChild(inp);
            }
            row.appendChild(subWrap);
        }

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'text-xs text-red-500 hover:text-red-700';
        removeBtn.textContent = '− Remove button';
        removeBtn.onclick = () => row.remove();
        row.appendChild(removeBtn);

        container.appendChild(row);
    }

    for (const btn of buttons) addButtonRow(btn);

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'text-xs text-blue-600 hover:text-blue-800 font-medium';
    addBtn.textContent = '+ Add Button';
    addBtn.onclick = () => {
        if (container.querySelectorAll('.border').length < maxItems) addButtonRow({});
        else alert(`Maximum ${maxItems} buttons.`);
    };
    container.appendChild(addBtn);

    return container;
}

const REPEATABLE_ITEM_LABEL = {
    sections: 'Section', rows: 'Item', fields: 'Field',
    headers: 'Header', body_fields: 'Field', response_mapping: 'Mapping',
};

// Generic nested repeatable-list builder — used for Send List's sections
// (which themselves contain a repeatable list of rows) and Collect Form's
// question list. Distinct from renderButtonList (kept as-is, already proven)
// to avoid risking a regression in the working buttons feature.
function renderRepeatable(field, items, level) {
    level = level || 0;
    // list_sections uses 'section_schema' as its subfield-list key instead
    // of 'item_schema' (button_list/form_fields use item_schema) — support both.
    const itemSchema = field.item_schema || field.section_schema || [];
    const container = document.createElement('div');
    container.className = 'space-y-3 repeatable-list';
    container.dataset.field = field.name;
    container.dataset.itemSchema = JSON.stringify(itemSchema);
    if (level === 0) container.id = 'cfg_' + field.name + '_container';

    const maxItems = field.max_items || field.max_sections || 20;

    function addRow(item) {
        const row = document.createElement('div');
        row.className = 'border border-gray-200 rounded-lg p-2 space-y-2 bg-gray-50 repeatable-row';

        for (const subField of itemSchema) {
            const subWrap = document.createElement('div');
            const lbl = document.createElement('label');
            lbl.className = 'cfg-label';
            lbl.textContent = subField.label + (subField.required ? ' *' : '');
            subWrap.appendChild(lbl);

            if (subField.type === 'node_select') {
                subWrap.appendChild(buildNodeSelect(item[subField.name] || '', 'repeatable-field'));
                subWrap.lastChild.dataset.field = subField.name;
            } else if (subField.type === 'list') {
                const nested = renderRepeatable(subField, Array.isArray(item[subField.name]) ? item[subField.name] : [], level + 1);
                subWrap.appendChild(nested);
            } else if (subField.type === 'select') {
                const sel = document.createElement('select');
                sel.className = 'cfg-input repeatable-field';
                sel.dataset.field = subField.name;
                for (const opt of (subField.options || [])) {
                    const o = document.createElement('option');
                    o.value = opt.value;
                    o.textContent = opt.label;
                    if ((item[subField.name] || '') === opt.value) o.selected = true;
                    sel.appendChild(o);
                }
                subWrap.appendChild(sel);
            } else {
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.className = 'cfg-input repeatable-field';
                inp.dataset.field = subField.name;
                inp.placeholder = subField.placeholder || '';
                inp.value = item[subField.name] || '';
                subWrap.appendChild(inp);
            }
            row.appendChild(subWrap);
        }

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'text-xs text-red-500 hover:text-red-700';
        removeBtn.textContent = '− Remove ' + (REPEATABLE_ITEM_LABEL[field.name] || 'Item');
        removeBtn.onclick = () => row.remove();
        row.appendChild(removeBtn);

        container.appendChild(row);
    }

    for (const it of items) addRow(it);

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'text-xs text-blue-600 hover:text-blue-800 font-medium';
    addBtn.textContent = '+ Add ' + (REPEATABLE_ITEM_LABEL[field.name] || 'Item');
    addBtn.onclick = () => {
        if (container.querySelectorAll(':scope > .repeatable-row').length < maxItems) addRow({});
        else alert(`Maximum ${maxItems} items.`);
    };
    container.appendChild(addBtn);

    return container;
}

function collectRepeatable(container) {
    const itemSchema = JSON.parse(container.dataset.itemSchema || '[]');
    const rows = container.querySelectorAll(':scope > .repeatable-row');
    const items = [];
    rows.forEach(row => {
        const item = {};
        for (const sf of itemSchema) {
            if (sf.type === 'list') {
                const nested = row.querySelector(':scope > div > .repeatable-list[data-field="' + sf.name + '"]');
                item[sf.name] = nested ? collectRepeatable(nested) : [];
            } else {
                const el = row.querySelector('.repeatable-field[data-field="' + sf.name + '"]');
                item[sf.name] = el ? el.value : '';
            }
        }
        items.push(item);
    });
    return items;
}

function buildNodeSelect(selectedVal, extraClass) {
    const sel = document.createElement('select');
    sel.className = 'cfg-input btn-next-node' + (extraClass ? ' ' + extraClass : '');
    const none = document.createElement('option');
    none.value = '';
    none.textContent = '— None —';
    sel.appendChild(none);
    const dfData = editor.export().drawflow.Home.data;
    for (const [id, n] of Object.entries(dfData)) {
        if ('node-' + id === 'node-' + selectedNodeId) continue;
        const o = document.createElement('option');
        o.value = 'df_' + id;
        o.textContent = `df_${id} — ${NODE_SCHEMAS[n.name]?.name || n.name}`;
        if (selectedVal === 'df_' + id) o.selected = true;
        sel.appendChild(o);
    }
    return sel;
}

function applyConfig() {
    if (!selectedNodeId) return;

    const nodeData = editor.getNodeFromId(selectedNodeId);
    const type     = nodeData.data.type;
    const schema   = NODE_SCHEMAS[type];
    const config   = {};

    for (const field of (schema?.config_fields || [])) {
        if (field.type === 'button_list') {
            const rows = document.querySelectorAll('#cfg_' + field.name + '_container > .border');
            const buttons = [];
            // Clear all possible button output connections first to avoid leftovers
            const maxButtons = field.max_items || 3;
            for (let i = 1; i <= maxButtons; i++) {
                syncConnection(selectedNodeId, 'output_' + i, null);
            }
            rows.forEach((row, i) => {
                const btn = {};
                for (const sf of (field.item_schema || [])) {
                    if (sf.type === 'node_select') {
                        btn[sf.name] = row.querySelector('.btn-next-node')?.value || '';
                    } else {
                        btn[sf.name] = row.querySelector('.btn-field-' + sf.name)?.value || '';
                    }
                }
                buttons.push(btn);
                // Sync connection for this button's Go To dropdown
                syncConnection(selectedNodeId, 'output_' + (i + 1), btn.next_node);
            });
            config[field.name] = buttons;
        } else if (field.type === 'list_sections' || field.type === 'form_fields' || field.type === 'key_value_list') {
            const container = document.getElementById('cfg_' + field.name + '_container');
            config[field.name] = container ? collectRepeatable(container) : [];
        } else {
            const el = document.getElementById('cfg_' + field.name);
            if (el) config[field.name] = el.value;
        }
    }

    nodeData.data.config = config;
    editor.updateNodeDataFromId(selectedNodeId, nodeData.data);

    // Draw/update the canvas connection line to match each top-level
    // "Next Node" dropdown. Saving derives the actual next_node from the
    // drawn line (see FlowsController::saveNodes) — without this, picking
    // a value in the dropdown looked like it worked but was silently
    // discarded on save unless the user separately dragged a line by hand.
    for (const field of (schema?.config_fields || [])) {
        if (field.type !== 'node_select') continue;
        const outputKey = field.name === 'false_node' ? 'output_2' : 'output_1';
        syncConnection(selectedNodeId, outputKey, config[field.name]);
    }

    // Update node preview text in the DOM
    const previewEl = document.querySelector(`#node-${selectedNodeId} .df-node-body`);
    if (previewEl) {
        const preview = getPreviewText(type, config);
        previewEl.innerHTML = preview || '<em>Configured</em>';
    }

    closeConfig();
}

function syncConnection(nodeId, outputKey, targetNodeKey) {
    try {
        const nodeData = editor.getNodeFromId(nodeId);
        const existing = (nodeData?.outputs?.[outputKey]?.connections || []).slice();
        for (const conn of existing) {
            editor.removeSingleConnection(nodeId, conn.node, outputKey, conn.output);
        }

        if (!targetNodeKey) return;
        const targetId = String(targetNodeKey).replace('df_', '');
        if (!editor.getNodeFromId(targetId)) return;
        editor.addConnection(nodeId, targetId, outputKey, 'input_1');
    } catch (e) {
        // Drawflow throws on stale/invalid ids (e.g. target removed since
        // this dropdown was rendered) — don't let that block saving config.
        console.warn('syncConnection failed', e);
    }
}

// ── Keyword tags ─────────────────────────────────────────────────────────────
function renderKeywordTags() {
    const container = document.getElementById('kw-tags');
    container.innerHTML = '';
    keywords.forEach((kw, i) => {
        const tag = document.createElement('span');
        tag.className = 'keyword-tag';
        tag.innerHTML = `${esc(kw)} <button type="button" onclick="removeKeyword(${i})" class="text-blue-400 hover:text-blue-700 font-bold ml-1">×</button>`;
        container.appendChild(tag);
    });
}

function removeKeyword(index) {
    keywords.splice(index, 1);
    renderKeywordTags();
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function updateNodeCount() {
    const count = Object.keys(editor.export().drawflow.Home.data || {}).length;
    document.getElementById('node-count').textContent = count;
}

// ── Save ─────────────────────────────────────────────────────────────────────
async function saveFlow() {
    const name = document.getElementById('flow-name').value.trim();
    if (!name) { alert('Please enter a flow name.'); return; }
    if (!keywords.length) { alert('Add at least one trigger keyword.'); return; }

    const flowData = editor.export();
    const nodeCount = Object.keys(flowData.drawflow.Home.data || {}).length;
    if (nodeCount === 0) { alert('Add at least one node to the canvas.'); return; }

    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
        const res = await fetch(SAVE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name,
                is_active:        document.getElementById('flow-active').checked ? 1 : 0,
                trigger_keywords: keywords,
                flow_data:        flowData,
            }),
        });

        const data = await res.json();
        if (data.success) {
            window.location.href = <?= json_encode(base_url('flows'), JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
        } else {
            alert(data.error || 'Failed to save flow.');
            btn.disabled = false;
            btn.textContent = 'Save Flow';
        }
    } catch (err) {
        alert('Network error — could not save.');
        btn.disabled = false;
        btn.textContent = 'Save Flow';
    }
}
</script>
</body>
</html>
