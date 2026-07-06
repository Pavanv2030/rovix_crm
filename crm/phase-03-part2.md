### Prompt 3.3 — Webhook Controller

```
Port the WhatsApp webhook handler for Rovix AI Leads Tool. This is the most critical file — it processes ALL inbound WhatsApp events.

Reference: src/app/api/whatsapp/webhook/route.ts (969 lines)

Create app/Controllers/Api/WebhookController.php:

<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\WhatsAppConfigModel;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\MessageReactionModel;
use App\Models\MessageTemplateModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\PhoneUtils;
use App\Libraries\JobDispatcher;
use App\Models\BaseModel;

class WebhookController extends BaseController
{
    /**
     * GET /api/whatsapp/webhook - Meta webhook verification
     */
    public function verify()
    {
        $hubMode = $this->request->getGet('hub.mode');
        $hubToken = $this->request->getGet('hub.verify_token');
        $hubChallenge = $this->request->getGet('hub.challenge');

        $config = config('WhatsApp');
        $verifyToken = $config->verifyToken;

        if ($hubMode === 'subscribe' && $hubToken === $verifyToken) {
            log_message('info', 'Webhook verified successfully');
            return $this->response->setBody($hubChallenge);
        }

        log_message('error', 'Webhook verification failed');
        return $this->response->setStatusCode(403)->setJSON(['error' => 'Verification failed']);
    }

    /**
     * POST /api/whatsapp/webhook - Handle inbound events
     */
    public function handle()
    {
        // Bypass tenant scoping for webhook processing
        BaseModel::setBypassAccountScope(true);

        $body = $this->request->getJSON(true);

        if (empty($body['entry'])) {
            return $this->response->setStatusCode(200)->setJSON(['status' => 'ok']);
        }

        foreach ($body['entry'] as $entry) {
            foreach ($entry['changes'] as $change) {
                $value = $change['value'];

                // Process inbound messages
                if (isset($value['messages'])) {
                    foreach ($value['messages'] as $message) {
                        try {
                            $this->processInboundMessage(
                                $value['metadata']['phone_number_id'],
                                $message,
                                $value['contacts'][0] ?? null
                            );
                        } catch (\Exception $e) {
                            log_message('error', 'Error processing message: ' . $e->getMessage());
                        }
                    }
                }

                // Process status updates
                if (isset($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        try {
                            $this->processStatusUpdate($status);
                        } catch (\Exception $e) {
                            log_message('error', 'Error processing status: ' . $e->getMessage());
                        }
                    }
                }

                // Process template status updates
                if (isset($value['message_template_status_update'])) {
                    try {
                        $this->processTemplateStatus($value['message_template_status_update']);
                    } catch (\Exception $e) {
                        log_message('error', 'Error processing template status: ' . $e->getMessage());
                    }
                }
            }
        }

        return $this->response->setStatusCode(200)->setJSON(['status' => 'ok']);
    }

    private function processInboundMessage(string $phoneNumberId, array $message, ?array $contactInfo)
    {
        // Find WhatsApp config to get account_id
        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('phone_number_id', $phoneNumberId)->first();

        if (!$waConfig) {
            log_message('error', "No WhatsApp config found for phone_number_id: {$phoneNumberId}");
            return;
        }

        $accountId = $waConfig['account_id'];
        $from = $message['from'];
        $messageType = $message['type'];
        $waMessageId = $message['id'];

        // Find or create contact
        $contactModel = new ContactModel();
        $phoneNormalized = PhoneUtils::normalize($from);
        
        $contact = $contactModel
            ->where('account_id', $accountId)
            ->where('phone_normalized', $phoneNormalized)
            ->first();

        if (!$contact) {
            $contactName = $contactInfo['profile']['name'] ?? $from;
            $contactId = $contactModel->insert([
                'account_id' => $accountId,
                'phone' => $from,
                'phone_normalized' => $phoneNormalized,
                'name' => $contactName
            ]);
            $contact = $contactModel->find($contactId);
        }

        // Find or create conversation
        $conversationModel = new ConversationModel();
        $conversation = $conversationModel
            ->where('account_id', $accountId)
            ->where('contact_id', $contact['id'])
            ->first();

        if (!$conversation) {
            $conversationId = $conversationModel->insert([
                'account_id' => $accountId,
                'contact_id' => $contact['id'],
                'status' => 'open'
            ]);
            $conversation = $conversationModel->find($conversationId);
        }

        // Handle different message types
        if ($messageType === 'reaction') {
            $this->handleReaction($accountId, $conversation['id'], $message['reaction']);
            return;
        }

        // Extract message content
        $contentText = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaFilename = null;

        switch ($messageType) {
            case 'text':
                $contentText = $message['text']['body'];
                break;

            case 'image':
                $mediaId = $message['image']['id'];
                $contentText = $message['image']['caption'] ?? null;
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($mediaId, $waConfig, $accountId);
                break;

            case 'video':
                $mediaId = $message['video']['id'];
                $contentText = $message['video']['caption'] ?? null;
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($mediaId, $waConfig, $accountId);
                break;

            case 'document':
                $mediaId = $message['document']['id'];
                $contentText = $message['document']['caption'] ?? null;
                $mediaFilename = $message['document']['filename'] ?? 'document';
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($mediaId, $waConfig, $accountId);
                break;

            case 'audio':
                $mediaId = $message['audio']['id'];
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($mediaId, $waConfig, $accountId);
                break;

            case 'sticker':
                $mediaId = $message['sticker']['id'];
                [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta($mediaId, $waConfig, $accountId);
                break;

            case 'location':
                $loc = $message['location'];
                $contentText = "Location: {$loc['latitude']}, {$loc['longitude']}";
                break;

            case 'interactive':
                $contentText = $this->extractInteractiveResponse($message['interactive']);
                break;

            default:
                $contentText = "Unsupported message type: {$messageType}";
        }

        // Insert message
        $messageModel = new MessageModel();
        $messageModel->insert([
            'conversation_id' => $conversation['id'],
            'account_id' => $accountId,
            'sender_type' => 'customer',
            'content_type' => $messageType,
            'content_text' => $contentText,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_filename' => $mediaFilename,
            'status' => 'received',
            'whatsapp_message_id' => $waMessageId,
            'reply_to_message_id' => $message['context']['id'] ?? null
        ]);

        // Update conversation
        $conversationModel->update($conversation['id'], [
            'last_message_text' => $contentText ? substr($contentText, 0, 200) : '[' . ucfirst($messageType) . ']',
            'last_message_at' => date('Y-m-d H:i:s'),
            'unread_count' => $conversation['unread_count'] + 1,
            'status' => 'open' // Reopen if closed
        ]);

        // Dispatch background jobs
        $dispatcher = new JobDispatcher();

        // Check for automations
        $dispatcher->dispatch('run_automation', [
            'account_id' => $accountId,
            'contact_id' => $contact['id'],
            'conversation_id' => $conversation['id'],
            'message' => $message,
            'trigger_type' => 'new_message_received'
        ], null, 5); // Priority 5 for automations

        // Check for flows
        $dispatcher->dispatch('check_flow', [
            'account_id' => $accountId,
            'contact_id' => $contact['id'],
            'conversation_id' => $conversation['id'],
            'message_text' => $contentText
        ], null, 5);
    }

    private function handleReaction(string $accountId, string $conversationId, array $reaction)
    {
        $reactionModel = new MessageReactionModel();

        // Find the message being reacted to
        $messageModel = new MessageModel();
        $message = $messageModel->where('whatsapp_message_id', $reaction['message_id'])->first();

        if (!$message) {
            log_message('warning', 'Message not found for reaction: ' . $reaction['message_id']);
            return;
        }

        if (empty($reaction['emoji'])) {
            // Remove reaction
            $reactionModel->where('message_id', $message['id'])
                ->where('actor_type', 'customer')
                ->delete();
        } else {
            // Add or update reaction
            $reactionModel->insert([
                'message_id' => $message['id'],
                'conversation_id' => $conversationId,
                'actor_type' => 'customer',
                'emoji' => $reaction['emoji']
            ]);
        }
    }

    private function downloadMediaFromMeta(string $mediaId, array $waConfig, string $accountId): array
    {
        $encryption = new \App\Libraries\WhatsApp\Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        $metaApi = new MetaApi();
        
        // Get media URL
        $mediaUrl = $metaApi->getMediaUrl($mediaId, $accessToken);
        
        // Download media
        $localPath = $metaApi->downloadMedia($mediaUrl, $accessToken);
        
        // Save to media_files table for tracking
        $mediaFileModel = new \App\Models\MediaFileModel();
        $mediaFileModel->insert([
            'account_id' => $accountId,
            'file_path' => $localPath,
            'mime_type' => 'unknown', // Will be detected from file
            'file_size' => filesize(WRITEPATH . 'uploads/' . $localPath),
            'media_type' => $this->guessMediaType($localPath)
        ]);

        return [$localPath, 'unknown', basename($localPath)];
    }

    private function guessMediaType(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'image';
        if (in_array($ext, ['mp4', 'mov', 'avi'])) return 'video';
        if (in_array($ext, ['mp3', 'ogg', 'wav', 'aac'])) return 'audio';
        return 'document';
    }

    private function extractInteractiveResponse(array $interactive): string
    {
        if (isset($interactive['button_reply'])) {
            return $interactive['button_reply']['title'];
        }
        
        if (isset($interactive['list_reply'])) {
            return $interactive['list_reply']['title'];
        }

        return 'Interactive response';
    }

    private function processStatusUpdate(array $status)
    {
        $waMessageId = $status['id'];
        $newStatus = $status['status']; // sent, delivered, read, failed

        $messageModel = new MessageModel();
        $message = $messageModel->where('whatsapp_message_id', $waMessageId)->first();

        if (!$message) {
            return; // Message not in our DB yet (race condition)
        }

        // Update message status
        $updateData = ['status' => $newStatus];
        
        if ($newStatus === 'failed' && isset($status['errors'])) {
            $updateData['error_message'] = json_encode($status['errors']);
        }

        $messageModel->update($message['id'], $updateData);

        // Update broadcast recipient if this is a broadcast message
        $broadcastRecipientModel = new \App\Models\BroadcastRecipientModel();
        $recipient = $broadcastRecipientModel->where('whatsapp_message_id', $waMessageId)->first();

        if ($recipient) {
            $broadcastRecipientModel->update($recipient['id'], ['status' => $newStatus]);

            // Update broadcast counts
            $broadcastModel = new \App\Models\BroadcastModel();
            $broadcast = $broadcastModel->find($recipient['broadcast_id']);

            if ($broadcast) {
                $countField = match($newStatus) {
                    'sent' => 'sent_count',
                    'delivered' => 'delivered_count',
                    'read' => 'read_count',
                    'failed' => 'failed_count',
                    default => null
                };

                if ($countField) {
                    $broadcastModel->update($broadcast['id'], [
                        $countField => $broadcast[$countField] + 1
                    ]);
                }
            }
        }
    }

    private function processTemplateStatus(array $event)
    {
        $metaTemplateId = $event['message_template_id'] ?? null;
        $newStatus = $event['event']; // approved, rejected, paused, disabled

        if (!$metaTemplateId) return;

        $templateModel = new MessageTemplateModel();
        $template = $templateModel->where('meta_template_id', $metaTemplateId)->first();

        if ($template) {
            $templateModel->update($template['id'], ['status' => $newStatus]);
        }
    }
}
```

### Prompt 3.4 — Job Queue System with Improvements

```
Build the enhanced background job queue system for Rovix AI Leads Tool.

This replaces Node.js in-process async with a MySQL-based queue processed by cron.

IMPROVEMENTS from original plan:
- Priority queue (higher priority = processed first)
- Lock mechanism (prevents concurrent processing of same job)
- Dead letter queue (failed_attempts_log for debugging)

Create app/Libraries/JobDispatcher.php:

<?php
namespace App\Libraries;

use App\Models\JobQueueModel;

class JobDispatcher
{
    /**
     * Dispatch a job to the queue
     * 
     * @param string $jobType Job type identifier
     * @param array $payload Job data
     * @param string|null $runAfter Delay execution (datetime string or null for immediate)
     * @param int $priority Higher = more urgent (0-10, default 0)
     * @return int Job ID
     */
    public function dispatch(
        string $jobType,
        array $payload,
        ?string $runAfter = null,
        int $priority = 0
    ): int {
        $model = new JobQueueModel();
        
        return $model->insert([
            'job_type' => $jobType,
            'payload' => json_encode($payload),
            'status' => 'pending',
            'priority' => max(0, min(10, $priority)), // Clamp 0-10
            'run_after' => $runAfter,
            'attempts' => 0,
            'max_retries' => 3
        ]);
    }
}
```

Continuing in next file...
