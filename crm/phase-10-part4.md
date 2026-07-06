### Prompt 10.3 — Drawflow.js Visual Editor Integration

```
Build the visual flow editor interface using Drawflow.js for Rovix AI Leads Tool.

Reference: src/components/flows/flow-editor.tsx (wacrm original)

IMPORTANT: Drawflow.js is a lightweight drag-and-drop node editor. We'll use it to build the visual flow canvas.

Create app/Controllers/FlowsController.php:

1. index() GET: List all flows
   - Load flows with execution stats
   - Show: name, trigger keywords, is_active, execution_count
   - Pass to view: $flows

2. create() GET: Show flow editor (empty canvas)
   - Pass to view: node type schemas (from FlowNodeSchemas)

3. store() POST: Create new flow
   - Validate: name required, trigger_keywords array
   - Parse flow JSON from editor (nodes + connections)
   - Insert flow record
   - Insert flow_nodes records (each node with position)
   - Redirect to flow detail

4. view($flowId) GET: Show flow detail + execution logs
   - Load flow with nodes
   - Load recent flow_runs
   - Pass to view: $flow, $nodes, $runs

5. edit($flowId) GET: Show flow editor with existing flow
   - Load flow with nodes
   - Build Drawflow JSON structure
   - Pass to view: $flow, $drawflowData

6. update($flowId) POST: Update flow
   - Parse flow JSON from editor
   - Delete old nodes
   - Insert new nodes
   - Redirect with success

7. toggle($flowId) POST: Toggle active/inactive

8. delete($flowId) POST: Delete flow

9. duplicate($flowId) POST: Duplicate flow

Create app/Views/flows/editor.php:

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> - Flow Editor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Drawflow CSS & JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.60/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.60/dist/drawflow.min.js"></script>
    
    <style>
        #drawflow-container {
            width: 100%;
            height: calc(100vh - 200px);
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            position: relative;
        }
        
        .drawflow .drawflow-node {
            border-radius: 8px;
            border: 2px solid #3b82f6;
            background: white;
            min-width: 200px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .drawflow .drawflow-node.selected {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        
        .node-header {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 6px 6px 0 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .node-body {
            padding: 12px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .node-palette {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            padding: 16px;
            background: white;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .node-palette-item {
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: grab;
            text-align: center;
            transition: all 0.2s;
        }
        
        .node-palette-item:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: translateY(-2px);
        }
        
        .node-palette-item .icon {
            font-size: 24px;
            margin-bottom: 4px;
        }
        
        .node-palette-item .name {
            font-size: 12px;
            font-weight: 500;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <!-- Left Sidebar: Node Palette -->
        <div class="w-64 bg-gray-50 border-r border-gray-200 overflow-y-auto p-4">
            <h3 class="text-lg font-semibold mb-4">Flow Nodes</h3>
            
            <div id="node-palette" class="space-y-2">
                <!-- Dynamically loaded from FlowNodeSchemas -->
            </div>
            
            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                <h4 class="font-semibold text-sm mb-2">Tips</h4>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• Drag nodes to canvas</li>
                    <li>• Click to configure</li>
                    <li>• Connect output → input</li>
                    <li>• Delete: select + Del key</li>
                </ul>
            </div>
        </div>
        
        <!-- Main Canvas -->
        <div class="flex-1 flex flex-col">
            <!-- Toolbar -->
            <div class="bg-white border-b border-gray-200 p-4 flex items-center justify-between">
                <div>
                    <input type="text" 
                           id="flow-name" 
                           placeholder="Flow Name" 
                           value="<?= esc($flow['name'] ?? '') ?>"
                           class="text-xl font-semibold border-none focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-2">
                </div>
                
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" 
                               id="flow-active" 
                               <?= ($flow['is_active'] ?? 1) ? 'checked' : '' ?>
                               class="rounded">
                        <span class="text-sm">Active</span>
                    </label>
                    
                    <button onclick="saveFlow()" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Save Flow
                    </button>
                    
                    <button onclick="testFlow()" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Test
                    </button>
                    
                    <a href="<?= base_url('flows') ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </div>
            
            <!-- Trigger Keywords -->
            <div class="bg-white border-b border-gray-200 p-3">
                <label class="text-sm font-medium text-gray-700">Trigger Keywords (comma-separated):</label>
                <input type="text" 
                       id="trigger-keywords" 
                       placeholder="start, begin, help"
                       value="<?= esc(implode(', ', json_decode($flow['trigger_keywords'] ?? '[]', true))) ?>"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            
            <!-- Drawflow Canvas -->
            <div id="drawflow-container"></div>
        </div>
        
        <!-- Right Sidebar: Node Config Panel -->
        <div id="config-panel" 
             class="w-80 bg-white border-l border-gray-200 overflow-y-auto p-4"
             style="display: none;">
            <h3 class="text-lg font-semibold mb-4">Node Configuration</h3>
            <div id="config-form">
                <!-- Dynamically populated based on selected node -->
            </div>
        </div>
    </div>

    <script>
    let editor;
    let nodeSchemas = {};
    let selectedNodeId = null;

    // Initialize Drawflow
    document.addEventListener('DOMContentLoaded', async function() {
        // Fetch node schemas
        const response = await fetch('/api/flows/node-types');
        nodeSchemas = await response.json();
        
        // Populate node palette
        populateNodePalette();
        
        // Initialize Drawflow editor
        const container = document.getElementById('drawflow-container');
        editor = new Drawflow(container);
        editor.start();
        
        // Load existing flow data if editing
        <?php if (!empty($drawflowData)): ?>
        editor.import(<?= json_encode($drawflowData) ?>);
        <?php else: ?>
        // Add start node by default
        addNode('start', 50, 50);
        <?php endif; ?>
        
        // Event listeners
        editor.on('nodeSelected', function(id) {
            selectedNodeId = id;
            showNodeConfig(id);
        });
        
        editor.on('nodeUnselected', function() {
            selectedNodeId = null;
            hideNodeConfig();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Delete' && selectedNodeId) {
                editor.removeNodeId('node-' + selectedNodeId);
            }
        });
    });

    function populateNodePalette() {
        const palette = document.getElementById('node-palette');
        
        for (const [type, schema] of Object.entries(nodeSchemas)) {
            if (type === 'start') continue; // Start node auto-added
            
            const item = document.createElement('div');
            item.className = 'node-palette-item';
            item.draggable = true;
            item.dataset.nodeType = type;
            
            item.innerHTML = `
                <div class="icon">${schema.icon}</div>
                <div class="name">${schema.name}</div>
            `;
            
            item.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('node-type', type);
            });
            
            item.addEventListener('click', function() {
                // Add node at center of canvas
                const rect = container.getBoundingClientRect();
                addNode(type, rect.width / 2, rect.height / 2);
            });
            
            palette.appendChild(item);
        }
    }

    // Handle drop on canvas
    document.getElementById('drawflow-container').addEventListener('drop', function(e) {
        e.preventDefault();
        const type = e.dataTransfer.getData('node-type');
        if (type) {
            const rect = e.target.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            addNode(type, x, y);
        }
    });

    document.getElementById('drawflow-container').addEventListener('dragover', function(e) {
        e.preventDefault();
    });

    function addNode(type, x, y) {
        const schema = nodeSchemas[type];
        if (!schema) return;
        
        // Build node HTML
        const html = `
            <div class="node-header" style="background-color: ${schema.color}20; border-bottom-color: ${schema.color}">
                <span>${schema.icon}</span>
                <span>${schema.name}</span>
            </div>
            <div class="node-body">
                <div id="node-preview-\${nodeId}">Click to configure</div>
            </div>
        `;
        
        // Determine inputs/outputs
        const inputs = (type === 'start') ? 0 : 1;
        const outputs = schema.has_multiple_outputs ? schema.outputs.length : (schema.has_single_output ? 1 : 0);
        
        // Add node to editor
        const nodeId = editor.addNode(
            type,
            inputs,
            outputs,
            x,
            y,
            type,
            { type: type, config: {} },
            html
        );
        
        return nodeId;
    }

    function showNodeConfig(nodeId) {
        const panel = document.getElementById('config-panel');
        const form = document.getElementById('config-form');
        
        const nodeData = editor.getNodeFromId(nodeId);
        const type = nodeData.data.type;
        const schema = nodeSchemas[type];
        
        // Build config form based on schema
        let formHTML = `<h4 class="font-semibold mb-3">${schema.name}</h4>`;
        
        for (const field of schema.config_fields) {
            formHTML += renderConfigField(field, nodeData.data.config);
        }
        
        formHTML += `
            <button onclick="saveNodeConfig(${nodeId})" 
                    class="mt-4 w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Apply
            </button>
        `;
        
        form.innerHTML = formHTML;
        panel.style.display = 'block';
    }

    function hideNodeConfig() {
        document.getElementById('config-panel').style.display = 'none';
    }

    function renderConfigField(field, currentConfig) {
        const value = currentConfig[field.name] || '';
        
        let html = `<div class="mb-4">`;
        html += `<label class="block text-sm font-medium text-gray-700 mb-1">${field.label}</label>`;
        
        switch (field.type) {
            case 'text':
            case 'url':
                html += `<input type="text" 
                               id="field-${field.name}" 
                               value="${value}" 
                               placeholder="${field.placeholder || ''}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">`;
                break;
                
            case 'textarea':
                html += `<textarea id="field-${field.name}" 
                                  rows="3" 
                                  placeholder="${field.placeholder || ''}"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">${value}</textarea>`;
                break;
                
            case 'select':
                html += `<select id="field-${field.name}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">`;
                for (const option of field.options) {
                    const selected = value === option.value ? 'selected' : '';
                    html += `<option value="${option.value}" ${selected}>${option.label}</option>`;
                }
                html += `</select>`;
                break;
                
            // Add more field types as needed
        }
        
        if (field.help) {
            html += `<p class="text-xs text-gray-500 mt-1">${field.help}</p>`;
        }
        
        html += `</div>`;
        return html;
    }

    function saveNodeConfig(nodeId) {
        const nodeData = editor.getNodeFromId(nodeId);
        const schema = nodeSchemas[nodeData.data.type];
        
        // Collect form values
        const config = {};
        for (const field of schema.config_fields) {
            const el = document.getElementById(`field-${field.name}`);
            if (el) {
                config[field.name] = el.value;
            }
        }
        
        // Update node data
        nodeData.data.config = config;
        editor.updateNodeDataFromId(nodeId, nodeData.data);
        
        // Update node preview
        updateNodePreview(nodeId, config);
        
        alert('Configuration saved!');
    }

    function updateNodePreview(nodeId, config) {
        // Update the node's visual preview with config summary
        const preview = document.getElementById(`node-preview-${nodeId}`);
        if (preview) {
            // Show first config value as preview
            const firstValue = Object.values(config)[0];
            if (firstValue) {
                preview.textContent = firstValue.substring(0, 50) + (firstValue.length > 50 ? '...' : '');
            }
        }
    }

    async function saveFlow() {
        const flowData = editor.export();
        const name = document.getElementById('flow-name').value;
        const isActive = document.getElementById('flow-active').checked;
        const keywords = document.getElementById('trigger-keywords').value
            .split(',')
            .map(k => k.trim())
            .filter(k => k.length > 0);
        
        if (!name) {
            alert('Please enter a flow name');
            return;
        }
        
        if (keywords.length === 0) {
            alert('Please enter at least one trigger keyword');
            return;
        }
        
        const payload = {
            name: name,
            is_active: isActive ? 1 : 0,
            trigger_keywords: keywords,
            flow_data: flowData
        };
        
        const url = <?= !empty($flow) ? "'/flows/{$flow['id']}'" : "'/flows'" ?>;
        const method = 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        if (response.ok) {
            alert('Flow saved!');
            window.location.href = '/flows';
        } else {
            alert('Failed to save flow');
        }
    }

    function testFlow() {
        alert('Test mode: Send a message with one of the trigger keywords to start the flow.');
    }
    </script>
</body>
</html>
```

This completes Phase 10.3 - Visual Editor.

Should I continue with **Phase 10.4 (Testing & Debugging)** to complete Phase 10?
