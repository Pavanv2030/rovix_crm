## PHASE 6: Pipelines & Deals (Week 5-6)

### Prompt 6.1 — Pipeline & Stage CRUD

```
Build the pipeline and stage management for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/pipelines/page.tsx
- src/components/pipelines/pipeline-list.tsx
- src/lib/api/pipelines.ts

Create app/Controllers/PipelinesController.php:

1. index() GET: List all pipelines
   - Load pipelines with stages (join pipeline_stages)
   - Count deals per pipeline
   - Sum deal values per pipeline
   - Pass to view: $pipelines

2. create() GET: Show create pipeline form
   - Pass empty form
   - Default stages: "New Lead", "Qualified", "Proposal", "Negotiation", "Closed Won"

3. store() POST: Create new pipeline
   - Validate: name required
   - Insert pipeline
   - Insert default stages (or custom stages from form)
   - Each stage: name, position (0, 1, 2...), color
   - Redirect to pipeline board with success message

4. edit($pipelineId) GET: Edit pipeline
   - Load pipeline with stages
   - Pass to view: $pipeline, $stages

5. update($pipelineId) POST: Update pipeline
   - Update pipeline name
   - Update stages (name, color)
   - Handle reordering (position field)
   - Redirect back with success

6. delete($pipelineId) POST: Delete pipeline
   - Verify has_min_role('admin')
   - Check if has deals: if yes, require confirmation or block
   - CASCADE delete stages (FK)
   - SET NULL on deals.pipeline_id
   - Redirect to list

Create app/Controllers/Api/PipelineStagesController.php:

1. store() POST: Add new stage to pipeline
   - Validate: pipeline_id, name required
   - Get max position from existing stages
   - Insert new stage with position = max + 1
   - Return JSON: created stage

2. update($stageId) POST: Update stage
   - Update name and/or color
   - Return JSON: updated stage

3. reorder() POST: Reorder stages
   - Input: array of stage_ids in new order
   - Update position field for each stage
   - Return JSON: success

4. delete($stageId) DELETE: Delete stage
   - Check if stage has deals: if yes, block or move to another stage
   - Delete stage
   - Reorder remaining stages
   - Return JSON: success

Create app/Views/pipelines/index.php:

Layout: main.php, $pageTitle = 'Pipelines'

Header:
- "New Pipeline" button (primary, slate-blue)
- Search/filter (optional for MVP)

Pipeline list:
- Card for each pipeline
- Shows: pipeline name, stage count, total deals, total value
- Actions: View board, Edit, Delete
- Click pipeline card → opens board view (Prompt 6.2)

Create app/Views/pipelines/create.php:

Form with:
- Pipeline name input
- Stages builder (Alpine.js):
  - List of stages (editable)
  - Each stage: name input, color picker (12 preset colors), delete button
  - "Add Stage" button
  - Drag handles for reordering (optional for MVP)
- Submit button

Alpine.js for dynamic stage management:
x-data="{
  stages: [
    { name: 'New Lead', color: '#3B82F6' },
    { name: 'Qualified', color: '#10B981' },
    { name: 'Proposal', color: '#F59E0B' },
    { name: 'Negotiation', color: '#EF4444' },
    { name: 'Closed Won', color: '#8B5CF6' }
  ],
  addStage() {
    this.stages.push({ name: '', color: '#3B82F6' });
  },
  removeStage(index) {
    this.stages.splice(index, 1);
  }
}"

Create app/Views/pipelines/edit.php:

Similar to create, but pre-filled with existing pipeline data.
Allow editing stage names/colors, adding new stages, deleting stages (with confirmation if stage has deals).

Add routes:
GET /pipelines → PipelinesController::index
GET /pipelines/create → PipelinesController::create
POST /pipelines → PipelinesController::store
GET /pipelines/{id}/edit → PipelinesController::edit
POST /pipelines/{id} → PipelinesController::update
POST /pipelines/{id}/delete → PipelinesController::delete

GET /pipelines/{id}/board → PipelinesController::board (next prompt)

API routes:
POST /api/pipelines/{id}/stages → PipelineStagesController::store
POST /api/stages/{id} → PipelineStagesController::update
POST /api/stages/reorder → PipelineStagesController::reorder
DELETE /api/stages/{id} → PipelineStagesController::delete
```

### Prompt 6.2 — Kanban Board with Drag & Drop

```
Build the Kanban board view for deals in Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/pipelines/[id]/page.tsx
- src/components/pipelines/kanban-board.tsx
- src/components/pipelines/deal-card.tsx

Create app/Controllers/PipelinesController.php (add method):

7. board($pipelineId) GET: Show Kanban board
   - Load pipeline with stages
   - Load all deals for this pipeline, grouped by stage_id
   - For each deal: include contact info, assigned agent
   - Pass to view: $pipeline, $stages, $dealsByStage

Create app/Views/pipelines/board.php:

Layout: main.php, $pageTitle = $pipeline['name']

Header:
- Pipeline name (breadcrumb: Pipelines > {name})
- Filter dropdown: All deals, My deals, Unassigned
- "New Deal" button
- "Edit Pipeline" button (admin only)

Kanban Board:
- Horizontal columns (one per stage)
- Each column:
  - Stage name + color indicator (left border)
  - Deal count + total value
  - Scrollable deal cards
  - "Add Deal" button at bottom

Deal Card (for each deal):
- Title (bold)
- Contact name + phone (small text)
- Deal value (₹{value}, large)
- Expected close date (if set, with color: red if overdue, yellow if this week)
- Assigned agent avatar (small)
- Click → opens deal detail modal

Drag & Drop:
Use SortableJS library (lightweight, no dependencies):
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

Initialize Sortable for each column:
<script>
document.addEventListener('DOMContentLoaded', function() {
  const columns = document.querySelectorAll('.kanban-column');
  
  columns.forEach(column => {
    new Sortable(column, {
      group: 'deals',
      animation: 150,
      ghostClass: 'opacity-50',
      onEnd: function(evt) {
        const dealId = evt.item.dataset.dealId;
        const newStageId = evt.to.dataset.stageId;
        const newPosition = evt.newIndex;
        
        // Update via AJAX
        fetch('/api/deals/' + dealId + '/move', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            stage_id: newStageId,
            position: newPosition 
          })
        });
      }
    });
  });
});
</script>

Column HTML structure:
<div class="kanban-column" data-stage-id="<?= $stage['id'] ?>" style="border-left: 4px solid <?= $stage['color'] ?>">
  <div class="p-4 bg-gray-50 border-b">
    <h3 class="font-semibold"><?= esc($stage['name']) ?></h3>
    <p class="text-sm text-gray-600"><?= count($deals) ?> deals · ₹<?= number_format($totalValue) ?></p>
  </div>
  
  <div class="p-4 space-y-3 overflow-y-auto" style="max-height: calc(100vh - 300px)">
    <?php foreach ($deals as $deal): ?>
      <div class="deal-card bg-white rounded-lg shadow p-4 cursor-move" 
           data-deal-id="<?= $deal['id'] ?>">
        <h4 class="font-semibold text-gray-900"><?= esc($deal['title']) ?></h4>
        <p class="text-sm text-gray-600"><?= esc($deal['contact_name']) ?></p>
        <p class="text-lg font-bold text-green-600 mt-2">₹<?= number_format($deal['value']) ?></p>
        
        <?php if ($deal['expected_close_date']): ?>
          <p class="text-xs text-gray-500 mt-1">Close: <?= date('M d', strtotime($deal['expected_close_date'])) ?></p>
        <?php endif; ?>
        
        <?php if ($deal['assigned_agent_name']): ?>
          <div class="mt-2 flex items-center">
            <div class="w-6 h-6 rounded-full bg-blue-900 text-white text-xs flex items-center justify-center">
              <?= strtoupper(substr($deal['assigned_agent_name'], 0, 1)) ?>
            </div>
            <span class="text-xs text-gray-600 ml-2"><?= esc($deal['assigned_agent_name']) ?></span>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  
  <div class="p-4 border-t">
    <button class="text-sm text-blue-600 hover:text-blue-700" 
            onclick="openNewDealModal('<?= $stage['id'] ?>')">
      + Add Deal
    </button>
  </div>
</div>

Responsive: On mobile, switch to vertical accordion (one stage at a time)
```

### Prompt 6.3 — Deal Management (CRUD + Modal)

```
Build deal creation, editing, and detail view for Rovix AI Leads Tool.

Reference original wacrm files:
- src/components/pipelines/deal-modal.tsx
- src/lib/api/deals.ts

Create app/Controllers/DealsController.php:

1. create() GET: Show create deal modal/page
   - Pass: $pipelines, $contacts, $agents (for dropdowns)

2. store() POST: Create new deal
   - Validate: title required, pipeline_id required, stage_id required
   - Contact is optional (can create deal without contact)
   - Insert deal with status='open'
   - If conversation_id provided: link deal to conversation
   - Redirect back to board with success message

3. view($dealId) GET: Show deal detail
   - Load deal with contact, conversation, assigned agent
   - Load deal history (stage changes, value changes, notes)
   - Pass to view: $deal, $history

4. edit($dealId) GET: Show edit form
   - Pass: $deal, $pipelines, $stages, $contacts, $agents

5. update($dealId) POST: Update deal
   - Validate inputs
   - Update deal
   - If stage_id changed: log stage change in history
   - If value changed: log value change
   - Redirect back with success

6. updateStatus($dealId) POST: Mark as won/lost
   - Update status to 'won' or 'lost'
   - Update deals.updated_at
   - Log status change
   - Redirect with success message

7. delete($dealId) POST: Delete deal
   - Verify has_min_role('admin')
   - Delete deal
   - Redirect to board

Create app/Controllers/Api/DealsController.php:

1. move() POST: Move deal to different stage (drag & drop handler)
   - Input: deal_id, stage_id, position
   - Update deals.stage_id
   - Log stage change in database (for history)
   - Return JSON: success

2. assign() POST: Assign deal to agent
   - Input: deal_id, agent_id
   - Update deals.assigned_agent_id
   - Return JSON: success

3. updateValue() POST: Update deal value
   - Input: deal_id, value
   - Update deals.value
   - Return JSON: success

Create app/Views/deals/modal.php (or use Alpine.js modal):

Modal overlay with form:
- Title input (required)
- Pipeline dropdown → loads stages for selected pipeline
- Stage dropdown (required)
- Contact dropdown (searchable, optional)
- Value input (number, currency symbol ₹)
- Currency dropdown (default INR)
- Expected close date (date picker)
- Assigned agent dropdown (optional)
- Notes textarea (optional)
- Submit button

Alpine.js for dynamic stage loading:
x-data="{
  selectedPipeline: '',
  stages: [],
  async loadStages() {
    const response = await fetch('/api/pipelines/' + this.selectedPipeline + '/stages');
    this.stages = await response.json();
  }
}"

Create app/Views/deals/view.php:

Layout: main.php, $pageTitle = $deal['title']

Left panel (30%):
- Deal summary card:
  - Title (editable inline)
  - Value (editable inline)
  - Status badge (open/won/lost)
  - Pipeline → Stage
  - Expected close date
  - Contact (linked)
  - Conversation (linked, if exists)
  - Assigned agent
  - Created date
- Actions:
  - Mark as Won (green button)
  - Mark as Lost (red button)
  - Edit Deal
  - Delete Deal (admin only)

Right panel (70%):
- Tabs: Activity | Notes
- Activity tab: Timeline of all changes
  - Deal created
  - Moved from Stage A → Stage B
  - Value changed from ₹X → ₹Y
  - Assigned to Agent
  - Marked as won/lost
- Notes tab: Free-form notes (like contact notes)

Add routes:
GET /deals/create → DealsController::create
POST /deals → DealsController::store
GET /deals/{id} → DealsController::view
GET /deals/{id}/edit → DealsController::edit
POST /deals/{id} → DealsController::update
POST /deals/{id}/status → DealsController::updateStatus
POST /deals/{id}/delete → DealsController::delete

API routes:
POST /api/deals/{id}/move → DealsController::move
POST /api/deals/{id}/assign → DealsController::assign
POST /api/deals/{id}/value → DealsController::updateValue
```

### Testing Phase 6

Manual test checklist:

```bash
# 1. Navigate to pipelines
http://localhost:8080/pipelines

# Test: Pipelines list shows

# 2. Create pipeline
- Click "New Pipeline"
- Name: "Sales Pipeline"
- Default stages load
- Edit stage names/colors
- Add custom stage
- Save

# Test: Pipeline created, redirected to board

# 3. View Kanban board
- Click pipeline card

# Test: Board shows with stages as columns, empty initially

# 4. Create deal
- Click "Add Deal" in first stage
- Title: "Test Deal - Acme Corp"
- Value: 50000
- Select contact (optional)
- Expected close: next week
- Assign to yourself
- Save

# Test: Deal card appears in stage column

# 5. Drag deal between stages
- Drag deal card from "New Lead" to "Qualified"

# Test: Card moves, AJAX call fires, deal.stage_id updates in DB

# 6. Edit deal
- Click deal card
- Change value to 75000
- Change expected close date
- Save

# Test: Deal updates, history logs value change

# 7. Mark deal as won
- Open deal detail
- Click "Mark as Won"

# Test: Deal status changes to 'won', moves to won column (if configured)

# 8. Filter board
- Select "My Deals" filter

# Test: Only deals assigned to current user show

# 9. Create second pipeline
- Create "Support Pipeline" with different stages

# Test: Multiple pipelines coexist, each with own stages

# 10. Edit pipeline
- Click "Edit Pipeline"
- Rename stage
- Change stage color
- Add new stage
- Save

# Test: Changes apply, board reflects new stage structure

# 11. Delete empty stage
- Delete a stage with no deals

# Test: Stage deleted, remaining stages reordered

# 12. Try to delete stage with deals
- Delete a stage that has deals

# Test: Error or confirmation prompt "Move X deals to another stage first"

# 13. Tenant isolation
- Login as different account
- View pipelines

# Test: Only see own account's pipelines, not other accounts'

# 14. Mobile responsive
- Open board on mobile (resize browser)

# Test: Switches to vertical view or horizontal scroll
```

**Pass Criteria:**
- ✅ Pipelines CRUD works (create, edit, delete)
- ✅ Stages CRUD works (add, edit, reorder, delete)
- ✅ Kanban board displays deals correctly by stage
- ✅ Drag & drop moves deals between stages
- ✅ Deal CRUD works (create, edit, delete)
- ✅ Deal value and date can be edited inline
- ✅ Deal history logs all changes
- ✅ Mark as won/lost works
- ✅ Filters work (All, My deals, Unassigned)
- ✅ Tenant isolation (accounts only see own pipelines)
- ✅ Stage color displays correctly
- ✅ Deal counts and totals accurate per stage
- ✅ Contact and conversation linkage works

**Common Issues:**
- Drag & drop not working: Check SortableJS loaded, check onEnd callback fires
- Deal not moving: Check /api/deals/{id}/move route exists, check AJAX call succeeds
- Stages out of order: Check position field updates correctly on reorder
- Can't delete pipeline: Check for CASCADE delete on stages FK
- Deal values not summing: Check deals.value is DECIMAL, not VARCHAR
- History not logging: Check stage change detection in update method
- Tenant leak: Check BaseModel scoping on pipelines, stages, deals tables

---
