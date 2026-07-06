<?php

namespace App\Libraries;

use App\Models\AutomationModel;
use App\Models\AutomationStepModel;
use App\Models\AutomationLogModel;
use App\Models\ContactModel;
use App\Models\ContactTagModel;
use App\Models\ConversationModel;
use App\Models\AppointmentTypeModel;
use App\Models\WhatsAppConfigModel;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\SessionWindow;

class AutomationEngine
{
    private AutomationModel $automationModel;
    private AutomationStepModel $stepModel;
    private AutomationLogModel $logModel;

    public function __construct()
    {
        $this->automationModel = new AutomationModel();
        $this->stepModel       = new AutomationStepModel();
        $this->logModel        = new AutomationLogModel();
    }

    /**
     * Find and execute all active automations matching the trigger for an account.
     * Call this from webhook handlers, contact creation, tag events, etc.
     */
    public function fire(string $triggerType, array $triggerData, string $contactId, string $accountId): void
    {
        AutomationModel::setBypassAccountScope(true);
        $query = $this->automationModel
            ->where('account_id', $accountId)
            ->where('is_active', 1);

        if ($triggerType === 'new_message_received') {
            $query->groupStart()
                ->where('trigger_type', 'new_message_received')
                ->orWhere('trigger_type', 'first_inbound_message')
                ->orWhere('trigger_type', 'keyword_match')
            ->groupEnd();
        } else {
            $query->where('trigger_type', $triggerType);
        }

        $automations = $query->findAll();
        AutomationModel::setBypassAccountScope(false);

        foreach ($automations as $automation) {
            if ($this->matchesTrigger($automation, $automation['trigger_type'], $triggerData)) {
                $this->execute($automation, $contactId, $triggerData);
            }
        }
    }

    /**
     * Resume a previously paused automation after a wait step completes.
     */
    public function resumeFrom(string $automationId, string $contactId, string $waitStepId): void
    {
        AutomationModel::setBypassAccountScope(true);
        $automation = $this->automationModel->find($automationId);
        AutomationModel::setBypassAccountScope(false);

        if (!$automation || !$automation['is_active']) return;

        $this->execute($automation, $contactId, [], $waitStepId);
    }

    /**
     * Execute an automation for a single contact.
     * $fromStepId: when resuming after a wait, pass the wait step's ID so
     * execution continues from that step's children.
     */
    public function execute(array $automation, string $contactId, array $triggerData = [], ?string $fromStepId = null): void
    {
        $logId = $this->logModel->insert([
            'automation_id'  => $automation['id'],
            'contact_id'     => $contactId,
            'trigger_event'  => json_encode($triggerData),
            'steps_executed' => json_encode([]),
            'status'         => 'running',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        try {
            $contact = (new ContactModel())->find($contactId);
            if (!$contact) throw new \Exception("Contact not found: {$contactId}");

            $allSteps = $this->stepModel
                ->where('automation_id', $automation['id'])
                ->orderBy('position', 'ASC')
                ->findAll();

            // Build parent → children map
            $byParent = [];
            foreach ($allSteps as $step) {
                $key = $step['parent_step_id'] ?? '__root__';
                $byParent[$key][] = $step;
            }

            $executed = [];
            $context  = ['contact' => &$contact, 'trigger' => $triggerData];

            $startKey = $fromStepId ?? '__root__';
            $this->runChain($byParent, $startKey, $context, $executed, $automation);

            $this->logModel->update($logId, [
                'steps_executed' => json_encode($executed),
                'status'         => 'completed',
            ]);

            $this->automationModel->update($automation['id'], [
                'execution_count'  => (int)$automation['execution_count'] + 1,
                'last_executed_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            $this->logModel->update($logId, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            log_message('error', '[AutomationEngine] execute failed: ' . $e->getMessage());
        }
    }

    // ─── Private: chain traversal ───────────────────────────────────────────

    private function runChain(array $byParent, string $parentKey, array &$context, array &$executed, array $automation): bool
    {
        if (empty($byParent[$parentKey])) return true;

        $steps = $byParent[$parentKey];
        usort($steps, fn($a, $b) => $a['position'] <=> $b['position']);

        foreach ($steps as $step) {
            $result = $this->runStep($step, $context, $automation);

            $executed[] = [
                'step_id' => $step['id'],
                'type'    => $step['step_type'],
                'outcome' => $result['outcome'],
            ];

            if ($result['outcome'] === 'wait') {
                // Job queued — stop processing; will resume via resume_automation job
                return false;
            }

            if ($step['step_type'] === 'condition') {
                if ($result['branch'] === 'no') {
                    // Condition failed — stop this branch
                    return true;
                }
                // Condition passed — continue to children
            }

            // Recurse into children of this step
            $continued = $this->runChain($byParent, $step['id'], $context, $executed, $automation);
            if (!$continued) return false;
        }

        return true;
    }

    private function runStep(array $step, array &$context, array $automation): array
    {
        $config  = json_decode($step['step_config'] ?? '{}', true) ?? [];
        $contact = &$context['contact'];

        return match ($step['step_type']) {
            'send_message'         => $this->doSendMessage($config, $contact, $automation),
            'send_template'        => $this->doSendTemplate($config, $contact, $automation),
            'add_tag'              => $this->doAddTag($config, $contact),
            'remove_tag'           => $this->doRemoveTag($config, $contact),
            'assign_conversation'  => $this->doAssign($config, $contact, $automation),
            'update_contact_field' => $this->doUpdateField($config, $contact),
            'create_deal'          => $this->doCreateDeal($config, $contact, $automation),
            'wait'                 => $this->doWait($config, $contact, $step, $automation),
            'condition'            => $this->doCondition($config, $contact),
            'send_webhook'         => $this->doWebhook($config, $contact),
            'close_conversation'   => $this->doCloseConversation($contact, $automation),
            'send_appointment_flow' => $this->doSendAppointmentFlow($config, $contact, $automation),
            'send_catalog'          => $this->doSendCatalog($config, $contact, $automation),
            default                => ['outcome' => 'unknown_step'],
        };
    }

    // ─── Step handlers ───────────────────────────────────────────────────────

    private function doSendMessage(array $config, array $contact, array $automation): array
    {
        $convo = $this->latestConversation($contact['id'], $automation['account_id']);
        if (!$convo) return ['outcome' => 'skipped_no_conversation'];

        (new JobDispatcher())->dispatch('send_whatsapp_message', [
            'conversation_id' => $convo['id'],
            'account_id'      => $automation['account_id'],
            'message_type'    => 'text',
            'content'         => $this->interpolate($config['message'] ?? '', $contact),
        ], null, 5);

        return ['outcome' => 'queued'];
    }

    private function doSendTemplate(array $config, array $contact, array $automation): array
    {
        $convo = $this->latestConversation($contact['id'], $automation['account_id']);
        if (!$convo) return ['outcome' => 'skipped_no_conversation'];

        $variables = [];
        foreach ($config['variables'] ?? [] as $field) {
            $variables[] = $this->getField($contact, $field);
        }

        (new JobDispatcher())->dispatch('send_whatsapp_template', [
            'conversation_id'   => $convo['id'],
            'account_id'        => $automation['account_id'],
            'template_name'     => $config['template_name'] ?? '',
            'template_language' => $config['template_language'] ?? 'en',
            'variables'         => $variables,
        ], null, 5);

        return ['outcome' => 'queued'];
    }

    /**
     * Sends the native WhatsApp Flow booking message for a given appointment
     * type — same mechanism as the manual 📅 send in the inbox
     * (Api\AppointmentsController::sendFlow), just triggered by an
     * automation step instead of an agent click.
     */
    private function doSendAppointmentFlow(array $config, array $contact, array $automation): array
    {
        $typeId = $config['appointment_type_id'] ?? null;
        if (!$typeId) return ['outcome' => 'skipped_no_type'];

        $convo = $this->latestConversation($contact['id'], $automation['account_id']);
        if (!$convo) return ['outcome' => 'skipped_no_conversation'];

        $type = (new AppointmentTypeModel())->find($typeId);
        if (!$type) return ['outcome' => 'skipped_type_not_found'];

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $automation['account_id'])->first();
        if (!$waConfig) return ['outcome' => 'skipped_whatsapp_not_connected'];

        $flowRow = \Config\Database::connect()
            ->table('whatsapp_flows')
            ->where('appointment_type_id', $typeId)
            ->where('status', 'published')
            ->get()->getRowArray();
        if (!$flowRow) return ['outcome' => 'skipped_no_published_flow'];

        $flowToken   = uniqid('apt_', true);
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        \Config\Database::connect()->table('flow_token_map')->insert([
            'flow_token'          => $flowToken,
            'account_id'          => $automation['account_id'],
            'appointment_type_id' => $typeId,
            'contact_id'          => $contact['id'],
            'conversation_id'     => $convo['id'],
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $bodyText   = ($config['body_text'] ?? '')   ?: 'Please choose a date & time for your appointment.';
        $buttonText = ($config['button_text'] ?? '') ?: 'Available Date & Time';

        try {
            $response = (new MetaApi())->sendFlowMessage(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $bodyText,
                $buttonText,
                $flowRow['flow_id'],
                $flowToken,
                [
                    'min_date' => date('Y-m-d', strtotime('+1 day')),
                    'max_date' => date('Y-m-d', strtotime('+' . ($type['max_days_ahead'] ?? 60) . ' days')),
                ]
            );
            $this->logToInbox(
                $convo['id'],
                $automation['account_id'],
                'flow',
                json_encode(['body' => $bodyText, 'button' => $buttonText]),
                $response['messages'][0]['id'] ?? null
            );
            return ['outcome' => 'sent'];
        } catch (\Exception $e) {
            return ['outcome' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Sends the connected WhatsApp catalog — same mechanism as the manual
     * 🛒 send in the inbox (Api\CatalogController::sendCatalog), including
     * the auto-retry-without-thumbnail fallback for products not yet
     * indexed on Meta's WhatsApp-side catalog graph (error 131009).
     */
    private function doSendCatalog(array $config, array $contact, array $automation): array
    {
        $convo = $this->latestConversation($contact['id'], $automation['account_id']);
        if (!$convo) return ['outcome' => 'skipped_no_conversation'];

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $automation['account_id'])->first();
        if (!$waConfig) return ['outcome' => 'skipped_whatsapp_not_connected'];
        if (empty($waConfig['catalog_id'])) return ['outcome' => 'skipped_no_catalog_connected'];

        if (!SessionWindow::isOpen($convo['last_customer_message_at'] ?? null)) {
            return ['outcome' => 'skipped_session_window_closed'];
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
        $bodyText    = ($config['body_text'] ?? '')   ?: 'Browse our products';
        $footerText  = $config['footer_text'] ?? null;

        $thumbnailRetailerId = null;
        if (!empty($waConfig['catalog_products'])) {
            $cachedProducts      = json_decode($waConfig['catalog_products'], true) ?? [];
            $thumbnailRetailerId = $cachedProducts[0]['retailer_id'] ?? null;
        }

        $metaApi = new MetaApi();
        try {
            try {
                $response = $metaApi->sendCatalogMessage(
                    $waConfig['phone_number_id'],
                    $accessToken,
                    $contact['phone_normalized'],
                    $bodyText,
                    $footerText,
                    $thumbnailRetailerId
                );
            } catch (\Exception $e) {
                if ($thumbnailRetailerId && str_contains($e->getMessage(), '131009')) {
                    $response = $metaApi->sendCatalogMessage(
                        $waConfig['phone_number_id'],
                        $accessToken,
                        $contact['phone_normalized'],
                        $bodyText,
                        $footerText,
                        null
                    );
                } else {
                    throw $e;
                }
            }
            $this->logToInbox($convo['id'], $automation['account_id'], 'catalog', $bodyText, $response['messages'][0]['id'] ?? null);
            return ['outcome' => 'sent'];
        } catch (\Exception $e) {
            return ['outcome' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Automation-triggered sends (appointment flow, catalog) previously
     * called MetaApi directly and never touched the messages table —
     * invisible in the inbox even though the customer really received them.
     */
    private function logToInbox(string $conversationId, string $accountId, string $contentType, string $contentText, ?string $waMessageId): void
    {
        (new \App\Models\MessageModel())->insert([
            'conversation_id'     => $conversationId,
            'account_id'          => $accountId,
            'sender_type'         => 'bot',
            'content_type'        => $contentType,
            'content_text'        => $contentText,
            'status'              => 'sent',
            'whatsapp_message_id' => $waMessageId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $preview = $contentType === 'flow' ? 'Appointment booking sent' : ('Catalog: ' . mb_strimwidth($contentText, 0, 60, '...'));

        (new ConversationModel())->update($conversationId, [
            'last_message_text' => $preview,
            'last_message_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function doAddTag(array $config, array $contact): array
    {
        $tagId = $config['tag_id'] ?? null;
        if (!$tagId) return ['outcome' => 'skipped_no_tag'];

        $exists = (new ContactTagModel())->where('contact_id', $contact['id'])->where('tag_id', $tagId)->first();
        if (!$exists) {
            (new ContactTagModel())->insert(['contact_id' => $contact['id'], 'tag_id' => $tagId]);
        }
        return ['outcome' => 'done'];
    }

    private function doRemoveTag(array $config, array $contact): array
    {
        $tagId = $config['tag_id'] ?? null;
        if (!$tagId) return ['outcome' => 'skipped_no_tag'];

        (new ContactTagModel())->where('contact_id', $contact['id'])->where('tag_id', $tagId)->delete();
        return ['outcome' => 'done'];
    }

    private function doAssign(array $config, array $contact, array $automation): array
    {
        $userId = $config['user_id'] ?? null;
        $db     = \Config\Database::connect();
        $db->table('conversations')
            ->where('contact_id', $contact['id'])
            ->where('account_id', $automation['account_id'])
            ->set(['assigned_to' => $userId])
            ->update();
        return ['outcome' => 'done'];
    }

    private function doUpdateField(array $config, array $contact): array
    {
        $field   = $config['field'] ?? null;
        $value   = $config['value'] ?? '';
        $allowed = ['name', 'email', 'company', 'phone', 'notes'];

        if (!$field || !in_array($field, $allowed, true)) return ['outcome' => 'skipped_bad_field'];

        (new ContactModel())->update($contact['id'], [$field => $value]);
        $contact[$field] = $value; // update in-memory for downstream steps
        return ['outcome' => 'done'];
    }

    private function doCreateDeal(array $config, array $contact, array $automation): array
    {
        $stageId = $config['stage_id'] ?? null;
        if (!$stageId) return ['outcome' => 'skipped_no_stage'];

        (new \App\Models\DealModel())->insert([
            'account_id' => $automation['account_id'],
            'contact_id' => $contact['id'],
            'stage_id'   => $stageId,
            'name'       => $this->interpolate($config['name'] ?? 'Deal — ' . $contact['name'], $contact),
            'value'      => (float)($config['value'] ?? 0),
            'status'     => 'open',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['outcome' => 'done'];
    }

    private function doWait(array $config, array $contact, array $step, array $automation): array
    {
        $amount  = max(1, (int)($config['amount'] ?? 1));
        $unit    = $config['unit'] ?? 'hours';
        $seconds = match ($unit) {
            'minutes' => $amount * 60,
            'days'    => $amount * 86400,
            default   => $amount * 3600,
        };

        (new JobDispatcher())->dispatch('resume_automation', [
            'automation_id' => $automation['id'],
            'contact_id'    => $contact['id'],
            'from_step_id'  => $step['id'],
        ], date('Y-m-d H:i:s', time() + $seconds), 5);

        return ['outcome' => 'wait'];
    }

    private function doCondition(array $config, array $contact): array
    {
        $field    = $config['field'] ?? 'name';
        $operator = $config['operator'] ?? 'not_empty';
        $value    = (string)($config['value'] ?? '');
        $actual   = (string)$this->getField($contact, $field);

        $pass = match ($operator) {
            'equals'     => $actual === $value,
            'not_equals' => $actual !== $value,
            'contains'   => str_contains(strtolower($actual), strtolower($value)),
            'not_empty'  => $actual !== '',
            'empty'      => $actual === '',
            default      => false,
        };

        return ['outcome' => 'condition', 'branch' => $pass ? 'yes' : 'no'];
    }

    private function doWebhook(array $config, array $contact): array
    {
        $url = $config['url'] ?? '';
        if (!filter_var($url, FILTER_VALIDATE_URL)) return ['outcome' => 'skipped_invalid_url'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'contact'   => ['id' => $contact['id'], 'name' => $contact['name'], 'phone' => $contact['phone'], 'email' => $contact['email'] ?? ''],
                'timestamp' => date('c'),
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_exec($ch);
        curl_close($ch);

        return ['outcome' => ($code >= 200 && $code < 300) ? 'done' : 'webhook_error', 'http_code' => $code];
    }

    private function doCloseConversation(array $contact, array $automation): array
    {
        $db = \Config\Database::connect();
        $db->table('conversations')
            ->where('contact_id', $contact['id'])
            ->where('account_id', $automation['account_id'])
            ->where('status', 'open')
            ->set(['status' => 'closed'])
            ->update();
        return ['outcome' => 'done'];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function latestConversation(string $contactId, string $accountId): ?array
    {
        return (new ConversationModel())
            ->where('contact_id', $contactId)
            ->where('account_id', $accountId)
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    private function matchesTrigger(array $automation, string $triggerType, array $triggerData): bool
    {
        $config = json_decode($automation['trigger_config'] ?? '{}', true) ?? [];

        if ($triggerType === 'first_inbound_message') {
            $convoId = $triggerData['conversation_id'] ?? null;
            if (!$convoId) {
                $convo = $this->latestConversation($triggerData['contact_id'] ?? '', $automation['account_id']);
                $convoId = $convo['id'] ?? null;
            }
            if ($convoId) {
                $db = \Config\Database::connect();
                $count = $db->table('messages')
                    ->where('conversation_id', $convoId)
                    ->where('sender_type', 'customer')
                    ->countAllResults();
                return $count === 1;
            }
            return false;
        }

        if ($triggerType === 'keyword_match') {
            $keywords = array_filter(array_map('trim', explode(',', $config['keywords'] ?? '')));
            $message  = strtolower($triggerData['message_text'] ?? $triggerData['message']['text']['body'] ?? '');
            if (empty($keywords)) return false;
            $matchAll = !empty($config['match_all']);
            foreach ($keywords as $kw) {
                $found = str_contains($message, strtolower($kw));
                if ($matchAll && !$found) return false;
                if (!$matchAll && $found) return true;
            }
            return $matchAll;
        }

        if ($triggerType === 'tag_added') {
            $targetTag = $config['tag_id'] ?? null;
            return !$targetTag || ($triggerData['tag_id'] ?? null) === $targetTag;
        }

        return true;
    }

    private function interpolate(string $text, array $contact): string
    {
        return str_replace(
            ['{{name}}', '{{phone}}', '{{email}}', '{{company}}'],
            [$contact['name'] ?? '', $contact['phone'] ?? '', $contact['email'] ?? '', $contact['company'] ?? ''],
            $text
        );
    }

    private function getField(array $contact, string $field): mixed
    {
        return $contact[$field] ?? '';
    }
}
