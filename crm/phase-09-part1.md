## PHASE 9: Automation Engine (Week 7-8)

### Prompt 9.1 — Automation Builder UI

```
Build the automation builder interface for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/automations/page.tsx
- src/components/automations/automation-builder.tsx
- src/lib/api/automations.ts

IMPORTANT: Automations execute based on triggers (7 types) and perform actions via steps.

Create app/Controllers/AutomationsController.php:

1. index() GET: List all automations
   - Load automations with execution stats
   - Show: name, trigger type, is_active, execution_count, last_executed_at
   - Group by status: Active, Inactive
   - Pass to view: $automations

2. create() GET: Show automation builder
   - Load tags for trigger/action options
   - Load custom fields for actions
   - Load agents for assignment actions
   - Pass to view: $tags, $customFields, $agents

3. store() POST: Create new automation
   - Validate:
     - name required
     - trigger_type required
     - trigger_config (JSON based on trigger type)
     - steps array required (at least 1 step)
   - Insert automation with is_active=1
   - Insert automation_steps (each with position)
   - Redirect to automation detail with success

4. view($automationId) GET: Show automation detail
   - Load automation with steps (ordered by position)
   - Load execution logs (recent 50)
   - Show visual flow diagram
   - Pass to view: $automation, $steps, $logs

5. edit($automationId) GET: Edit automation
   - Load automation with steps
   - Pass to view: $automation, $steps, $tags, $customFields, $agents

6. update($automationId) POST: Update automation
   - Update automation record
   - Delete old steps
   - Insert new steps
   - Redirect with success

7. toggle($automationId) POST: Toggle active/inactive
   - Toggle is_active field
   - Return JSON or redirect with success

8. delete($automationId) POST: Delete automation
   - Verify has_min_role('admin')
   - Delete automation (CASCADE steps and logs)
   - Redirect to list

Create app/Views/automations/index.php:

Layout: main.php, $pageTitle = 'Automations'

Header:
- "New Automation" button (primary, slate-blue)
- Toggle: Active (badge) | Inactive

Automation list (cards):
Each card:
- Automation name (bold)
- Active/Inactive toggle switch (Alpine.js)
- Trigger icon + type (e.g., "⚡ New Message Received")
- Step count (e.g., "3 steps")
- Execution stats: "Ran 127 times | Last: 2 hours ago"
- Actions: View, Edit, Delete

Empty state: "No automations yet. Create your first automation to respond automatically to customer messages."

Create app/Views/automations/create.php:

Layout: main.php, $pageTitle = 'Create Automation'

Automation Builder (step-by-step wizard or single page):

Section 1: Basic Info
- Name input
- Description textarea (optional)

Section 2: Trigger
- Trigger type dropdown:
  1. New Message Received (any inbound message)
  2. First Inbound Message (new contact's first message)
  3. Keyword Match (message contains specific word/phrase)
  4. New Contact Created (contact added to system)
  5. Conversation Assigned (conversation assigned to agent)
  6. Tag Added (specific tag added to contact)
  7. Time-Based (X hours after last message, daily at time, etc.)

- Trigger config (conditional fields based on type):
  - Keyword Match: keywords input (comma-separated)
  - Tag Added: tag dropdown
  - Time-Based: delay input + unit (hours, days)

Section 3: Actions (Steps)
- Visual step builder (vertical flow)
- "Add Step" button opens dropdown with action types:
  1. Send Message (text input)
  2. Send Template (template dropdown + variable inputs)
  3. Add Tag (tag dropdown)
  4. Remove Tag (tag dropdown)
  5. Assign Conversation (agent dropdown)
  6. Update Contact Field (field + value)
  7. Create Deal (pipeline, stage, value inputs)
  8. Wait (delay input + unit)
  9. Condition (if/then branching)
  10. Send Webhook (URL + payload)
  11. Close Conversation

Each step card shows:
- Step type icon + name
- Configuration preview
- Edit button
- Delete button
- Drag handle (for reordering)

For Condition steps:
- Show branching: "If X, then steps A, B, C | Otherwise, steps D, E"
- Condition types:
  - Message contains keyword
  - Contact has tag
  - Custom field equals value
  - Contact name is set
  - Time of day (business hours check)

Alpine.js for step management:
x-data="{
  steps: [],
  addStep(type) {
    this.steps.push({
      step_type: type,
      step_config: {},
      position: this.steps.length
    });
  },
  removeStep(index) {
    this.steps.splice(index, 1);
    this.reorderSteps();
  },
  reorderSteps() {
    this.steps.forEach((step, index) => {
      step.position = index;
    });
  }
}"

Create app/Views/automations/view.php:

Layout: main.php, $pageTitle = $automation['name']

Left panel (40%):
- Automation details card:
  - Name
  - Status (Active/Inactive toggle)
  - Trigger badge + description
  - Created by
  - Created date
  - Execution stats

Actions:
- Edit
- Duplicate
- Delete

Right panel (60%):
- Visual flow diagram (vertical):
  - Trigger box at top
  - Arrow down
  - Each step as box with icon
  - Branching arrows for conditions
  - End node at bottom

- Execution logs table:
  Columns: Contact | Triggered At | Status | Steps Executed | Error

  Filters: Status (All, Completed, Failed, Skipped)
  Pagination: 50 per page

Add routes:
GET /automations → AutomationsController::index
GET /automations/create → AutomationsController::create
POST /automations → AutomationsController::store
GET /automations/{id} → AutomationsController::view
GET /automations/{id}/edit → AutomationsController::edit
POST /automations/{id} → AutomationsController::update
POST /automations/{id}/toggle → AutomationsController::toggle
POST /automations/{id}/delete → AutomationsController::delete
```

### Prompt 9.2 — Trigger System & Execution Engine

```
Build the automation trigger detection and execution engine for Rovix AI Leads Tool.

Create app/Libraries/AutomationEngine.php:

<?php
namespace App\Libraries;

use App\Models\AutomationModel;
use App\Models\AutomationStepModel;
use App\Models\AutomationLogModel;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\BaseModel;

class AutomationEngine
{
    /**
     * Process trigger and execute matching automations
     * Called by job queue (dispatched from webhook)
     */
    public function processForTrigger(array $payload): void
    {
        BaseModel::setBypassAccountScope(true);

        $accountId = $payload['account_id'];
        $contactId = $payload['contact_id'];
        $triggerType = $payload['trigger_type'];
        $triggerData = $payload; // Includes message, conversation_id, etc.

        // Find active automations for this trigger type
        $automationModel = new AutomationModel();
        $automations = $automationModel
            ->where('account_id', $accountId)
            ->where('trigger_type', $triggerType)
            ->where('is_active', 1)
            ->findAll();

        foreach ($automations as $automation) {
            // Check if trigger matches
            if (!$this->matchesTrigger($automation, $triggerData)) {
                continue;
            }

            // Execute automation
            $this->execute($automation, $contactId, $triggerData);
        }
    }

    private function matchesTrigger(array $automation, array $triggerData): bool
    {
        $config = json_decode($automation['trigger_config'], true);

        switch ($automation['trigger_type']) {
            case 'keyword_match':
                $messageText = $triggerData['message']['text']['body'] ?? '';
                $keywords = $config['keywords'] ?? [];
                
                foreach ($keywords as $keyword) {
                    if (stripos($messageText, trim($keyword)) !== false) {
                        return true;
                    }
                }
                return false;

            case 'tag_added':
                // Check if the tag that triggered this matches config
                $tagId = $triggerData['tag_id'] ?? null;
                return $tagId === $config['tag_id'];

            case 'new_message_received':
            case 'first_inbound_message':
            case 'new_contact_created':
            case 'conversation_assigned':
                // No additional matching needed
                return true;

            case 'time_based':
                // Time-based automations are triggered by cron, not events
                return false;

            default:
                return false;
        }
    }

    private function execute(array $automation, string $contactId, array $triggerData): void
    {
        // Create execution log
        $logModel = new AutomationLogModel();
        $logId = $logModel->insert([
            'automation_id' => $automation['id'],
            'contact_id' => $contactId,
            'trigger_event' => json_encode($triggerData),
            'status' => 'running'
        ]);

        try {
            // Load steps
            $stepModel = new AutomationStepModel();
            $steps = $stepModel
                ->where('automation_id', $automation['id'])
                ->where('parent_step_id', null) // Root steps only
                ->orderBy('position', 'ASC')
                ->findAll();

            $executedSteps = [];

            foreach ($steps as $step) {
                $result = $this->executeStep($step, $contactId, $triggerData, $automation);
                $executedSteps[] = [
                    'step_id' => $step['id'],
                    'step_type' => $step['step_type'],
                    'result' => $result
                ];

                // If step is a condition, execute branch steps
                if ($step['step_type'] === 'condition') {
                    $branchSteps = $this->executeBranch($step, $contactId, $triggerData, $automation, $result);
                    $executedSteps = array_merge($executedSteps, $branchSteps);
                }

                // If step is a wait, schedule next steps for later
                if ($step['step_type'] === 'wait') {
                    $this->scheduleWaitedSteps($automation, $contactId, $step, $triggerData);
                    break; // Stop execution, will resume later
                }
            }

            // Update log
            $logModel->update($logId, [
                'status' => 'completed',
                'steps_executed' => json_encode($executedSteps)
            ]);

            // Update automation stats
            $automationModel = new AutomationModel();
            $automationModel->update($automation['id'], [
                'execution_count' => $automation['execution_count'] + 1,
                'last_executed_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            // Log error
            $logModel->update($logId, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            log_message('error', "Automation execution failed: " . $e->getMessage());
        }
    }

    private function executeStep(array $step, string $contactId, array $triggerData, array $automation): mixed
    {
        $config = json_decode($step['step_config'], true);

        switch ($step['step_type']) {
            case 'send_message':
                return $this->sendMessage($contactId, $config['message_text'], $triggerData);

            case 'send_template':
                return $this->sendTemplate($contactId, $config, $triggerData, $automation);

            case 'add_tag':
                return $this->addTag($contactId, $config['tag_id']);

            case 'remove_tag':
                return $this->removeTag($contactId, $config['tag_id']);

            case 'assign_conversation':
                return $this->assignConversation($triggerData['conversation_id'], $config['agent_id']);

            case 'update_contact_field':
                return $this->updateContactField($contactId, $config['field'], $config['value']);

            case 'create_deal':
                return $this->createDeal($contactId, $config, $automation['account_id']);

            case 'close_conversation':
                return $this->closeConversation($triggerData['conversation_id']);

            case 'send_webhook':
                return $this->sendWebhook($config['url'], $config['payload'], $contactId, $triggerData);

            case 'condition':
                return $this->evaluateCondition($config, $contactId, $triggerData);

            case 'wait':
                // Wait is handled by scheduling, return delay info
                return ['delay' => $config['delay'], 'unit' => $config['unit']];

            default:
                throw new \Exception('Unknown step type: ' . $step['step_type']);
        }
    }

    // Implementation stubs for each action type
    // These will be fully implemented

    private function sendMessage(string $contactId, string $text, array $triggerData): array
    {
        // Get conversation
        $conversationId = $triggerData['conversation_id'] ?? null;
        if (!$conversationId) {
            throw new \Exception('No conversation to send message to');
        }

        // Dispatch send job
        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('send_message', [
            'conversation_id' => $conversationId,
            'content_type' => 'text',
            'content_text' => $text
        ], null, 5);

        return ['status' => 'queued'];
    }

    private function sendTemplate(string $contactId, array $config, array $triggerData, array $automation): array
    {
        // Similar to sendMessage but uses template
        $templateName = $config['template_name'];
        $variables = $config['variables'] ?? [];

        // TODO: Implement template sending via MetaApi
        return ['status' => 'queued', 'template' => $templateName];
    }

    private function addTag(string $contactId, string $tagId): array
    {
        $contactTagModel = new \App\Models\ContactTagModel();
        
        // Check if already tagged
        $existing = $contactTagModel
            ->where('contact_id', $contactId)
            ->where('tag_id', $tagId)
            ->first();

        if (!$existing) {
            $contactTagModel->insert([
                'contact_id' => $contactId,
                'tag_id' => $tagId
            ]);
        }

        return ['status' => 'success'];
    }

    private function removeTag(string $contactId, string $tagId): array
    {
        $contactTagModel = new \App\Models\ContactTagModel();
        $contactTagModel
            ->where('contact_id', $contactId)
            ->where('tag_id', $tagId)
            ->delete();

        return ['status' => 'success'];
    }

    private function assignConversation(string $conversationId, string $agentId): array
    {
        $conversationModel = new ConversationModel();
        $conversationModel->update($conversationId, [
            'assigned_agent_id' => $agentId
        ]);

        return ['status' => 'success', 'agent_id' => $agentId];
    }

    private function updateContactField(string $contactId, string $field, string $value): array
    {
        $contactModel = new ContactModel();
        
        // Only allow updating safe fields
        $allowedFields = ['name', 'email', 'company'];
        
        if (in_array($field, $allowedFields)) {
            $contactModel->update($contactId, [$field => $value]);
            return ['status' => 'success'];
        }

        throw new \Exception('Field not allowed: ' . $field);
    }

    private function createDeal(string $contactId, array $config, string $accountId): array
    {
        $dealModel = new \App\Models\DealModel();
        
        $dealId = $dealModel->insert([
            'account_id' => $accountId,
            'contact_id' => $contactId,
            'pipeline_id' => $config['pipeline_id'],
            'stage_id' => $config['stage_id'],
            'title' => $config['title'] ?? 'Auto-created deal',
            'value' => $config['value'] ?? 0,
            'status' => 'open'
        ]);

        return ['status' => 'success', 'deal_id' => $dealId];
    }

    private function closeConversation(string $conversationId): array
    {
        $conversationModel = new ConversationModel();
        $conversationModel->update($conversationId, [
            'status' => 'closed'
        ]);

        return ['status' => 'success'];
    }

    private function sendWebhook(string $url, array $payload, string $contactId, array $triggerData): array
    {
        // Send HTTP POST to webhook URL
        $client = \Config\Services::curlrequest(['timeout' => 10]);
        
        $response = $client->post($url, [
            'json' => array_merge($payload, [
                'contact_id' => $contactId,
                'trigger_data' => $triggerData
            ])
        ]);

        return [
            'status' => $response->getStatusCode() === 200 ? 'success' : 'failed',
            'response_code' => $response->getStatusCode()
        ];
    }

    // Continuing in next file...
