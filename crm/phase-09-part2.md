    private function evaluateCondition(array $config, string $contactId, array $triggerData): bool
    {
        $conditionType = $config['condition_type'];
        
        switch ($conditionType) {
            case 'message_contains':
                $messageText = $triggerData['message']['text']['body'] ?? '';
                $keyword = $config['keyword'];
                return stripos($messageText, $keyword) !== false;

            case 'contact_has_tag':
                $tagId = $config['tag_id'];
                $contactTagModel = new \App\Models\ContactTagModel();
                $has = $contactTagModel
                    ->where('contact_id', $contactId)
                    ->where('tag_id', $tagId)
                    ->first();
                return $has !== null;

            case 'custom_field_equals':
                $fieldId = $config['custom_field_id'];
                $expectedValue = $config['value'];
                
                $customValueModel = new \App\Models\ContactCustomValueModel();
                $actual = $customValueModel
                    ->where('contact_id', $contactId)
                    ->where('custom_field_id', $fieldId)
                    ->first();
                
                return $actual && $actual['value'] === $expectedValue;

            case 'contact_name_is_set':
                $contactModel = new ContactModel();
                $contact = $contactModel->find($contactId);
                return !empty($contact['name']);

            case 'time_of_day':
                $currentHour = (int)date('H');
                $startHour = $config['start_hour'] ?? 9;
                $endHour = $config['end_hour'] ?? 17;
                return $currentHour >= $startHour && $currentHour < $endHour;

            default:
                return false;
        }
    }

    private function executeBranch(array $step, string $contactId, array $triggerData, array $automation, bool $conditionResult): array
    {
        $stepModel = new AutomationStepModel();
        $branch = $conditionResult ? 'yes' : 'no';
        
        // Get steps for this branch
        $branchSteps = $stepModel
            ->where('parent_step_id', $step['id'])
            ->where('branch', $branch)
            ->orderBy('position', 'ASC')
            ->findAll();

        $executedSteps = [];

        foreach ($branchSteps as $branchStep) {
            $result = $this->executeStep($branchStep, $contactId, $triggerData, $automation);
            $executedSteps[] = [
                'step_id' => $branchStep['id'],
                'step_type' => $branchStep['step_type'],
                'branch' => $branch,
                'result' => $result
            ];
        }

        return $executedSteps;
    }

    private function scheduleWaitedSteps(array $automation, string $contactId, array $waitStep, array $triggerData): void
    {
        $config = json_decode($waitStep['step_config'], true);
        $delay = $config['delay'];
        $unit = $config['unit']; // minutes, hours, days

        // Calculate run_after datetime
        $seconds = match($unit) {
            'minutes' => $delay * 60,
            'hours' => $delay * 3600,
            'days' => $delay * 86400,
            default => 0
        };

        $runAfter = date('Y-m-d H:i:s', time() + $seconds);

        // Dispatch job to resume automation after wait
        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('execute_wait_step', [
            'automation_id' => $automation['id'],
            'contact_id' => $contactId,
            'wait_step_id' => $waitStep['id'],
            'trigger_data' => $triggerData
        ], $runAfter, 5);
    }

    /**
     * Resume automation execution after wait step
     * Called by job queue
     */
    public function resumeAfterWait(array $payload): void
    {
        BaseModel::setBypassAccountScope(true);

        $automationId = $payload['automation_id'];
        $contactId = $payload['contact_id'];
        $waitStepId = $payload['wait_step_id'];
        $triggerData = $payload['trigger_data'];

        // Load automation
        $automationModel = new AutomationModel();
        $automation = $automationModel->find($automationId);

        if (!$automation) {
            return; // Automation deleted
        }

        // Get steps after wait step
        $stepModel = new AutomationStepModel();
        $waitStep = $stepModel->find($waitStepId);
        
        $remainingSteps = $stepModel
            ->where('automation_id', $automationId)
            ->where('parent_step_id', null)
            ->where('position >', $waitStep['position'])
            ->orderBy('position', 'ASC')
            ->findAll();

        // Execute remaining steps
        foreach ($remainingSteps as $step) {
            $this->executeStep($step, $contactId, $triggerData, $automation);

            // If another wait, schedule again
            if ($step['step_type'] === 'wait') {
                $this->scheduleWaitedSteps($automation, $contactId, $step, $triggerData);
                break;
            }
        }
    }
}

Update app/Commands/ProcessQueue.php (add cases):

case 'run_automation':
    $engine = new \App\Libraries\AutomationEngine();
    $engine->processForTrigger($payload);
    CLI::write("  Automation triggered for contact {$payload['contact_id']}", 'cyan');
    break;

case 'execute_wait_step':
    $engine = new \App\Libraries\AutomationEngine();
    $engine->resumeAfterWait($payload);
    CLI::write("  Resumed automation after wait", 'cyan');
    break;
```

### Prompt 9.3 — Time-Based Automations & Cron Triggers

```
Build time-based automation triggers for Rovix AI Leads Tool.

Time-based automations run on schedule (e.g., "Send follow-up 24 hours after last message").

Create app/Commands/CheckTimeTriggers.php:

<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\AutomationModel;
use App\Models\ConversationModel;
use App\Models\ContactModel;
use App\Models\BaseModel;
use App\Libraries\JobDispatcher;

class CheckTimeTriggers extends BaseCommand
{
    protected $group = 'Automations';
    protected $name = 'automations:check-time';
    protected $description = 'Check and trigger time-based automations';

    public function run(array $params)
    {
        BaseModel::setBypassAccountScope(true);

        $automationModel = new AutomationModel();
        $timeAutomations = $automationModel
            ->where('trigger_type', 'time_based')
            ->where('is_active', 1)
            ->findAll();

        if (empty($timeAutomations)) {
            CLI::write('No time-based automations', 'yellow');
            return;
        }

        CLI::write('Checking ' . count($timeAutomations) . ' time-based automations...', 'green');

        $triggered = 0;

        foreach ($timeAutomations as $automation) {
            $config = json_decode($automation['trigger_config'], true);
            $timeTriggerType = $config['time_trigger_type']; // 'delay_after_message', 'daily_at_time', 'weekly_on_day'

            switch ($timeTriggerType) {
                case 'delay_after_message':
                    $triggered += $this->checkDelayAfterMessage($automation, $config);
                    break;

                case 'daily_at_time':
                    $triggered += $this->checkDailyAtTime($automation, $config);
                    break;

                case 'weekly_on_day':
                    $triggered += $this->checkWeeklyOnDay($automation, $config);
                    break;
            }
        }

        CLI::write("Triggered {$triggered} automations", 'green');
    }

    private function checkDelayAfterMessage(array $automation, array $config): int
    {
        // Example: "Send follow-up 24 hours after last customer message"
        $delay = $config['delay']; // e.g., 24
        $unit = $config['unit']; // e.g., 'hours'

        $seconds = match($unit) {
            'minutes' => $delay * 60,
            'hours' => $delay * 3600,
            'days' => $delay * 86400,
            default => 0
        };

        $cutoffTime = date('Y-m-d H:i:s', time() - $seconds);

        // Find conversations where last message was from customer and was before cutoff
        $conversationModel = new ConversationModel();
        $conversations = $conversationModel
            ->where('account_id', $automation['account_id'])
            ->where('status', 'open')
            ->where('last_message_at <', $cutoffTime)
            ->findAll(100); // Limit to prevent overwhelming

        $triggered = 0;

        foreach ($conversations as $conversation) {
            // Check if last message was from customer
            $messageModel = new \App\Models\MessageModel();
            $lastMessage = $messageModel
                ->where('conversation_id', $conversation['id'])
                ->orderBy('created_at', 'DESC')
                ->first();

            if ($lastMessage && $lastMessage['sender_type'] === 'customer') {
                // Trigger automation
                $dispatcher = new JobDispatcher();
                $dispatcher->dispatch('run_automation', [
                    'account_id' => $automation['account_id'],
                    'contact_id' => $conversation['contact_id'],
                    'conversation_id' => $conversation['id'],
                    'trigger_type' => 'time_based',
                    'message' => []
                ], null, 5);

                $triggered++;
            }
        }

        return $triggered;
    }

    private function checkDailyAtTime(array $automation, array $config): int
    {
        // Example: "Send daily reminder at 9 AM"
        $targetHour = $config['hour']; // e.g., 9
        $targetMinute = $config['minute'] ?? 0;
        $currentHour = (int)date('H');
        $currentMinute = (int)date('i');

        // Only trigger if current time matches target time (within cron interval)
        if ($currentHour !== $targetHour || abs($currentMinute - $targetMinute) > 5) {
            return 0;
        }

        // Get all contacts for this account
        $contactModel = new ContactModel();
        $contacts = $contactModel
            ->where('account_id', $automation['account_id'])
            ->findAll(100); // Limit

        $triggered = 0;

        foreach ($contacts as $contact) {
            // Check if we already triggered today (to prevent duplicates)
            $logModel = new \App\Models\AutomationLogModel();
            $alreadyTriggered = $logModel
                ->where('automation_id', $automation['id'])
                ->where('contact_id', $contact['id'])
                ->where('created_at >', date('Y-m-d 00:00:00'))
                ->first();

            if ($alreadyTriggered) {
                continue;
            }

            // Trigger automation
            $dispatcher = new JobDispatcher();
            $dispatcher->dispatch('run_automation', [
                'account_id' => $automation['account_id'],
                'contact_id' => $contact['id'],
                'trigger_type' => 'time_based',
                'message' => []
            ], null, 5);

            $triggered++;
        }

        return $triggered;
    }

    private function checkWeeklyOnDay(array $automation, array $config): int
    {
        // Example: "Send weekly update every Monday at 10 AM"
        $targetDay = $config['day_of_week']; // 1=Monday, 7=Sunday
        $targetHour = $config['hour'];
        $currentDay = (int)date('N'); // 1=Monday, 7=Sunday
        $currentHour = (int)date('H');

        if ($currentDay !== $targetDay || $currentHour !== $targetHour) {
            return 0;
        }

        // Similar to daily, but only on specific day
        // Implementation similar to checkDailyAtTime
        return 0; // Placeholder
    }
}

Add to RunScheduled.php:

// Run time-based automation checks every 5 minutes
$minute = (int)date('i');
if ($minute % 5 === 0) {
    command('automations:check-time');
}
```

### Testing Phase 9

Manual test checklist:

```bash
# Prerequisites: Have contacts, conversations, tags

# 1. Navigate to automations
http://localhost:8080/automations

# Test: Empty state or existing automations show

# 2. Create simple automation
- Click "New Automation"
- Name: "Welcome New Contacts"
- Trigger: New Contact Created
- Add step: Send Message → "Welcome! Thanks for contacting us."
- Add step: Add Tag → "New Lead"
- Save

# Test: Automation created, shows in list

# 3. Trigger automation manually
- Create new contact via /contacts/create

# Test:
- Automation job dispatched to queue
php spark queue:process
- Message sent to contact (if conversation exists)
- Tag "New Lead" added to contact

# 4. Create keyword-match automation
- Name: "Pricing Info"
- Trigger: Keyword Match → "price,pricing,cost"
- Add step: Send Template → select pricing template
- Save

# Test: Automation created

# 5. Trigger keyword automation
- Send WhatsApp message containing "price"

# Test:
- Webhook receives message
- Automation triggered
- Template message sent back

# 6. Create conditional automation
- Name: "VIP Treatment"
- Trigger: New Message Received
- Add step: Condition → "Contact has tag: VIP"
  - If YES: Send Message → "Thank you, our VIP team will assist you shortly."
  - If NO: Send Message → "Thanks for your message, we'll respond soon."
- Save

# Test:
- Send message from VIP contact → gets VIP message
- Send message from non-VIP → gets regular message

# 7. Create automation with wait
- Name: "Follow-up Sequence"
- Trigger: Tag Added → "Hot Lead"
- Add step: Send Message → "Thanks for your interest!"
- Add step: Wait → 1 hour
- Add step: Send Message → "Just checking in..."
- Add step: Wait → 24 hours
- Add step: Send Message → "Final reminder!"
- Save

# Test:
- Add "Hot Lead" tag to contact
- First message sends immediately
- Check job_queue: wait job scheduled for 1 hour later
- After 1 hour: second message sends
- After 24 more hours: third message sends

# 8. Test automation with deal creation
- Name: "Create Deal on Interest"
- Trigger: Keyword Match → "interested,buy"
- Add step: Create Deal → Pipeline "Sales", Stage "New Lead", Value ₹50000
- Save

# Test:
- Send message "I'm interested"
- Deal created automatically in pipeline

# 9. Test webhook action
- Name: "Notify External System"
- Trigger: New Contact Created
- Add step: Send Webhook → URL: https://webhook.site/... (test URL)
- Save

# Test:
- Create contact
- Webhook fires with contact data
- Check webhook.site for received payload

# 10. Test time-based automation
- Name: "Follow-up Inactive"
- Trigger: Time-Based → 24 hours after last message
- Add step: Send Message → "Are you still interested?"
- Save

# Test:
- Wait 24 hours OR manually adjust last_message_at to yesterday
php spark automations:check-time
- Automation triggers for inactive conversations

# 11. Test daily automation
- Name: "Morning Greeting"
- Trigger: Time-Based → Daily at 9:00 AM
- Add step: Send Message → "Good morning!"
- Save

# Test:
- Set system time to 9:00 AM OR wait until 9:00 AM
php spark run:scheduled
- Message sent to all contacts (or filtered subset)

# 12. Toggle automation on/off
- Click toggle switch on automation card

# Test:
- Status changes to inactive
- Automation no longer triggers
- Toggle back: active again

# 13. View automation logs
- Open automation detail
- Check execution logs

# Test:
- Shows recent executions
- Contact name, timestamp, status
- Steps executed displayed
- Errors shown if any

# 14. Edit automation
- Click "Edit"
- Change trigger config
- Add new step
- Reorder steps
- Save

# Test: Changes saved, automation uses new config

# 15. Duplicate automation
- Click "Duplicate"

# Test: New automation created with "(Copy)" suffix

# 16. Test error handling
- Create automation with invalid config (e.g., non-existent tag)
- Trigger it

# Test:
- Execution log shows "failed" status
- Error message logged
- Automation continues for other triggers

# 17. Tenant isolation
- Login as different account
- View automations

# Test: Only see own automations

# 18. Test multiple automations on same trigger
- Create 3 automations, all with trigger "New Message Received"
- Send message

# Test: All 3 automations execute
```

**Pass Criteria:**
- ✅ Automation CRUD works (create, edit, view, delete)
- ✅ All 7 trigger types work correctly
- ✅ All 11 action types execute
- ✅ Conditions evaluate correctly (if/then branching)
- ✅ Wait steps schedule correctly, resume after delay
- ✅ Time-based triggers run on schedule (cron)
- ✅ Execution logs created for each run
- ✅ Error handling: failed executions logged, don't crash
- ✅ Multiple automations can trigger from same event
- ✅ Toggle active/inactive works
- ✅ Webhook actions send HTTP POST correctly
- ✅ Deal creation action works
- ✅ Tag add/remove actions work
- ✅ Message sending works (queued via job system)
- ✅ Tenant isolation (accounts only see own automations)

**Common Issues:**
- Automation not triggering: Check is_active=1, check trigger matching logic
- Wait not resuming: Check job_queue run_after datetime, check queue processor running
- Condition always false: Check condition evaluation logic, check data exists
- Webhook timeout: Reduce webhook timeout to 5s, handle errors gracefully
- Time trigger firing multiple times: Check "already triggered today" logic
- Messages not sending: Check conversation_id exists in trigger data
- Steps out of order: Check position field, check ORDER BY position
- Branch not executing: Check parent_step_id, check branch value ('yes' or 'no')

---
