### Prompt 10.4 — Flow Testing & Debugging Tools

```
Build flow testing and debugging tools for Rovix AI Leads Tool.

Create app/Views/flows/index.php:

Layout: main.php, $pageTitle = 'Flows'

Header:
- "New Flow" button (primary, slate-blue)
- "Beta" badge next to title
- Toggle: Active | Inactive

Flow list (cards):
Each card:
- Flow name (bold)
- Active/Inactive toggle
- Trigger keywords (as chips)
- Execution count: "Ran X times"
- Actions: View, Edit, Test, Duplicate, Delete

Create app/Views/flows/view.php:

Layout: main.php, $pageTitle = $flow['name']

Left panel (40%):
- Flow details card:
  - Name
  - Status toggle
  - Trigger keywords
  - Execution stats
- Actions: Edit, Test, Duplicate, Delete

Right panel (60%):
- Tabs: Flow Diagram | Execution Logs | Test Console

Flow Diagram tab:
- Read-only visualization of flow (using Drawflow)
- Shows all nodes and connections
- Click node to see config

Execution Logs tab:
- Table of flow_runs:
  Columns: Contact | Started At | Status | Current Node | Duration
- Filters: Status (All, Active, Completed, Handed Off, Timed Out, Failed)
- Click row → shows detailed execution log

Test Console tab:
- Simulated chat interface
- Test flow without sending real WhatsApp messages
- Shows: User message → Bot response → Current node

Create app/Controllers/FlowsController.php (add method):

10. test($flowId) GET: Show test console
    - Load flow
    - Create mock flow_run in session (not DB)
    - Return test console view

11. testMessage($flowId) POST: Process test message
    - Get mock flow_run from session
    - Run FlowEngine::processResponse in test mode
    - Return JSON: { response, current_node, vars, is_complete }

Create app/Views/flows/test_console.php:

<div class="flex flex-col h-full">
    <!-- Chat messages -->
    <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
        <div class="text-center text-sm text-gray-500 py-4">
            Flow test started. Send a message to begin.
        </div>
    </div>
    
    <!-- Current state indicator -->
    <div class="bg-blue-50 border-t border-blue-200 p-3">
        <div class="text-xs text-gray-600">Current Node: <span id="current-node" class="font-semibold">start</span></div>
        <div class="text-xs text-gray-600 mt-1">Variables: <span id="flow-vars" class="font-mono">{}</span></div>
    </div>
    
    <!-- Input -->
    <div class="border-t border-gray-200 p-4 bg-white">
        <div class="flex gap-2">
            <input type="text" 
                   id="test-input" 
                   placeholder="Type your message..."
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg"
                   onkeypress="if(event.key==='Enter') sendTestMessage()">
            <button onclick="sendTestMessage()" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Send
            </button>
            <button onclick="resetTest()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Reset
            </button>
        </div>
    </div>
</div>

<script>
async function sendTestMessage() {
    const input = document.getElementById('test-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message to chat
    addMessage('user', message);
    input.value = '';
    
    // Send to test endpoint
    const response = await fetch('/flows/<?= $flow['id'] ?>/test-message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: message })
    });
    
    const data = await response.json();
    
    // Add bot response
    if (data.response) {
        addMessage('bot', data.response);
    }
    
    // Update state
    document.getElementById('current-node').textContent = data.current_node;
    document.getElementById('flow-vars').textContent = JSON.stringify(data.vars);
    
    // Check if flow completed
    if (data.is_complete) {
        addMessage('system', 'Flow completed');
    }
}

function addMessage(type, text) {
    const container = document.getElementById('chat-messages');
    const div = document.createElement('div');
    
    if (type === 'user') {
        div.className = 'flex justify-end';
        div.innerHTML = `<div class="bg-blue-600 text-white px-4 py-2 rounded-lg max-w-md">${text}</div>`;
    } else if (type === 'bot') {
        div.className = 'flex justify-start';
        div.innerHTML = `<div class="bg-white border border-gray-300 px-4 py-2 rounded-lg max-w-md">${text}</div>`;
    } else {
        div.className = 'text-center';
        div.innerHTML = `<div class="text-xs text-gray-500 italic">${text}</div>`;
    }
    
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function resetTest() {
    document.getElementById('chat-messages').innerHTML = `
        <div class="text-center text-sm text-gray-500 py-4">
            Flow test reset. Send a message to begin.
        </div>
    `;
    document.getElementById('current-node').textContent = 'start';
    document.getElementById('flow-vars').textContent = '{}';
    
    // Call backend to reset session
    fetch('/flows/<?= $flow['id'] ?>/test-reset', { method: 'POST' });
}
</script>

Add routes:
GET /flows → FlowsController::index
GET /flows/create → FlowsController::create
POST /flows → FlowsController::store
GET /flows/{id} → FlowsController::view
GET /flows/{id}/edit → FlowsController::edit
POST /flows/{id} → FlowsController::update
POST /flows/{id}/toggle → FlowsController::toggle
POST /flows/{id}/duplicate → FlowsController::duplicate
POST /flows/{id}/delete → FlowsController::delete
GET /flows/{id}/test → FlowsController::test
POST /flows/{id}/test-message → FlowsController::testMessage
POST /flows/{id}/test-reset → FlowsController::testReset
```

### Testing Phase 10 (Complete Flow System)

Manual test checklist:

```bash
# 1. Navigate to flows
http://localhost:8080/flows

# Test: Empty state or existing flows show, "Beta" badge visible

# 2. Create new flow
- Click "New Flow"
- Name: "Product Info Bot"
- Trigger keywords: info, product, catalog

# 3. Build flow visually
- Drag "Send Message" node to canvas
- Connect Start → Send Message
- Configure: "Welcome! What product are you interested in?"
- Add "Send Buttons" node
- Configure buttons: "Electronics", "Clothing", "Home"
- Connect to appropriate next nodes
- Add "End" node

# Test: Nodes drag smoothly, connections work, config panel shows

# 4. Save flow
- Click "Save Flow"

# Test: Flow saved, redirects to flow list

# 5. Test flow in console
- Click "Test" on flow card
- Type: "info"
- See welcome message + buttons
- Click "Electronics"
- See response

# Test: Flow executes correctly in test mode, no real messages sent

# 6. Test live flow
- Send real WhatsApp message: "info"
- Check webhook processes it
- Check flow_run created
- Check messages sent

# Test:
php spark queue:process
- Flow triggers from keyword
- flow_run created with status='active'
- Messages sent via WhatsApp

# 7. Test collect input node
- Create flow with "Collect Input" node
- Variable: user_email
- Validation: email
- Trigger keyword: subscribe

# Test:
- Send "subscribe"
- Bot asks for email
- Send invalid: "notanemail"
- Bot shows error
- Send valid: "test@example.com"
- Bot continues to next node
- Check flow_run.vars contains email

# 8. Test conditional branching
- Create flow with "Condition" node
- Condition: variable_equals, user_type = "vip"
- True path: "VIP discount available"
- False path: "Standard pricing"

# Test:
- Set user_type variable in flow
- Flow branches correctly based on condition

# 9. Test handoff node
- Add "Handoff to Agent" node at end
- Configure: assign to specific agent

# Test:
- Flow reaches handoff
- Conversation assigned to agent
- flow_run status = 'handed_off'
- Flow ends

# 10. View flow execution logs
- Open flow detail
- Check "Execution Logs" tab

# Test:
- Shows all flow_runs
- Contact names, timestamps, statuses
- Can filter by status
- Click row shows detailed events

# 11. Test stale flow cleanup
- Manually set flow_run.updated_at to 2 days ago
php spark flows:cleanup-stale

# Test: Stale run marked as 'timed_out'

# 12. Test multiple contacts in same flow
- Trigger flow from 2 different contacts simultaneously

# Test:
- 2 separate flow_runs created
- Each maintains own vars and state
- No interference between runs

# 13. Test flow with buttons
- Create flow with "Send Buttons"
- Buttons: "Yes", "No", "Maybe"
- Each button goes to different node

# Test:
- Interactive buttons sent via WhatsApp
- Clicking button triggers correct path
- Button selection saved to variable if configured

# 14. Test flow with list
- Create flow with "Send List"
- Sections: "Popular Items", "New Arrivals"
- Each with 3 items

# Test:
- List message sent
- Selecting item continues flow
- Selection saved to variable

# 15. Test set tag action
- Add "Set Tag" node
- Action: Add tag "Chatbot User"

# Test:
- Flow executes set_tag node
- Tag added to contact
- Flow continues to next node

# 16. Test flow duplication
- Click "Duplicate" on existing flow

# Test: New flow created with "(Copy)" suffix, same structure

# 17. Test flow toggle
- Toggle flow to inactive

# Test:
- Keyword no longer triggers flow
- Existing active runs continue

# 18. Edit existing flow
- Click "Edit"
- Add new node
- Remove old node
- Save

# Test: Changes applied, flow uses new structure

# 19. Test error handling
- Create invalid flow (e.g., missing next_node connection)
- Try to save

# Test: Validation error shown

# 20. Tenant isolation
- Login as different account
- View flows

# Test: Only see own flows, not other accounts'
```

**Pass Criteria:**
- ✅ Flow CRUD works (create, edit, view, delete)
- ✅ Visual editor loads with Drawflow.js
- ✅ Drag & drop nodes works
- ✅ Node configuration panel works
- ✅ Connections between nodes work
- ✅ Save flow persists to database correctly
- ✅ Keyword trigger detection works
- ✅ Flow execution follows node connections
- ✅ All 9 node types execute correctly
- ✅ Collect input with validation works
- ✅ Conditional branching works
- ✅ Button/list responses handled correctly
- ✅ Variables stored and replaced correctly
- ✅ Handoff to agent works
- ✅ Test console simulates flow without real messages
- ✅ Multiple concurrent flow_runs don't interfere
- ✅ Stale flow cleanup works
- ✅ Execution logs display correctly
- ✅ Toggle active/inactive works
- ✅ Duplicate flow works
- ✅ Tenant isolation (accounts only see own flows)

**Common Issues:**
- Drawflow not loading: Check CDN URLs, check browser console for errors
- Nodes not connecting: Check input/output counts match node type
- Config not saving: Check node data structure, check editor.updateNodeDataFromId()
- Flow not triggering: Check trigger_keywords, check is_active=1, check queue processing
- Variables not replacing: Check {{variable}} syntax, check vars JSON in flow_run
- Buttons not working: Check handleButtonResponse(), check message text matching
- Stale flows not cleaning: Check cron running, check stale date calculation
- Test console not working: Check session storage, check mock flow_run creation
- Flow hangs: Check each node has next_node configured, check for circular references
- Multiple runs interfering: Check flow_runs filtered by contact_id correctly

---
