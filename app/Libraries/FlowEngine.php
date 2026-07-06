<?php

namespace App\Libraries;

use App\Models\FlowModel;
use App\Models\FlowNodeModel;
use App\Models\FlowRunModel;
use App\Models\FlowRunEventModel;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\ContactTagModel;
use App\Models\CustomFieldModel;
use App\Models\ContactCustomValueModel;
use App\Models\MessageModel;
use App\Models\AiConfigModel;
use App\Models\WhatsAppConfigModel;
use App\Models\BaseModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\SessionWindow;

class FlowEngine
{
    private FlowNodeModel     $nodeModel;
    private FlowRunModel      $runModel;
    private FlowRunEventModel $eventModel;

    public function __construct()
    {
        $this->nodeModel  = new FlowNodeModel();
        $this->runModel   = new FlowRunModel();
        $this->eventModel = new FlowRunEventModel();
    }

    // ─── Public entry points ─────────────────────────────────────────────────

    /**
     * Process an inbound message. Called by the job queue.
     * Payload keys: account_id, contact_id, conversation_id,
     *               message_text, button_reply_id (optional)
     */
    public function dispatchInbound(array $payload): void
    {
        BaseModel::setBypassAccountScope(true);

        $accountId      = $payload['account_id'];
        $contactId      = $payload['contact_id'];
        $conversationId = $payload['conversation_id'];
        $messageText    = $payload['message_text'] ?? '';
        $effectiveInput = $payload['button_reply_id'] ?? $messageText;

        // If contact is already in a flow, continue it
        $activeRun = $this->runModel
            ->where('contact_id', $contactId)
            ->where('status', 'active')
            ->first();

        if ($activeRun) {
            // If the incoming message text matches another active visual chatbot flow's start trigger
            // OR if it matches any active automation's trigger keywords, we terminate the active flow run
            // so the new flow/automation can execute cleanly instead of being intercepted.
            $matchedOther = false;
            $db = \Config\Database::connect();

            // 1. Check if it matches other active chatbot flows
            $flows = (new FlowModel())
                ->where('account_id', $accountId)
                ->where('is_active', 1)
                ->where('id !=', $activeRun['flow_id'])
                ->findAll();
            foreach ($flows as $flow) {
                if ($this->matchesTrigger($flow, $messageText)) {
                    $matchedOther = true;
                    break;
                }
            }

            // 2. Check if it matches active keyword automations
            if (!$matchedOther) {
                $automations = $db->table('automations')
                    ->where('account_id', $accountId)
                    ->where('trigger_type', 'keyword_match')
                    ->where('is_active', 1)
                    ->get()->getResultArray();
                foreach ($automations as $auto) {
                    $config = json_decode($auto['trigger_config'] ?? '{}', true) ?? [];
                    $keywords = array_filter(array_map('trim', explode(',', $config['keywords'] ?? '')));
                    if (empty($keywords)) continue;
                    $msgLower = strtolower($messageText);
                    $matchAll = !empty($config['match_all']);
                    $matchedAuto = false;
                    foreach ($keywords as $kw) {
                        $found = str_contains($msgLower, strtolower($kw));
                        if ($matchAll && !$found) { $matchedAuto = false; break; }
                        if (!$matchAll && $found) { $matchedAuto = true; break; }
                    }
                    if ($matchedAuto || ($matchAll && !empty($keywords))) {
                        $matchedOther = true;
                        break;
                    }
                }
            }

            if ($matchedOther) {
                $this->endFlow($activeRun, 'terminated');
            } else {
                $this->processResponse($activeRun, $effectiveInput, $conversationId);
                return;
            }
        }

        // Check if this message triggers any flow
        $flows = (new FlowModel())
            ->where('account_id', $accountId)
            ->where('is_active', 1)
            ->findAll();

        foreach ($flows as $flow) {
            if ($this->matchesTrigger($flow, $messageText)) {
                $this->startFlow($flow, $contactId, $conversationId);
                return;
            }
        }
    }

    // ─── Flow lifecycle ──────────────────────────────────────────────────────

    private function startFlow(array $flow, string $contactId, string $conversationId): void
    {
        $runId = $this->runModel->insert([
            'flow_id'          => $flow['id'],
            'contact_id'       => $contactId,
            'conversation_id'  => $conversationId,
            'status'           => 'active',
            'current_node_key' => null,
            'vars'             => json_encode([]),
            'started_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $startNode = $this->nodeModel
            ->where('flow_id', $flow['id'])
            ->where('node_type', 'start')
            ->first();

        if (!$startNode) {
            log_message('error', "[FlowEngine] Flow {$flow['id']} has no start node");
            return;
        }

        $run = $this->runModel->find($runId);
        $this->executeNode($run, $startNode, null, $conversationId);

        (new FlowModel())->update($flow['id'], [
            'execution_count' => (int)$flow['execution_count'] + 1,
        ]);
    }

    private function processResponse(array $run, string $input, string $conversationId): void
    {
        $currentNode = $this->nodeModel
            ->where('flow_id', $run['flow_id'])
            ->where('node_key', $run['current_node_key'])
            ->first();

        if (!$currentNode) {
            log_message('error', "[FlowEngine] Current node not found: {$run['current_node_key']}");
            $this->endFlow($run, 'failed');
            return;
        }

        match ($currentNode['node_type']) {
            'collect_input'      => $this->handleCollectResponse($run, $currentNode, $input, $conversationId),
            'collect_form'       => $this->handleCollectFormResponse($run, $currentNode, $input, $conversationId),
            'send_buttons'       => $this->handleButtonResponse($run, $currentNode, $input, $conversationId),
            'send_media_buttons' => $this->handleMediaButtonResponse($run, $currentNode, $input, $conversationId),
            'send_list'          => $this->handleListResponse($run, $currentNode, $input, $conversationId),
            'request_location'   => $this->handleLocationResponse($run, $currentNode, $input, $conversationId),
            default              => log_message('info', "[FlowEngine] Unexpected input at node: {$currentNode['node_type']}"),
        };
    }

    private function endFlow(array $run, string $status): void
    {
        $this->runModel->update($run['id'], [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->eventModel->insert([
            'flow_run_id' => $run['id'],
            'node_key'    => $run['current_node_key'],
            'event_type'  => 'flow_' . $status,
            'event_data'  => json_encode(['status' => $status]),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    // ─── Node executor ───────────────────────────────────────────────────────

    private function executeNode(array $run, array $node, ?string $userInput, string $conversationId): void
    {
        // Reload run to ensure we have the latest vars and state (e.g. after HTTP requests or button saves)
        $run = $this->runModel->find($run['id']) ?? $run;

        $config = json_decode($node['config'] ?? '{}', true) ?? [];
        $vars   = json_decode($run['vars']    ?? '{}', true) ?? [];

        // Log execution event
        $this->eventModel->insert([
            'flow_run_id' => $run['id'],
            'node_key'    => $node['node_key'],
            'event_type'  => 'executed',
            'event_data'  => json_encode(['node_type' => $node['node_type'], 'user_input' => $userInput]),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Track current position
        $this->runModel->update($run['id'], [
            'current_node_key' => $node['node_key'],
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $run['current_node_key'] = $node['node_key'];

        try {
            switch ($node['node_type']) {
                case 'start':
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'send_message':
                    $text = $this->interpolate($config['message_text'] ?? '', $vars);
                    $this->sendText($conversationId, $text);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'send_buttons':
                    $this->execSendButtons($run, $node, $config, $conversationId, $vars);
                    // Waits for button click — do NOT auto-transition
                    break;

                case 'send_list':
                    $this->execSendList($run, $node, $config, $conversationId, $vars);
                    // Waits for list selection
                    break;

                case 'send_media':
                    $this->execSendMedia($run, $node, $config, $conversationId, $vars);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'collect_input':
                    $prompt = $this->interpolate($config['prompt_text'] ?? '', $vars);
                    $this->sendText($conversationId, $prompt);
                    // Waits for user text response
                    break;

                case 'condition':
                    $this->execCondition($run, $node, $config, $conversationId, $vars);
                    break;

                case 'set_tag':
                    $this->execSetTag($run, $config);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'send_media_buttons':
                    $this->execSendMediaButtons($run, $node, $config, $conversationId, $vars);
                    // Waits for button reply — do NOT auto-transition
                    break;

                case 'url_button':
                    $this->execUrlButton($run, $node, $config, $conversationId, $vars);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'request_location':
                    $this->execRequestLocation($run, $node, $config, $conversationId, $vars);
                    // Waits for location share
                    break;

                case 'collect_form':
                    $this->execCollectForm($run, $node, $config, $conversationId, $vars);
                    // Waits for first answer
                    break;

                case 'add_to_group':
                    $this->execAddToGroup($run, $config);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'handoff':
                    $this->execHandoff($run, $node, $config, $conversationId);
                    break;

                case 'end':
                    $this->endFlow($run, 'completed');
                    break;

                case 'send_catalog':
                    $this->execSendCatalog($run, $node, $config, $conversationId);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'send_product':
                    $this->execSendProduct($run, $node, $config, $conversationId);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'http_request':
                    $this->execHttpRequest($run, $node, $config, $conversationId, $vars);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'ai_node':
                    $this->execAiNode($run, $node, $config, $conversationId, $vars);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                default:
                    throw new \Exception('Unknown node type: ' . $node['node_type']);
            }
        } catch (\Exception $e) {
            log_message('error', "[FlowEngine] Node {$node['node_key']} error: " . $e->getMessage());
            $this->endFlow($run, 'failed');
        }
    }

    // ─── Specific node handlers ──────────────────────────────────────────────

    private function execSendButtons(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $bodyText  = $this->interpolate($config['body_text'] ?? '', $vars);
        $rawBtns   = $config['buttons'] ?? [];
        $waButtons = array_map(fn($b) => [
            'type'  => 'reply',
            'reply' => ['id' => $b['id'], 'title' => $b['title']],
        ], array_slice($rawBtns, 0, 3));

        [$phoneId, $token, $phone] = $this->getWaCredentials($conversationId);
        if ($phoneId && $phone) {
            (new MetaApi())->sendInteractiveButtons($phoneId, $token, $phone, $bodyText, $waButtons);
        }
    }

    private function execSendList(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $bodyText   = $this->interpolate($config['body_text'] ?? '', $vars);
        $buttonText = $config['button_text'] ?? 'View options';
        $sections   = $config['sections']    ?? [];

        [$phoneId, $token, $phone] = $this->getWaCredentials($conversationId);
        if ($phoneId && $phone) {
            (new MetaApi())->sendInteractiveList($phoneId, $token, $phone, $bodyText, $buttonText, $sections);
        }
    }

    private function execSendMedia(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $mediaType = $config['media_type'] ?? 'image';
        $mediaUrl  = $config['media_url']  ?? '';
        $caption   = $this->interpolate($config['caption'] ?? '', $vars);

        [$phoneId, $token, $phone] = $this->getWaCredentials($conversationId);
        if (!$phoneId || !$phone) return;

        $api = new MetaApi();
        match ($mediaType) {
            'image'    => $api->sendImage($phoneId, $token, $phone, $mediaUrl, $caption ?: null),
            'video'    => $api->sendVideo($phoneId, $token, $phone, $mediaUrl, $caption ?: null),
            'document' => $api->sendDocument($phoneId, $token, $phone, $mediaUrl, basename($mediaUrl), $caption ?: null),
            'audio'    => $api->sendAudio($phoneId, $token, $phone, $mediaUrl),
            default    => null,
        };
    }

    private function execCondition(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $condType = $config['condition_type'] ?? 'variable_equals';
        $pass     = false;

        switch ($condType) {
            case 'variable_equals':
                $pass = (string)($vars[$config['variable'] ?? ''] ?? '') === (string)($config['value'] ?? '');
                break;
            case 'variable_contains':
                $pass = stripos((string)($vars[$config['variable'] ?? ''] ?? ''), (string)($config['substring'] ?? '')) !== false;
                break;
            case 'contact_has_tag':
                $tagId = $config['tag_id'] ?? null;
                if ($tagId && $run['contact_id']) {
                    $pass = (bool)(new ContactTagModel())->where('contact_id', $run['contact_id'])->where('tag_id', $tagId)->first();
                }
                break;
        }

        $nextKey  = $pass ? ($config['true_node'] ?? null) : ($config['false_node'] ?? null);
        $nextNode = $nextKey ? $this->nodeModel->where('flow_id', $run['flow_id'])->where('node_key', $nextKey)->first() : null;

        if ($nextNode) {
            $this->executeNode($run, $nextNode, null, $conversationId);
        } else {
            $this->endFlow($run, 'completed');
        }
    }

    private function execSetTag(array $run, array $config): void
    {
        $tagId     = $config['tag_id'] ?? null;
        $action    = $config['action']  ?? 'add';
        $contactId = $run['contact_id'] ?? null;
        if (!$tagId || !$contactId) return;

        if ($action === 'remove') {
            (new ContactTagModel())->where('contact_id', $contactId)->where('tag_id', $tagId)->delete();
        } else {
            $exists = (new ContactTagModel())->where('contact_id', $contactId)->where('tag_id', $tagId)->first();
            if (!$exists) {
                (new ContactTagModel())->insert(['contact_id' => $contactId, 'tag_id' => $tagId]);
            }
        }
    }

    private function execSendMediaButtons(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $mediaType = $config['media_type'] ?? 'image';
        $mediaUrl  = $config['media_url']  ?? '';
        $bodyText  = $this->interpolate($config['body_text'] ?? '', $vars);
        $rawBtns   = $config['buttons'] ?? [];

        [$phoneId, $token, $phone] = $this->getWaCredentials($conversationId);
        if ($phoneId && $phone) {
            (new MetaApi())->sendMediaButtons($phoneId, $token, $phone, $mediaType, $mediaUrl, $bodyText, $rawBtns);
        }
    }

    private function execUrlButton(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $bodyText   = $this->interpolate($config['body_text']   ?? '', $vars);
        $footerText = $config['footer_text'] ?? null;
        $btnText    = $config['button_text'] ?? 'Open';
        $btnUrl     = $config['button_url']  ?? '';

        [$phoneId, $token, $phone] = $this->getWaCredentials($conversationId);
        if ($phoneId && $phone) {
            (new MetaApi())->sendCtaUrlButton($phoneId, $token, $phone, $bodyText, $btnText, $btnUrl, $footerText ?: null);
        }
    }

    private function execRequestLocation(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $msg = $this->interpolate($config['message_text'] ?? 'Please share your location.', $vars);

        [$phoneId, $token, $phone] = $this->getWaCredentials($conversationId);
        if ($phoneId && $phone) {
            (new MetaApi())->sendLocationRequest($phoneId, $token, $phone, $msg);
        }
    }

    private function execCollectForm(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $fields = $config['fields'] ?? [];
        if (empty($fields)) {
            $this->transitionToNext($run, $node, $conversationId);
            return;
        }

        // Store current field index in vars using a hidden key
        $idxKey = '_cf_' . $node['node_key'] . '_idx';
        $vars[$idxKey] = 0;
        $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);

        $this->sendText($conversationId, $fields[0]['label'] ?? 'Please answer:');
    }

    private function execAddToGroup(array $run, array $config): void
    {
        // WhatsApp Cloud API group membership requires additional Business API permissions.
        // Logged as intent — implement via Meta Graph API /group endpoint when permissions are granted.
        log_message('info', "[FlowEngine] add_to_group: group_id={$config['group_id']} contact={$run['contact_id']}");
    }

    private function handleCollectFormResponse(array $run, array $node, string $input, string $conversationId): void
    {
        $config  = json_decode($node['config'] ?? '{}', true) ?? [];
        $vars    = json_decode($run['vars']    ?? '{}', true) ?? [];
        $fields  = $config['fields'] ?? [];
        $idxKey  = '_cf_' . $node['node_key'] . '_idx';
        $idx     = (int)($vars[$idxKey] ?? 0);

        if (!isset($fields[$idx])) {
            $this->transitionToNext($run, $node, $conversationId);
            return;
        }

        $field      = $fields[$idx];
        $validation = $field['validation'] ?? 'none';

        if ($validation !== 'none' && !$this->validateInput($input, ['type' => $validation])) {
            $this->sendText($conversationId, 'Invalid input. Please try again.');
            return;
        }

        $vars[$field['variable_name']] = $input;
        $this->syncVariableToContact($run['contact_id'] ?? null, $field['variable_name'], $input);
        $idx++;

        if (isset($fields[$idx])) {
            // Ask next question
            $vars[$idxKey] = $idx;
            $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);
            $this->sendText($conversationId, $fields[$idx]['label'] ?? 'Please answer:');
        } else {
            // All fields done — send completion message if set, then move on
            unset($vars[$idxKey]);
            $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);

            $completionMsg = $this->interpolate($config['completion_message'] ?? '', $vars);
            if ($completionMsg) {
                $this->sendText($conversationId, $completionMsg);
            }
            $run['vars'] = json_encode($vars);
            $this->transitionToNext($run, $node, $conversationId);
        }
    }

    private function handleLocationResponse(array $run, array $node, string $input, string $conversationId): void
    {
        $config  = json_decode($node['config'] ?? '{}', true) ?? [];
        $vars    = json_decode($run['vars']    ?? '{}', true) ?? [];
        $varName = $config['variable_name'] ?? null;

        if ($varName) {
            $vars[$varName] = $input; // format: "lat,lng" from webhook
            $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);
            $this->syncVariableToContact($run['contact_id'] ?? null, $varName, $input);
            $run['vars'] = json_encode($vars);
        }

        $this->transitionToNext($run, $node, $conversationId);
    }

    private function handleMediaButtonResponse(array $run, array $node, string $input, string $conversationId): void
    {
        $config  = json_decode($node['config'] ?? '{}', true) ?? [];
        $vars    = json_decode($run['vars']    ?? '{}', true) ?? [];
        $buttons = $config['buttons'] ?? [];

        $matched = null;
        foreach ($buttons as $btn) {
            if (strtolower($input) === strtolower($btn['id']) || strtolower($input) === strtolower($btn['title'])) {
                $matched = $btn;
                break;
            }
        }

        if (!$matched) {
            $this->sendText($conversationId, 'Please tap one of the buttons to continue.');
            return;
        }

        if (!empty($config['save_to_variable'])) {
            $vars[$config['save_to_variable']] = $matched['id'];
            $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);
            $this->syncVariableToContact($run['contact_id'] ?? null, $config['save_to_variable'], $matched['id']);
            $run['vars'] = json_encode($vars);
        }

        $nextKey  = $matched['next_node'] ?? null;
        $nextNode = $nextKey ? $this->nodeModel->where('flow_id', $run['flow_id'])->where('node_key', $nextKey)->first() : null;

        if ($nextNode) {
            $this->executeNode($run, $nextNode, null, $conversationId);
        } else {
            $this->endFlow($run, 'completed');
        }
    }

    private function execHandoff(array $run, array $node, array $config, string $conversationId): void
    {
        $assignTo = $config['agent_id']        ?? null;
        $message  = $config['handoff_message'] ?? 'Connecting you with our team…';

        if ($message) {
            $this->sendText($conversationId, $message);
        }

        if ($conversationId) {
            \Config\Database::connect()
                ->table('conversations')
                ->where('id', $conversationId)
                ->set(['assigned_to' => $assignTo, 'status' => 'open'])
                ->update();
        }

        $this->endFlow($run, 'handed_off');
    }

    // ─── Response handlers (waiting nodes) ──────────────────────────────────

    private function handleCollectResponse(array $run, array $node, string $input, string $conversationId): void
    {
        $config  = json_decode($node['config'] ?? '{}', true) ?? [];
        $vars    = json_decode($run['vars']    ?? '{}', true) ?? [];
        $varName = $config['variable_name'] ?? 'input';

        $validation = $config['validation'] ?? null;
        if ($validation && !$this->validateInput($input, $validation)) {
            $this->sendText($conversationId, $validation['error_message'] ?? 'Invalid input. Please try again.');
            return;
        }

        $vars[$varName] = $input;
        $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->syncVariableToContact($run['contact_id'] ?? null, $varName, $input);
        $run['vars'] = json_encode($vars);

        $this->transitionToNext($run, $node, $conversationId);
    }

    private function handleButtonResponse(array $run, array $node, string $input, string $conversationId): void
    {
        $config  = json_decode($node['config'] ?? '{}', true) ?? [];
        $buttons = $config['buttons'] ?? [];

        foreach ($buttons as $btn) {
            if ($btn['id'] === $input || strtolower($btn['title'] ?? '') === strtolower($input)) {
                // Schema advertises "Save Selection To Variable" for this
                // node but nothing here ever read it — the selected button
                // silently never got saved anywhere.
                if (!empty($config['save_to_variable'])) {
                    $vars = json_decode($run['vars'] ?? '{}', true) ?? [];
                    $vars[$config['save_to_variable']] = $btn['id'];
                    $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);
                    $this->syncVariableToContact($run['contact_id'] ?? null, $config['save_to_variable'], $btn['id']);
                    $run['vars'] = json_encode($vars);
                }

                $nextKey  = $btn['next_node'] ?? null;
                $nextNode = $nextKey ? $this->nodeModel->where('flow_id', $run['flow_id'])->where('node_key', $nextKey)->first() : null;
                if ($nextNode) {
                    $this->executeNode($run, $nextNode, $input, $conversationId);
                } else {
                    $this->endFlow($run, 'completed');
                }
                return;
            }
        }

        // No match — prompt again
        $titles = implode(' / ', array_column($buttons, 'title'));
        $this->sendText($conversationId, "Please choose: {$titles}");
    }

    private function handleListResponse(array $run, array $node, string $input, string $conversationId): void
    {
        $config  = json_decode($node['config'] ?? '{}', true) ?? [];
        $vars    = json_decode($run['vars']    ?? '{}', true) ?? [];
        // Schema's field key is 'save_to_variable' (matches send_buttons/
        // send_media_buttons) — this previously read 'variable_name', which
        // never existed in saved config, so the user's chosen variable name
        // was silently ignored in favor of a hardcoded default.
        $varName = $config['save_to_variable'] ?? 'list_selection';

        $vars[$varName] = $input;
        $this->runModel->update($run['id'], ['vars' => json_encode($vars), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->syncVariableToContact($run['contact_id'] ?? null, $varName, $input);
        $run['vars'] = json_encode($vars);

        $nextKey  = $config['next_node'] ?? null;
        $nextNode = $nextKey ? $this->nodeModel->where('flow_id', $run['flow_id'])->where('node_key', $nextKey)->first() : null;

        if ($nextNode) {
            $this->executeNode($run, $nextNode, $input, $conversationId);
        } else {
            $this->endFlow($run, 'completed');
        }
    }

    // ─── Transition ──────────────────────────────────────────────────────────

    private function transitionToNext(array $run, array $node, string $conversationId): void
    {
        $config  = json_decode($node['config'] ?? '{}', true) ?? [];
        $nextKey = $config['next_node'] ?? null;

        if (!$nextKey) {
            $this->endFlow($run, 'completed');
            return;
        }

        $nextNode = $this->nodeModel
            ->where('flow_id', $run['flow_id'])
            ->where('node_key', $nextKey)
            ->first();

        if (!$nextNode) {
            log_message('error', "[FlowEngine] Next node '{$nextKey}' not found in flow {$run['flow_id']}");
            $this->endFlow($run, 'failed');
            return;
        }

        $this->executeNode($run, $nextNode, null, $conversationId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function sendText(string $conversationId, string $text): void
    {
        if (empty(trim($text))) return;

        [$phoneId, $token, $phone] = $this->getWaCredentials($conversationId);
        if (!$phoneId || !$phone) {
            log_message('warning', "[FlowEngine] Cannot send — no WA config for conversation {$conversationId}");
            return;
        }

        try {
            $response = (new MetaApi())->sendText($phoneId, $token, $phone, $text);
            $this->logBotMessageToInbox($conversationId, $text, $response['messages'][0]['id'] ?? null);
        } catch (\Exception $e) {
            log_message('error', "[FlowEngine] sendText failed: " . $e->getMessage());
        }
    }

    /**
     * Every flow node that sends plain text (send_message, collect_input's
     * prompt/validation retry, request_location, collect_form, ai_node) goes
     * through sendText() above. Without this, the message genuinely
     * delivers via WhatsApp but never appears in the CRM's own inbox —
     * same visibility gap fixed elsewhere this session, just missed here.
     */
    private function logBotMessageToInbox(string $conversationId, string $text, ?string $waMessageId): void
    {
        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->find($conversationId);
        if (!$conversation) return;

        (new MessageModel())->insert([
            'conversation_id'     => $conversationId,
            'account_id'          => $conversation['account_id'],
            'sender_type'         => 'agent',
            'content_type'        => 'text',
            'content_text'        => $text,
            'status'              => 'sent',
            'whatsapp_message_id' => $waMessageId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $conversationModel->update($conversationId, [
            'last_message_text' => $text,
            'last_message_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function execSendCatalog(array $run, array $node, array $config, string $conversationId): void
    {
        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) return;

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $conversation['account_id'])->first();
        if (!$waConfig || empty($waConfig['catalog_id'])) {
            log_message('warning', '[FlowEngine] send_catalog — no catalog connected for account ' . ($conversation['account_id'] ?? ''));
            return;
        }

        if (!SessionWindow::isOpen($conversation['last_customer_message_at'] ?? null)) {
            log_message('warning', "[FlowEngine] send_catalog — 24h session window closed for conversation {$conversationId}");
            return;
        }

        $contact = (new ContactModel())->find($conversation['contact_id']);
        if (!$contact) return;

        $token      = (new Encryption())->decrypt($waConfig['access_token']);
        $bodyText   = $config['body_text'] ?? 'Browse our products';
        $footerText = $config['footer_text'] ?? null;

        try {
            $response = (new MetaApi())->sendCatalogMessage(
                $waConfig['phone_number_id'],
                $token,
                $contact['phone_normalized'],
                $bodyText,
                $footerText
            );
            $this->logNonTextMessageToInbox($conversationId, 'catalog', $bodyText, $response['messages'][0]['id'] ?? null, '[Catalog]');
        } catch (\Exception $e) {
            log_message('error', "[FlowEngine] send_catalog failed for conversation {$conversationId}: " . $e->getMessage());
        }
    }

    private function execSendProduct(array $run, array $node, array $config, string $conversationId): void
    {
        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) return;

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $conversation['account_id'])->first();
        if (!$waConfig || empty($waConfig['catalog_id'])) {
            log_message('warning', '[FlowEngine] send_product — no catalog connected');
            return;
        }

        if (!SessionWindow::isOpen($conversation['last_customer_message_at'] ?? null)) {
            log_message('warning', "[FlowEngine] send_product — 24h session window closed for conversation {$conversationId}");
            return;
        }

        $contact           = (new ContactModel())->find($conversation['contact_id']);
        $productRetailerId = $config['product_retailer_id'] ?? null;

        if (!$contact || !$productRetailerId) {
            log_message('warning', '[FlowEngine] send_product — missing contact or product_retailer_id');
            return;
        }

        $token    = (new Encryption())->decrypt($waConfig['access_token']);
        $bodyText = $config['body_text'] ?? '';

        try {
            $response = (new MetaApi())->sendSingleProduct(
                $waConfig['phone_number_id'],
                $token,
                $contact['phone_normalized'],
                $waConfig['catalog_id'],
                $productRetailerId,
                $bodyText
            );
            $this->logNonTextMessageToInbox($conversationId, 'product', 'Product: ' . $productRetailerId, $response['messages'][0]['id'] ?? null, '[Product]');
        } catch (\Exception $e) {
            log_message('error', "[FlowEngine] send_product failed for conversation {$conversationId}: " . $e->getMessage());
        }
    }

    /**
     * Mirrors logBotMessageToInbox() for non-text sends (catalog/product) so
     * a successful flow-triggered send is visible in the CRM inbox, same as
     * Api\CatalogController already does for manually-sent ones.
     */
    private function logNonTextMessageToInbox(string $conversationId, string $contentType, string $contentText, ?string $waMessageId, string $lastMessagePreview): void
    {
        $conversationModel = new ConversationModel();
        $conversation      = $conversationModel->find($conversationId);
        if (!$conversation) return;

        (new MessageModel())->insert([
            'conversation_id'     => $conversationId,
            'account_id'          => $conversation['account_id'],
            'sender_type'         => 'agent',
            'content_type'        => $contentType,
            'content_text'        => $contentText,
            'status'              => 'sent',
            'whatsapp_message_id' => $waMessageId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $conversationModel->update($conversationId, [
            'last_message_text' => $lastMessagePreview,
            'last_message_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function execHttpRequest(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $method = strtoupper($config['method'] ?? 'GET');
        $url    = $this->interpolate($config['url'] ?? '', $vars);

        $headers = [];
        foreach ($config['headers'] ?? [] as $h) {
            if (!empty($h['key'])) {
                $headers[$h['key']] = $this->interpolate($h['value'] ?? '', $vars);
            }
        }

        // Body is built from typed key/value pairs (not a raw JSON template)
        // so customer-supplied variable values go through json_encode's own
        // escaping instead of being string-substituted into a JSON template,
        // where a stray quote in a WhatsApp reply could corrupt the payload
        // or inject extra fields into the request sent to a third party.
        $bodyFields = [];
        foreach ($config['body_fields'] ?? [] as $f) {
            if (!empty($f['key'])) {
                $bodyFields[$f['key']] = $this->interpolate($f['value'] ?? '', $vars);
            }
        }

        $statusVar = $config['status_variable'] ?? null;
        $runVars   = json_decode($run['vars'] ?? '{}', true) ?? [];

        try {
            $client  = \Config\Services::curlrequest(['timeout' => 15, 'http_errors' => false, 'headers' => $headers]);
            $options = [];
            if ($method !== 'GET' && $bodyFields) {
                $options[($config['body_format'] ?? 'json') === 'form' ? 'form_params' : 'json'] = $bodyFields;
            }

            $response = match ($method) {
                'GET'    => $client->get($url),
                'POST'   => $client->post($url, $options),
                'PUT'    => $client->put($url, $options),
                'PATCH'  => $client->request('PATCH', $url, $options),
                'DELETE' => $client->delete($url, $options),
                default  => throw new \Exception('Unsupported HTTP method: ' . $method),
            };

            $statusCode = $response->getStatusCode();
            $result     = json_decode((string) $response->getBody(), true) ?? [];

            foreach ($config['response_mapping'] ?? [] as $map) {
                if (empty($map['variable_name'])) continue;
                $value = $this->extractPath($result, $map['response_path'] ?? '');
                $runVars[$map['variable_name']] = $value;
                $this->syncVariableToContact($run['contact_id'] ?? null, $map['variable_name'], $value);
            }

            if ($statusVar) {
                $runVars[$statusVar] = $statusCode;
            }
        } catch (\Exception $e) {
            // A failed external call shouldn't kill the whole flow — record
            // it in status_variable (if configured) so a Condition node can
            // branch on it, then continue to the next node normally.
            log_message('error', "[FlowEngine] http_request node {$node['node_key']} failed: " . $e->getMessage());
            if ($statusVar) {
                $runVars[$statusVar] = 'error';
            }
        }

        $this->runModel->update($run['id'], ['vars' => json_encode($runVars), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function execAiNode(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $varName = $config['save_to_variable'] ?? 'ai_reply';
        $runVars = json_decode($run['vars'] ?? '{}', true) ?? [];

        $conversation = (new ConversationModel())->find($conversationId);
        $accountId    = $conversation['account_id'] ?? null;

        if (!$accountId) {
            $runVars[$varName] = '';
            $this->runModel->update($run['id'], ['vars' => json_encode($runVars), 'updated_at' => date('Y-m-d H:i:s')]);
            return;
        }

        $aiConfig     = (new AiConfigModel())->where('account_id', $accountId)->first();
        $model        = $config['model'] ?: ($aiConfig['model'] ?? 'gpt-4o-mini');
        $systemPrompt = $this->interpolate($config['system_prompt'] ?? '', $vars);
        $userPrompt   = $this->interpolate($config['user_prompt']   ?? '', $vars);

        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $result = \App\Libraries\OpenAiClient::chat($accountId, $messages, $model, 500, 'ai_node');
        if (isset($result['error'])) {
            log_message('warning', "[FlowEngine] ai_node: {$result['error']}");
        }
        $reply = $result['text'] ?? '';

        $runVars[$varName] = $reply;
        $this->runModel->update($run['id'], ['vars' => json_encode($runVars), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->syncVariableToContact($run['contact_id'] ?? null, $varName, $reply);

        if (($config['send_as_message'] ?? 'no') === 'yes' && $reply !== '') {
            $this->sendText($conversationId, $reply);
        }
    }

    private function extractPath(array $data, string $path)
    {
        if ($path === '') return null;
        $value = $data;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) return null;
            $value = $value[$key];
        }
        return $value;
    }

    /**
     * Flow variables normally live only in flow_runs.vars and die with the
     * run. Syncing here makes collected answers permanent on the contact —
     * visible in Contacts, exportable, usable by later automations — instead
     * of vanishing once this flow run ends.
     */
    private function syncVariableToContact(?string $contactId, string $varName, $value): void
    {
        if (!$contactId || $value === null || $value === '') return;
        // Internal bookkeeping vars (e.g. collect_form's "_cf_..._idx" page
        // cursor) are never real customer data.
        if (str_starts_with($varName, '_')) return;

        $contact = (new ContactModel())->find($contactId);
        if (!$contact) return;

        $fieldModel = new CustomFieldModel();
        $field = $fieldModel->where('account_id', $contact['account_id'])
            ->where('field_name', $varName)
            ->first();

        $fieldId = $field['id'] ?? $fieldModel->insert([
            'account_id' => $contact['account_id'],
            'field_name' => $varName,
            'field_type' => 'text',
        ]);

        $valueModel = new ContactCustomValueModel();
        $existing   = $valueModel->where('contact_id', $contactId)->where('custom_field_id', $fieldId)->first();

        if ($existing) {
            $valueModel->update($existing['id'], ['value' => (string) $value]);
        } else {
            $valueModel->insert(['contact_id' => $contactId, 'custom_field_id' => $fieldId, 'value' => (string) $value]);
        }
    }

    private function getWaCredentials(string $conversationId): array
    {
        $conversation = (new ConversationModel())->find($conversationId);
        if (!$conversation) return [null, null, null];

        $contact  = (new ContactModel())->find($conversation['contact_id']);
        $waConfig = (new WhatsAppConfigModel())->where('account_id', $conversation['account_id'])->first();

        if (!$contact || !$waConfig || ($waConfig['status'] ?? '') !== 'connected') return [null, null, null];

        $token = (new Encryption())->decrypt($waConfig['access_token']);
        return [$waConfig['phone_number_id'], $token, $contact['phone']];
    }

    private function matchesTrigger(array $flow, string $messageText): bool
    {
        $keywords = json_decode($flow['trigger_keywords'] ?? '[]', true) ?? [];
        if (empty($keywords)) return false;

        $msg = strtolower(trim($messageText));
        foreach ($keywords as $kw) {
            if (str_contains($msg, strtolower(trim($kw)))) return true;
        }
        return false;
    }

    private function interpolate(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string)$value, $text);
        }
        return $text;
    }

    private function validateInput(string $input, array $rules): bool
    {
        return match ($rules['type'] ?? 'text') {
            'phone'      => (bool)preg_match('/^\+?[1-9]\d{9,14}$/', preg_replace('/\s+/', '', $input)),
            'email'      => filter_var($input, FILTER_VALIDATE_EMAIL) !== false,
            'number'     => is_numeric($input),
            'min_length' => strlen($input) >= (int)($rules['min'] ?? 1),
            'max_length' => strlen($input) <= (int)($rules['max'] ?? 255),
            default      => trim($input) !== '',
        };
    }
}
