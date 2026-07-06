### Prompt 8.3 — Broadcast Analytics & Reporting

```
Build broadcast analytics and reporting for Rovix AI Leads Tool.

Create app/Views/broadcasts/view.php (add analytics section):

After recipient table, add analytics dashboard:

1. Delivery Funnel Chart:
   - Visual funnel showing: Sent → Delivered → Read → Replied
   - Percentages at each stage
   - Use simple CSS for bars (no chart library needed for MVP)

Example:
<div class="space-y-2">
  <div class="flex items-center">
    <div class="w-32 text-sm">Sent</div>
    <div class="flex-1 bg-gray-200 rounded-full h-8">
      <div class="bg-blue-500 h-8 rounded-full flex items-center px-3 text-white text-sm" 
           style="width: 100%">
        <?= $broadcast['sent_count'] ?> (100%)
      </div>
    </div>
  </div>
  
  <div class="flex items-center">
    <div class="w-32 text-sm">Delivered</div>
    <div class="flex-1 bg-gray-200 rounded-full h-8">
      <div class="bg-green-500 h-8 rounded-full flex items-center px-3 text-white text-sm" 
           style="width: <?= ($broadcast['delivered_count'] / $broadcast['sent_count']) * 100 ?>%">
        <?= $broadcast['delivered_count'] ?> (<?= round(($broadcast['delivered_count'] / $broadcast['sent_count']) * 100) ?>%)
      </div>
    </div>
  </div>
  
  <div class="flex items-center">
    <div class="w-32 text-sm">Read</div>
    <div class="flex-1 bg-gray-200 rounded-full h-8">
      <div class="bg-purple-500 h-8 rounded-full flex items-center px-3 text-white text-sm" 
           style="width: <?= ($broadcast['read_count'] / $broadcast['sent_count']) * 100 ?>%">
        <?= $broadcast['read_count'] ?> (<?= round(($broadcast['read_count'] / $broadcast['sent_count']) * 100) ?>%)
      </div>
    </div>
  </div>
  
  <div class="flex items-center">
    <div class="w-32 text-sm">Replied</div>
    <div class="flex-1 bg-gray-200 rounded-full h-8">
      <div class="bg-yellow-500 h-8 rounded-full flex items-center px-3 text-white text-sm" 
           style="width: <?= ($broadcast['replied_count'] / $broadcast['sent_count']) * 100 ?>%">
        <?= $broadcast['replied_count'] ?> (<?= round(($broadcast['replied_count'] / $broadcast['sent_count']) * 100) ?>%)
      </div>
    </div>
  </div>
</div>

2. Metrics Cards:
- Delivery Rate: (delivered / sent) * 100
- Read Rate: (read / sent) * 100
- Reply Rate: (replied / sent) * 100
- Failure Rate: (failed / total) * 100

3. Timeline:
- Show when broadcast was created, started, completed
- If sending: show estimated completion time based on current rate

4. Failed Recipients Analysis:
- Group errors by type
- Show most common error messages
- "Retry Failed" button (creates new broadcast with only failed recipients)

Create app/Controllers/BroadcastsController.php (add methods):

11. export($broadcastId) GET: Export broadcast results to CSV
    - Generate CSV with all recipients and their status
    - Columns: Name, Phone, Status, Sent At, Delivered At, Read At, Variables, Error
    - Download as broadcast_results_{date}.csv

12. retryFailed($broadcastId) POST: Retry sending to failed recipients
    - Clone broadcast
    - Filter recipients: only those with status='failed'
    - Create new broadcast with same template
    - Dispatch batch jobs
    - Redirect to new broadcast detail

13. duplicate($broadcastId) POST: Duplicate broadcast
    - Clone broadcast with new name "{original_name} (Copy)"
    - Status='draft'
    - Do not clone recipients
    - Redirect to edit page

Create app/Libraries/BroadcastExporter.php:

<?php
namespace App\Libraries;

use App\Models\BroadcastModel;
use App\Models\BroadcastRecipientModel;
use App\Models\ContactModel;

class BroadcastExporter
{
    public function exportToCsv(string $broadcastId): string
    {
        $broadcastModel = new BroadcastModel();
        $broadcast = $broadcastModel->find($broadcastId);

        if (!$broadcast) {
            throw new \Exception('Broadcast not found');
        }

        $recipientModel = new BroadcastRecipientModel();
        $contactModel = new ContactModel();

        $recipients = $recipientModel
            ->where('broadcast_id', $broadcastId)
            ->findAll();

        // Create CSV in memory
        $output = fopen('php://temp', 'r+');

        // Header row
        fputcsv($output, [
            'Contact Name',
            'Phone',
            'Status',
            'Variables',
            'WhatsApp Message ID',
            'Sent At',
            'Delivered At',
            'Read At',
            'Error Message'
        ]);

        // Data rows
        foreach ($recipients as $recipient) {
            $contact = $contactModel->find($recipient['contact_id']);
            
            fputcsv($output, [
                $contact['name'] ?? '',
                $contact['phone'] ?? '',
                $recipient['status'],
                $recipient['variables'],
                $recipient['whatsapp_message_id'] ?? '',
                $recipient['created_at'],
                '', // TODO: Get from message status updates
                '', // TODO: Get from message status updates
                $recipient['error_message'] ?? ''
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}

Update BroadcastsController::export():

public function export($broadcastId)
{
    $exporter = new \App\Libraries\BroadcastExporter();
    $csv = $exporter->exportToCsv($broadcastId);

    $broadcastModel = new BroadcastModel();
    $broadcast = $broadcastModel->find($broadcastId);

    return $this->response
        ->setHeader('Content-Type', 'text/csv')
        ->setHeader('Content-Disposition', 'attachment; filename="broadcast_' . $broadcast['name'] . '_' . date('Y-m-d') . '.csv"')
        ->setBody($csv);
}

Add real-time progress updates (Alpine.js):

In broadcasts/view.php, add if status='sending':

<div x-data="{ 
  progress: <?= json_encode([
    'sent' => $broadcast['sent_count'],
    'total' => $broadcast['total_recipients'],
    'percentage' => round(($broadcast['sent_count'] / $broadcast['total_recipients']) * 100)
  ]) ?>,
  async updateProgress() {
    const response = await fetch('/api/broadcasts/<?= $broadcast['id'] ?>/progress');
    this.progress = await response.json();
  }
}" x-init="setInterval(() => updateProgress(), 5000)">
  
  <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-lg font-semibold text-blue-900">Sending in Progress</h3>
      <span class="text-2xl font-bold text-blue-600" x-text="progress.percentage + '%'"></span>
    </div>
    
    <div class="bg-gray-200 rounded-full h-4 mb-2">
      <div class="bg-blue-600 h-4 rounded-full transition-all duration-500" 
           :style="'width: ' + progress.percentage + '%'"></div>
    </div>
    
    <p class="text-sm text-gray-600">
      Sent <span x-text="progress.sent"></span> of <span x-text="progress.total"></span> messages
    </p>
  </div>
</div>

Add notification when broadcast completes:

In ProcessQueue.php, after updating broadcast status to 'sent':

// Send notification to creator
$profile = (new \App\Models\ProfileModel())->where('account_id', $broadcast['account_id'])->first();
if ($profile) {
    // TODO: Add in-app notification system (Phase 11)
    // For now: just log
    log_message('info', "Broadcast '{$broadcast['name']}' completed. Sent: {$broadcast['sent_count']}, Failed: {$broadcast['failed_count']}");
}

Add routes:
GET /broadcasts → BroadcastsController::index
GET /broadcasts/create → BroadcastsController::create
POST /broadcasts → BroadcastsController::store
GET /broadcasts/{id} → BroadcastsController::view
GET /broadcasts/{id}/edit → BroadcastsController::edit
POST /broadcasts/{id} → BroadcastsController::update
POST /broadcasts/{id}/schedule → BroadcastsController::schedule
POST /broadcasts/{id}/send-now → BroadcastsController::sendNow
POST /broadcasts/{id}/cancel → BroadcastsController::cancel
POST /broadcasts/{id}/retry-failed → BroadcastsController::retryFailed
POST /broadcasts/{id}/duplicate → BroadcastsController::duplicate
GET /broadcasts/{id}/export → BroadcastsController::export
POST /broadcasts/{id}/delete → BroadcastsController::delete

API routes:
POST /api/broadcasts/count-recipients → Api\BroadcastsController::countRecipients
GET /api/broadcasts/{id}/progress → Api\BroadcastsController::getProgress
```

### Testing Phase 8

Manual test checklist:

```bash
# Prerequisites: Have approved template and contacts with tags

# 1. Navigate to broadcasts
http://localhost:8080/broadcasts

# Test: Empty state or existing broadcasts show

# 2. Create broadcast
- Click "New Broadcast"
- Name: "January Promotion"
- Select approved template (e.g., welcome_message)
- Audience: Select tag "Hot Lead"
- Check recipient count updates

# Test: Shows "Will send to X contacts"

# 3. Set variables (if template has them)
- Fill variable values or upload CSV

# Test: Preview shows template with variables replaced

# 4. Send now
- Click "Send Now"
- Confirm

# Test: 
- Status changes to 'sending'
- Redirected to broadcast detail
- Progress bar shows
- Job queue starts processing

# 5. Monitor progress
php spark queue:process

# Test: 
- Batch jobs process (50 recipients each)
- sent_count increments
- Progress bar updates every 5s
- Rate limiting works (70 msg/sec max)

# 6. Check recipient status
- View Recipients table
- Filter by status

# Test: 
- Each recipient shows status: sent/delivered/read/failed
- whatsapp_message_id stored
- Timestamps recorded

# 7. Wait for status updates (webhook)
- Meta sends status updates as messages are delivered/read

# Test:
- delivered_count increments
- read_count increments
- Individual recipient status updates

# 8. View analytics
- Check delivery funnel
- Check metric cards

# Test:
- Percentages calculated correctly
- Funnel bars display proportionally
- Metrics: delivery rate, read rate, reply rate

# 9. Export results
- Click "Export CSV"

# Test:
- CSV downloads with all recipients
- Columns: name, phone, status, variables, timestamps, errors

# 10. Retry failed recipients
- If some failed, click "Retry Failed"

# Test:
- New broadcast created
- Only failed recipients included
- Can edit and send again

# 11. Schedule broadcast
- Create new broadcast
- Select "Schedule for later"
- Pick datetime tomorrow 10 AM
- Save

# Test:
- Status='scheduled'
- Shows scheduled_at datetime
- Doesn't send yet

# 12. Scheduled broadcast auto-send
# Set system time to scheduled time or wait
php spark run:scheduled

# Test:
- Cron detects scheduled broadcast
- Dispatches batch jobs
- Status changes to 'sending'

# 13. Cancel scheduled broadcast
- Before scheduled time, click "Cancel"

# Test:
- Status back to 'draft'
- scheduled_at cleared
- Can edit and reschedule

# 14. Rate limiting verification
- Create broadcast with 500+ recipients
- Monitor sending speed

# Test:
- Never exceeds 70 msg/sec
- Batch processor sleeps between bursts
- All messages eventually sent

# 15. Batch size verification
- Check job_queue table
SELECT payload FROM job_queue WHERE job_type='send_broadcast_batch';

# Test:
- Each batch has exactly 50 recipient_ids (except last batch)

# 16. Tenant isolation
- Login as different account
- View broadcasts

# Test: Only see own broadcasts, not other accounts'

# 17. Error handling
- Create broadcast to invalid phone numbers
- Send

# Test:
- Failed recipients show error messages
- failed_count increments
- Broadcast still completes
- Can retry failed only

# 18. Duplicate broadcast
- Click "Duplicate" on sent broadcast

# Test:
- New broadcast created with "(Copy)" suffix
- Status='draft'
- Same template and audience filter
- No recipients copied
```

**Pass Criteria:**
- ✅ Broadcast CRUD works (create, edit, view, delete)
- ✅ Template selector shows only approved templates
- ✅ Audience filtering works (by tags)
- ✅ Recipient count preview accurate
- ✅ Send now dispatches batch jobs (50 per batch)
- ✅ Rate limiting enforced (70 msg/sec max)
- ✅ Progress updates in real-time (5s interval)
- ✅ Status updates from webhook (delivered, read)
- ✅ Analytics funnel displays correctly
- ✅ Export CSV works with all data
- ✅ Retry failed creates new broadcast with failed only
- ✅ Schedule works, cron sends at scheduled time
- ✅ Cancel scheduled works
- ✅ Duplicate broadcast works
- ✅ Error handling: failed sends logged, broadcast completes
- ✅ Tenant isolation (accounts only see own broadcasts)
- ✅ Variables replaced correctly per recipient
- ✅ replied_count increments when contacts reply

**Common Issues:**
- Rate limit exceeded: Reduce maxMessagesPerSecond in BroadcastProcessor
- Batch jobs stuck: Check job_queue locked_until, check queue processor running
- Progress not updating: Check Alpine.js interval, check API route returns JSON
- Status not updating: Check webhook processes status events, check broadcast_recipient.whatsapp_message_id matches
- Variables not replacing: Check TemplateSendBuilder.buildComponents, check variables JSON format
- Scheduled broadcast not sending: Check cron running every minute, check scheduled_at format
- Export fails: Check CSV generation, check file permissions
- Memory exhausted: Reduce batch size from 50 to 25, add sleep between batches
- All messages failing: Check template approved, check access_token valid, check phone numbers normalized

---
