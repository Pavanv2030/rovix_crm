## PHASE 8: Broadcast System (Week 7)

### Prompt 8.1 — Broadcast Creation & Scheduling

```
Build the broadcast campaign manager for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/broadcasts/page.tsx
- src/components/broadcasts/broadcast-form.tsx
- src/lib/api/broadcasts.ts

IMPORTANT: Broadcasts use approved WhatsApp templates only. Rate limit: 70 msg/sec, batch size: 50 recipients.

Create app/Controllers/BroadcastsController.php:

1. index() GET: List all broadcasts
   - Load broadcasts ordered by created_at DESC
   - Show cards with: name, status, scheduled_at, stats (sent/delivered/read)
   - Group by status tabs: All, Draft, Scheduled, Sending, Sent
   - Pass to view: $broadcasts, $statusCounts

2. create() GET: Show broadcast creation form
   - Load approved templates only (status='approved')
   - Load tags for audience filtering
   - Pass to view: $templates, $tags

3. store() POST: Create new broadcast
   - Validate:
     - name required
     - template_name required (must be approved template)
     - audience_filter required (tag_ids or "all")
   - Calculate recipient count based on audience filter
   - Insert broadcast with status='draft'
   - Redirect to broadcast detail

4. view($broadcastId) GET: Show broadcast detail & stats
   - Load broadcast with template info
   - Load recipients with their individual status
   - Calculate stats: sent_count, delivered_count, read_count, failed_count, replied_count
   - Show progress bar
   - Pass to view: $broadcast, $recipients, $stats

5. edit($broadcastId) GET: Edit broadcast
   - Only editable if status='draft'
   - Cannot edit scheduled or sent broadcasts
   - Pass to view: $broadcast, $templates, $tags

6. update($broadcastId) POST: Update broadcast
   - Validate status='draft'
   - Update broadcast fields
   - Recalculate recipient count if audience changed
   - Redirect with success

7. schedule($broadcastId) POST: Schedule broadcast for later
   - Validate scheduled_at is in future
   - Update status to 'scheduled'
   - Redirect with success: "Broadcast scheduled for {datetime}"

8. sendNow($broadcastId) POST: Send broadcast immediately
   - Validate: template is approved, has recipients
   - Update status to 'sending'
   - Create broadcast_recipients records for each contact
   - Dispatch batch jobs to queue (50 recipients per job)
   - Redirect to detail page showing progress

9. cancel($broadcastId) POST: Cancel scheduled broadcast
   - Validate status='scheduled'
   - Update status back to 'draft'
   - Redirect with success

10. delete($broadcastId) POST: Delete broadcast
    - Verify has_min_role('admin')
    - Only allow if status='draft'
    - Delete broadcast (CASCADE recipients)
    - Redirect to list

Create app/Views/broadcasts/index.php:

Layout: main.php, $pageTitle = 'Broadcasts'

Header:
- "New Broadcast" button (primary, slate-blue)
- Status tabs: All (badge) | Draft | Scheduled | Sending | Sent

Broadcast list (cards or table):
Each broadcast:
- Name (bold)
- Status badge (colored: draft=gray, scheduled=blue, sending=yellow, sent=green)
- Template used
- Scheduled/sent datetime
- Recipient count
- Stats bar: Sent X | Delivered Y | Read Z | Failed W
- Progress bar (if sending)
- Actions:
  - View Details
  - Edit (if draft)
  - Send Now (if draft)
  - Schedule (if draft)
  - Cancel (if scheduled)
  - Delete (if draft)

Create app/Views/broadcasts/create.php:

Layout: main.php, $pageTitle = 'Create Broadcast'

Form with tabs/sections:

1. Basic Info:
   - Campaign name input
   - Template selector (dropdown, only approved templates)
   - Preview selected template (live preview)

2. Audience:
   - Filter by tags (multi-select)
   - Filter by custom fields (advanced, optional for MVP)
   - "All contacts" checkbox (overrides filters)
   - Recipient count preview: "Will send to X contacts"

3. Variables (if template has variables):
   - For each variable: input field or CSV upload for per-contact values
   - Option 1: Same value for all (simple input)
   - Option 2: CSV with contact_id, var1, var2, etc. (advanced)

4. Schedule:
   - Send now (radio)
   - Schedule for later (radio + datetime picker)

Alpine.js for live preview and recipient count:
x-data="{
  selectedTemplate: null,
  selectedTags: [],
  audienceFilter: 'tags',
  recipientCount: 0,
  
  async updateRecipientCount() {
    const response = await fetch('/api/broadcasts/count-recipients', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        audience_filter: this.audienceFilter,
        tag_ids: this.selectedTags
      })
    });
    const data = await response.json();
    this.recipientCount = data.count;
  }
}"

Create app/Views/broadcasts/view.php:

Layout: main.php, $pageTitle = $broadcast['name']

Top section:
- Broadcast name (large)
- Status badge
- Template used (linked to template detail)
- Scheduled/sent datetime
- Actions: Cancel (if scheduled), Resend (if sent)

Stats cards (4 columns):
- Total Recipients: {total_recipients}
- Sent: {sent_count} ({percentage}%)
- Delivered: {delivered_count}
- Read: {read_count}
- Replied: {replied_count}
- Failed: {failed_count} (red if > 0)

Progress bar (if status='sending'):
- Visual bar showing sent/total
- "Sending: 250 / 1000 (25%)"
- Auto-refresh every 5 seconds (Alpine.js interval)

Recipients table:
Columns: Contact Name | Phone | Status | Variables | Sent At | Delivered At | Read At | Error

Filters: Status dropdown (All, Pending, Sent, Delivered, Read, Failed)
Pagination: 50 per page

Export button: Download CSV with all recipient details
```

### Prompt 8.2 — Batch Processing with Rate Limiting

```
Build the broadcast batch processor with Meta API rate limiting for Rovix AI Leads Tool.

CRITICAL: Meta WhatsApp Cloud API rate limit is 80 messages/second per phone number ID.
We use 70 msg/sec to leave buffer, batch size 50 recipients per job.

Create app/Libraries/BroadcastProcessor.php:

<?php
namespace App\Libraries;

use App\Models\BroadcastModel;
use App\Models\BroadcastRecipientModel;
use App\Models\ContactModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageTemplateModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\TemplateSendBuilder;

class BroadcastProcessor
{
    /**
     * Prepare broadcast for sending
     * Creates broadcast_recipients records and dispatches batch jobs
     */
    public function prepare(string $broadcastId): int
    {
        $broadcastModel = new BroadcastModel();
        $broadcast = $broadcastModel->find($broadcastId);

        if (!$broadcast) {
            throw new \Exception('Broadcast not found');
        }

        // Get recipients based on audience filter
        $contacts = $this->getRecipients($broadcast);

        if (empty($contacts)) {
            throw new \Exception('No recipients found');
        }

        // Create recipient records
        $recipientModel = new BroadcastRecipientModel();
        $recipientIds = [];

        foreach ($contacts as $contact) {
            // Parse variables if needed (from CSV or defaults)
            $variables = $this->parseVariables($broadcast, $contact);

            $recipientId = $recipientModel->insert([
                'broadcast_id' => $broadcastId,
                'contact_id' => $contact['id'],
                'variables' => json_encode($variables),
                'status' => 'pending'
            ]);

            $recipientIds[] = $recipientId;
        }

        // Update broadcast total count
        $broadcastModel->update($broadcastId, [
            'total_recipients' => count($contacts),
            'status' => 'sending'
        ]);

        // Dispatch batch jobs (50 recipients per batch)
        $batches = array_chunk($recipientIds, 50);
        $dispatcher = new JobDispatcher();

        foreach ($batches as $index => $batch) {
            $dispatcher->dispatch('send_broadcast_batch', [
                'broadcast_id' => $broadcastId,
                'recipient_ids' => $batch,
                'batch_index' => $index
            ], null, 7); // Priority 7 (higher than automation, lower than daily report)
        }

        return count($batches);
    }

    private function getRecipients(array $broadcast): array
    {
        $contactModel = new ContactModel();
        $audienceFilter = json_decode($broadcast['audience_filter'], true);

        if ($audienceFilter['type'] === 'all') {
            // All contacts
            return $contactModel->findAll();
        }

        if ($audienceFilter['type'] === 'tags') {
            // Contacts with specific tags
            $tagIds = $audienceFilter['tag_ids'];
            return $contactModel
                ->join('contact_tags', 'contact_tags.contact_id = contacts.id')
                ->whereIn('contact_tags.tag_id', $tagIds)
                ->groupBy('contacts.id')
                ->findAll();
        }

        // Add more filter types here (custom fields, etc.)

        return [];
    }

    private function parseVariables(array $broadcast, array $contact): array
    {
        // For MVP: use simple defaults or contact fields
        // Format: { "1": "John", "2": "Rovix AI" }
        
        // TODO: Parse from CSV or broadcast variables config
        return [
            '1' => $contact['name'] ?? 'there',
            '2' => 'Rovix AI'
        ];
    }

    /**
     * Process a batch of recipients (called by job queue)
     */
    public function processBatch(string $broadcastId, array $recipientIds): array
    {
        $broadcastModel = new BroadcastModel();
        $broadcast = $broadcastModel->find($broadcastId);

        if (!$broadcast) {
            throw new \Exception('Broadcast not found');
        }

        // Get WhatsApp config
        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('account_id', $broadcast['account_id'])->first();

        if (!$waConfig) {
            throw new \Exception('WhatsApp not connected');
        }

        $encryption = new Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        // Get template
        $templateModel = new MessageTemplateModel();
        $template = $templateModel
            ->where('account_id', $broadcast['account_id'])
            ->where('name', $broadcast['template_name'])
            ->where('status', 'approved')
            ->first();

        if (!$template) {
            throw new \Exception('Template not found or not approved');
        }

        // Process recipients with rate limiting
        $recipientModel = new BroadcastRecipientModel();
        $contactModel = new ContactModel();
        $metaApi = new MetaApi();

        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $startTime = microtime(true);
        $messagesSent = 0;
        $maxMessagesPerSecond = 70;

        foreach ($recipientIds as $recipientId) {
            $recipient = $recipientModel->find($recipientId);
            if (!$recipient) continue;

            $contact = $contactModel->find($recipient['contact_id']);
            if (!$contact) continue;

            try {
                // Rate limiting: sleep if we're sending too fast
                if ($messagesSent > 0 && $messagesSent % $maxMessagesPerSecond === 0) {
                    $elapsed = microtime(true) - $startTime;
                    if ($elapsed < 1.0) {
                        usleep((1.0 - $elapsed) * 1000000); // Sleep remainder of 1 second
                    }
                    $startTime = microtime(true);
                }

                // Build template components with variables
                $variables = json_decode($recipient['variables'], true);
                $components = TemplateSendBuilder::buildComponents($template, $variables);

                // Send via Meta API
                $response = $metaApi->sendTemplate(
                    $waConfig['phone_number_id'],
                    $accessToken,
                    $contact['phone_normalized'],
                    $template['name'],
                    $template['language'],
                    $components
                );

                // Update recipient status
                $recipientModel->update($recipientId, [
                    'status' => 'sent',
                    'whatsapp_message_id' => $response['messages'][0]['id']
                ]);

                $results['sent']++;
                $messagesSent++;

                // Update broadcast sent_count
                $broadcastModel->update($broadcastId, [
                    'sent_count' => $broadcast['sent_count'] + 1
                ]);

            } catch (\Exception $e) {
                // Log error
                $recipientModel->update($recipientId, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);

                $results['failed']++;
                $results['errors'][] = [
                    'recipient_id' => $recipientId,
                    'contact_phone' => $contact['phone'],
                    'error' => $e->getMessage()
                ];

                // Update broadcast failed_count
                $broadcastModel->update($broadcastId, [
                    'failed_count' => $broadcast['failed_count'] + 1
                ]);

                // Don't throw - continue with next recipient
                log_message('error', "Broadcast batch error: " . $e->getMessage());
            }
        }

        // Check if broadcast is complete
        $broadcast = $broadcastModel->find($broadcastId); // Reload
        if ($broadcast['sent_count'] + $broadcast['failed_count'] >= $broadcast['total_recipients']) {
            $broadcastModel->update($broadcastId, ['status' => 'sent']);
        }

        return $results;
    }
}

Update app/Commands/ProcessQueue.php (add case):

case 'send_broadcast_batch':
    $processor = new \App\Libraries\BroadcastProcessor();
    $result = $processor->processBatch(
        $payload['broadcast_id'],
        $payload['recipient_ids']
    );
    CLI::write("  Sent: {$result['sent']}, Failed: {$result['failed']}", 'cyan');
    break;

Create app/Controllers/Api/BroadcastsController.php:

1. countRecipients() POST: Calculate recipient count for audience filter
   - Input: audience_filter JSON
   - Query contacts matching filter
   - Return JSON: { count: X }

2. getProgress($broadcastId) GET: Get current broadcast progress
   - Load broadcast with updated counts
   - Calculate percentage: (sent + failed) / total
   - Return JSON: { status, sent_count, total_recipients, percentage }

Add cron job for scheduled broadcasts:

In RunScheduled.php, add:

// Check for scheduled broadcasts
$broadcastModel = new \App\Models\BroadcastModel();
$due = $broadcastModel
    ->where('status', 'scheduled')
    ->where('scheduled_at <=', date('Y-m-d H:i:s'))
    ->findAll();

foreach ($due as $broadcast) {
    CLI::write("Dispatching scheduled broadcast: {$broadcast['name']}", 'blue');
    $processor = new \App\Libraries\BroadcastProcessor();
    $processor->prepare($broadcast['id']);
}
```

Continuing in next file...
