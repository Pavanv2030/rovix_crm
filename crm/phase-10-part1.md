## PHASE 10: Visual Flow Builder (Week 8-9)

### Prompt 10.1 — Flow Engine Core (Runtime Execution)

```
Build the flow runtime execution engine for Rovix AI Leads Tool.

Reference original wacrm files:
- src/lib/flows/flow-engine.ts
- src/lib/flows/node-executor.ts
- src/app/api/flows/dispatch/route.ts

IMPORTANT: Flows are visual chatbot-style decision trees. Unlike automations (event-driven), flows are conversational (input → output loops).

Flow structure:
- Flow: Container with trigger keywords
- Nodes: Individual steps (send_message, send_buttons, collect_input, condition, etc.)
- Flow Runs: Active execution instances (one per contact per flow)
- Events: Log of each node execution

Create app/Libraries/FlowEngine.php:

<?php
namespace App\Libraries;

use App\Models\FlowModel;
use App\Models\FlowNodeModel;
use App\Models\FlowRunModel;
use App\Models\FlowRunEventModel;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\BaseModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class FlowEngine
{
    /**
     * Check if inbound message triggers a flow
     * Called by job queue (dispatched from webhook)
     */
    public function dispatchInbound(array $payload): void
    {
        BaseModel::setBypassAccountScope(true);

        $accountId = $payload['account_id'];
        $contactId = $payload['contact_id'];
        $conversationId = $payload['conversation_id'];
        $messageText = $payload['message_text'] ?? '';

        // Check for active flow run first
        $runModel = new FlowRunModel();
        $activeRun = $runModel
            ->where('contact_id', $contactId)
            ->where('status', 'active')
            ->first();

        if ($activeRun) {
            // Contact already in a flow - process their response
            $this->processResponse($activeRun, $messageText, $conversationId);
            return;
        }

        // No active flow - check if message triggers a new flow
        $flowModel = new FlowModel();
        $flows = $flowModel
            ->where('account_id', $accountId)
            ->where('is_active', 1)
            ->findAll();

        foreach ($flows as $flow) {
            if ($this->matchesTrigger($flow, $messageText)) {
                // Start new flow
                $this->startFlow($flow, $contactId, $conversationId);
                return;
            }
        }
    }

    private function matchesTrigger(array $flow, string $messageText): bool
    {
        $keywords = json_decode($flow['trigger_keywords'], true);
        
        if (empty($keywords)) {
            return false;
        }

        $messageText = strtolower(trim($messageText));

        foreach ($keywords as $keyword) {
            if (stripos($messageText, trim($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function startFlow(array $flow, string $contactId, string $conversationId): void
    {
        // Create flow run
        $runModel = new FlowRunModel();
        $runId = $runModel->insert([
            'flow_id' => $flow['id'],
            'contact_id' => $contactId,
            'conversation_id' => $conversationId,
            'status' => 'active',
            'current_node_key' => 'start', // Will be updated
            'vars' => json_encode([]) // Flow variables
        ]);

        // Find start node
        $nodeModel = new FlowNodeModel();
        $startNode = $nodeModel
            ->where('flow_id', $flow['id'])
            ->where('node_type', 'start')
            ->first();

        if (!$startNode) {
            log_message('error', "Flow {$flow['id']} has no start node");
            return;
        }

        // Execute start node
        $run = $runModel->find($runId);
        $this->executeNode($run, $startNode, null, $conversationId);
    }

    private function processResponse(array $run, string $messageText, string $conversationId): void
    {
        // Load current node
        $nodeModel = new FlowNodeModel();
        $currentNode = $nodeModel
            ->where('flow_id', $run['flow_id'])
            ->where('node_key', $run['current_node_key'])
            ->first();

        if (!$currentNode) {
            log_message('error', "Current node not found: {$run['current_node_key']}");
            $this->endFlow($run, 'failed');
            return;
        }

        // Handle based on node type
        if ($currentNode['node_type'] === 'collect_input') {
            $this->handleCollectInputResponse($run, $currentNode, $messageText, $conversationId);
        } elseif ($currentNode['node_type'] === 'send_buttons') {
            $this->handleButtonResponse($run, $currentNode, $messageText, $conversationId);
        } elseif ($currentNode['node_type'] === 'send_list') {
            $this->handleListResponse($run, $currentNode, $messageText, $conversationId);
        } else {
            // Node not expecting input - this shouldn't happen
            log_message('warning', "Unexpected message in flow at node: {$currentNode['node_type']}");
        }
    }

    private function executeNode(array $run, array $node, ?string $userInput, string $conversationId): void
    {
        $config = json_decode($node['config'], true);
        $vars = json_decode($run['vars'], true);

        // Log event
        $eventModel = new FlowRunEventModel();
        $eventModel->insert([
            'flow_run_id' => $run['id'],
            'node_key' => $node['node_key'],
            'event_type' => 'executed',
            'event_data' => json_encode([
                'node_type' => $node['node_type'],
                'user_input' => $userInput
            ])
        ]);

        // Update current node
        $runModel = new FlowRunModel();
        $runModel->update($run['id'], ['current_node_key' => $node['node_key']]);

        try {
            switch ($node['node_type']) {
                case 'start':
                    // Start node just transitions to next
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'send_message':
                    $this->executeSendMessage($run, $node, $config, $conversationId, $vars);
                    break;

                case 'send_buttons':
                    $this->executeSendButtons($run, $node, $config, $conversationId, $vars);
                    break;

                case 'send_list':
                    $this->executeSendList($run, $node, $config, $conversationId, $vars);
                    break;

                case 'send_media':
                    $this->executeSendMedia($run, $node, $config, $conversationId, $vars);
                    break;

                case 'collect_input':
                    $this->executeCollectInput($run, $node, $config, $conversationId);
                    break;

                case 'condition':
                    $this->executeCondition($run, $node, $config, $conversationId, $vars);
                    break;

                case 'set_tag':
                    $this->executeSetTag($run, $node, $config);
                    $this->transitionToNext($run, $node, $conversationId);
                    break;

                case 'handoff':
                    $this->executeHandoff($run, $node, $config, $conversationId);
                    break;

                case 'end':
                    $this->endFlow($run, 'completed');
                    break;

                default:
                    throw new \Exception('Unknown node type: ' . $node['node_type']);
            }

        } catch (\Exception $e) {
            log_message('error', "Flow execution error: " . $e->getMessage());
            $this->endFlow($run, 'failed');
        }
    }

    private function executeSendMessage(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $messageText = $this->replaceVariables($config['message_text'], $vars);

        // Queue message send
        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('send_message', [
            'conversation_id' => $conversationId,
            'content_type' => 'text',
            'content_text' => $messageText
        ], null, 6); // Priority 6 for flow messages

        // Auto-transition to next node
        $this->transitionToNext($run, $node, $conversationId);
    }

    private function executeSendButtons(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $bodyText = $this->replaceVariables($config['body_text'], $vars);
        $buttons = $config['buttons']; // Array of {id, title}

        // Format buttons for WhatsApp interactive message
        $whatsappButtons = array_map(function($btn) {
            return [
                'type' => 'reply',
                'reply' => [
                    'id' => $btn['id'],
                    'title' => $btn['title']
                ]
            ];
        }, array_slice($buttons, 0, 3)); // Max 3 buttons

        // Get WhatsApp config and send
        $this->sendInteractiveButtons($conversationId, $bodyText, $whatsappButtons);

        // Wait for user response - don't auto-transition
        // processResponse() will handle button click
    }

    private function executeSendList(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $bodyText = $this->replaceVariables($config['body_text'], $vars);
        $buttonText = $config['button_text'];
        $sections = $config['sections']; // Array of {title, rows: [{id, title, description}]}

        // Send WhatsApp list message
        $this->sendInteractiveList($conversationId, $bodyText, $buttonText, $sections);

        // Wait for user response
    }

    private function executeSendMedia(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $mediaType = $config['media_type']; // image, video, document
        $mediaUrl = $config['media_url'];
        $caption = $this->replaceVariables($config['caption'] ?? '', $vars);

        // Queue media send
        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('send_message', [
            'conversation_id' => $conversationId,
            'content_type' => $mediaType,
            'media_url' => $mediaUrl,
            'content_text' => $caption
        ], null, 6);

        $this->transitionToNext($run, $node, $conversationId);
    }

    private function executeCollectInput(array $run, array $node, array $config, string $conversationId): void
    {
        // Send prompt message
        $promptText = $config['prompt_text'];
        
        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('send_message', [
            'conversation_id' => $conversationId,
            'content_type' => 'text',
            'content_text' => $promptText
        ], null, 6);

        // Wait for user response
        // processResponse() will save input to vars and transition
    }

    private function handleCollectInputResponse(array $run, array $node, string $messageText, string $conversationId): void
    {
        $config = json_decode($node['config'], true);
        $vars = json_decode($run['vars'], true);

        // Save input to variable
        $variableName = $config['variable_name'];
        $vars[$variableName] = $messageText;

        // Validate if validation rules exist
        $validation = $config['validation'] ?? null;
        if ($validation) {
            if (!$this->validateInput($messageText, $validation)) {
                // Invalid - send error message and ask again
                $errorMessage = $validation['error_message'] ?? 'Invalid input. Please try again.';
                
                $dispatcher = new JobDispatcher();
                $dispatcher->dispatch('send_message', [
                    'conversation_id' => $conversationId,
                    'content_type' => 'text',
                    'content_text' => $errorMessage
                ], null, 6);

                return; // Don't transition, wait for valid input
            }
        }

        // Save vars and transition
        $runModel = new FlowRunModel();
        $runModel->update($run['id'], ['vars' => json_encode($vars)]);

        $run['vars'] = json_encode($vars);
        $this->transitionToNext($run, $node, $conversationId);
    }

    // Continuing in next file...
