Create app/Commands/ProcessQueue.php:

<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\JobQueueModel;
use App\Models\BaseModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class ProcessQueue extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:process';
    protected $description = 'Process pending background jobs';

    public function run(array $params)
    {
        // Bypass tenant scoping for background processing
        BaseModel::setBypassAccountScope(true);

        $model = new JobQueueModel();
        
        // Lock expired jobs back to pending
        $model->where('status', 'processing')
            ->where('locked_until <', date('Y-m-d H:i:s'))
            ->set(['status' => 'pending', 'locked_until' => null])
            ->update();

        // Get pending jobs (ordered by priority DESC, created_at ASC)
        $jobs = $model->where('status', 'pending')
            ->where('(run_after IS NULL OR run_after <=', date('Y-m-d H:i:s') . ')')
            ->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->findAll(50);

        if (empty($jobs)) {
            CLI::write('No pending jobs', 'yellow');
            return;
        }

        CLI::write('Processing ' . count($jobs) . ' jobs...', 'green');

        foreach ($jobs as $job) {
            $this->processJob($job, $model);
        }

        CLI::write('Done', 'green');
    }

    private function processJob(array $job, JobQueueModel $model)
    {
        // Lock the job for 5 minutes
        $lockedUntil = date('Y-m-d H:i:s', time() + 300);
        
        $model->update($job['id'], [
            'status' => 'processing',
            'locked_until' => $lockedUntil,
            'attempts' => $job['attempts'] + 1
        ]);

        $payload = json_decode($job['payload'], true);

        try {
            CLI::write("Processing job #{$job['id']} ({$job['job_type']})", 'blue');

            switch ($job['job_type']) {
                case 'send_message':
                    $this->sendMessage($payload);
                    break;

                case 'run_automation':
                    $this->runAutomation($payload);
                    break;

                case 'check_flow':
                    $this->checkFlow($payload);
                    break;

                case 'send_broadcast_batch':
                    $this->sendBroadcastBatch($payload);
                    break;

                case 'execute_wait_step':
                    $this->executeWaitStep($payload);
                    break;

                case 'send_daily_report':
                    $this->sendDailyReport($payload);
                    break;

                default:
                    throw new \Exception('Unknown job type: ' . $job['job_type']);
            }

            // Mark as done
            $model->update($job['id'], [
                'status' => 'done',
                'locked_until' => null
            ]);

            CLI::write("✓ Job #{$job['id']} completed", 'green');

        } catch (\Exception $e) {
            CLI::write("✗ Job #{$job['id']} failed: " . $e->getMessage(), 'red');

            // Log the error
            $failedLog = json_decode($job['failed_attempts_log'] ?? '[]', true);
            $failedLog[] = [
                'attempt' => $job['attempts'] + 1,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Retry or fail permanently
            if ($job['attempts'] + 1 >= $job['max_retries']) {
                $model->update($job['id'], [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'failed_attempts_log' => json_encode($failedLog),
                    'locked_until' => null
                ]);
            } else {
                // Retry with exponential backoff
                $retryAfter = date('Y-m-d H:i:s', time() + (60 * pow(2, $job['attempts'])));
                $model->update($job['id'], [
                    'status' => 'pending',
                    'run_after' => $retryAfter,
                    'failed_attempts_log' => json_encode($failedLog),
                    'locked_until' => null
                ]);
            }
        }
    }

    private function sendMessage(array $payload)
    {
        // Implementation stub - will be fleshed out in Phase 4
        CLI::write('  Sending message to ' . $payload['to'], 'cyan');
    }

    private function runAutomation(array $payload)
    {
        // Implementation stub - will be fleshed out in Phase 9
        CLI::write('  Running automation for contact ' . $payload['contact_id'], 'cyan');
    }

    private function checkFlow(array $payload)
    {
        // Implementation stub - will be fleshed out in Phase 10
        CLI::write('  Checking flow for contact ' . $payload['contact_id'], 'cyan');
    }

    private function sendBroadcastBatch(array $payload)
    {
        // Implementation stub - will be fleshed out in Phase 8
        CLI::write('  Sending broadcast batch: ' . count($payload['recipients']) . ' recipients', 'cyan');
    }

    private function executeWaitStep(array $payload)
    {
        // Implementation stub - will be fleshed out in Phase 9
        CLI::write('  Executing wait step for automation ' . $payload['automation_id'], 'cyan');
    }

    private function sendDailyReport(array $payload)
    {
        // Implementation stub - will be fleshed out in Phase 11
        CLI::write('  Sending daily report', 'cyan');
    }
}

Create app/Commands/RunScheduled.php:

<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RunScheduled extends BaseCommand
{
    protected $group = 'Cron';
    protected $name = 'run:scheduled';
    protected $description = 'Run all scheduled tasks (called by cron every minute)';

    public function run(array $params)
    {
        CLI::write('Running scheduled tasks...', 'yellow');

        // Process queue
        command('queue:process');

        // Check if it's 8 AM for daily report
        $hour = (int)date('H');
        if ($hour === 8) {
            CLI::write('Dispatching daily report...', 'blue');
            
            $dispatcher = new \App\Libraries\JobDispatcher();
            $dispatcher->dispatch('send_daily_report', [], null, 10); // Highest priority
        }

        // Run media cleanup at 2 AM
        if ($hour === 2) {
            CLI::write('Running media cleanup...', 'blue');
            command('media:cleanup');
        }

        CLI::write('Scheduled tasks complete', 'green');
    }
}

Create app/Controllers/Api/SendController.php:

<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\WhatsAppConfigModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class SendController extends BaseController
{
    /**
     * POST /api/whatsapp/send
     */
    public function send()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $contentType = $this->request->getPost('content_type') ?? 'text';
        $contentText = $this->request->getPost('content_text');
        $replyToMessageId = $this->request->getPost('reply_to_message_id');

        // Validate
        if (empty($conversationId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'conversation_id is required'
            ]);
        }

        if (empty($contentText) && $contentType === 'text') {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'content_text is required for text messages'
            ]);
        }

        // Get conversation
        $conversationModel = new ConversationModel();
        $conversation = $conversationModel->find($conversationId);

        if (!$conversation) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Conversation not found'
            ]);
        }

        // Get WhatsApp config for current account
        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('account_id', session('account_id'))->first();

        if (!$waConfig || $waConfig['status'] !== 'connected') {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'WhatsApp not connected'
            ]);
        }

        // Decrypt access token
        $encryption = new Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        // Get contact phone
        $contactModel = new \App\Models\ContactModel();
        $contact = $contactModel->find($conversation['contact_id']);

        if (!$contact) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Contact not found'
            ]);
        }

        // Create message record first
        $messageModel = new MessageModel();
        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id' => session('account_id'),
            'sender_type' => 'agent',
            'content_type' => $contentType,
            'content_text' => $contentText,
            'status' => 'sending',
            'reply_to_message_id' => $replyToMessageId
        ]);

        try {
            // Send via Meta API
            $metaApi = new MetaApi();
            
            $response = $metaApi->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $contentText,
                $replyToMessageId
            );

            // Update message with WhatsApp message ID
            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'],
                'status' => 'sent'
            ]);

            // Update conversation
            $conversationModel->update($conversationId, [
                'last_message_text' => substr($contentText, 0, 200),
                'last_message_at' => date('Y-m-d H:i:s')
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message_id' => $messageId,
                'whatsapp_message_id' => $response['messages'][0]['id']
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            $messageModel->update($messageId, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to send message: ' . $e->getMessage()
            ]);
        }
    }
}

Create app/Controllers/Api/ReactController.php:

<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class ReactController extends BaseController
{
    /**
     * POST /api/whatsapp/react
     */
    public function react()
    {
        $messageId = $this->request->getPost('message_id');
        $emoji = $this->request->getPost('emoji');

        // Validate
        if (empty($messageId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'message_id is required'
            ]);
        }

        // Get message
        $messageModel = new MessageModel();
        $message = $messageModel->find($messageId);

        if (!$message || empty($message['whatsapp_message_id'])) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Message not found'
            ]);
        }

        // Get WhatsApp config
        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('account_id', session('account_id'))->first();

        if (!$waConfig) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'WhatsApp not connected'
            ]);
        }

        // Decrypt access token
        $encryption = new Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        try {
            // Send reaction via Meta API
            $metaApi = new MetaApi();
            $metaApi->sendReaction(
                $waConfig['phone_number_id'],
                $accessToken,
                $message['whatsapp_message_id'],
                $emoji
            );

            return $this->response->setJSON([
                'success' => true
            ]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to send reaction: ' . $e->getMessage()
            ]);
        }
    }
}

Update app/Config/Routes.php:

// Webhook routes (no auth, but signature verified)
$routes->get('api/whatsapp/webhook', 'Api\WebhookController::verify');
$routes->post('api/whatsapp/webhook', 'Api\WebhookController::handle', ['filter' => 'webhook_signature']);

// Send message (requires auth)
$routes->post('api/whatsapp/send', 'Api\SendController::send', ['filter' => 'auth']);
$routes->post('api/whatsapp/react', 'Api\ReactController::react', ['filter' => 'auth']);

Register WebhookSignatureFilter in app/Config/Filters.php:

public $aliases = [
    // ... existing filters
    'webhook_signature' => \App\Filters\WebhookSignatureFilter::class,
];
```

### Testing Phase 3

Test checklist:

```bash
# 1. Verify encryption works
php spark shell
> $enc = new \App\Libraries\WhatsApp\Encryption();
> $encrypted = $enc->encrypt('test secret');
> echo $encrypted;
> $decrypted = $enc->decrypt($encrypted);
> echo $decrypted; // Should output "test secret"

# 2. Test webhook verification (GET)
curl "http://localhost:8080/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=YOUR_VERIFY_TOKEN&hub.challenge=test123"

# Expected: Returns "test123" if token matches

# 3. Test webhook signature (POST)
# In Meta Developer Console:
# - Set webhook URL: https://yourdomain.com/api/whatsapp/webhook
# - Set verify token (matches .env whatsapp.verifyToken)
# - Subscribe to: messages, message_status
# - Send test message from WhatsApp

# 4. Test job queue
php spark queue:process

# Expected: "No pending jobs" or processes existing jobs

# 5. Dispatch a test job
php spark shell
> $dispatcher = new \App\Libraries\JobDispatcher();
> $jobId = $dispatcher->dispatch('send_message', ['to' => '919876543210', 'text' => 'Test'], null, 5);
> echo "Job ID: " . $jobId;

# 6. Process the job
php spark queue:process

# Expected: Job processes, status changes to 'done'

# 7. Test send endpoint (via Postman or curl)
curl -X POST http://localhost:8080/api/whatsapp/send \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "conversation_id=xxx&content_type=text&content_text=Hello from PHP"

# Expected: Message sent via WhatsApp, returns message_id

# 8. Check cron command
php spark run:scheduled

# Expected: Runs queue:process, checks for daily report time
```

**Pass Criteria:**
- ✅ Encryption/decryption works (AES-256-GCM)
- ✅ Webhook verification passes (GET request)
- ✅ Webhook signature verification works (rejects invalid signatures)
- ✅ Inbound messages create contact, conversation, message records
- ✅ Media downloads from Meta and saves to local storage
- ✅ Status updates modify message status
- ✅ Job queue processes jobs in priority order
- ✅ Failed jobs retry with exponential backoff
- ✅ Jobs hitting max_retries move to 'failed' status
- ✅ Send endpoint sends WhatsApp message successfully
- ✅ Tenant isolation maintained (BaseModel::setBypassAccountScope)

**Common Issues:**
- Webhook returns 403: Check X-Hub-Signature-256 header matches, check META_APP_SECRET configured
- Messages not inserting: Check BaseModel::setBypassAccountScope(true) is called in webhook
- Encryption fails: Check rovix.encryptionKey is 64 hex chars (32 bytes)
- Media download fails: Check writable/uploads/chat-media/ exists and is writable (755)
- Job queue stuck: Check locked_until is in the past, or run unlock query manually
- Send fails: Check WhatsApp config exists, access_token decrypts correctly, phone_number_id valid

---
