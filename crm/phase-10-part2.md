    private function handleButtonResponse(array $run, array $node, string $messageText, string $conversationId): void
    {
        $config = json_decode($node['config'], true);
        $buttons = $config['buttons'];

        // Find which button was clicked (match by title or ID)
        $selectedButton = null;
        foreach ($buttons as $button) {
            if (strcasecmp(trim($messageText), trim($button['title'])) === 0) {
                $selectedButton = $button;
                break;
            }
        }

        if (!$selectedButton) {
            // Invalid response - send error and wait again
            $dispatcher = new JobDispatcher();
            $dispatcher->dispatch('send_message', [
                'conversation_id' => $conversationId,
                'content_type' => 'text',
                'content_text' => 'Please click one of the buttons.'
            ], null, 6);
            return;
        }

        // Save button selection to vars if configured
        if (!empty($config['save_to_variable'])) {
            $vars = json_decode($run['vars'], true);
            $vars[$config['save_to_variable']] = $selectedButton['id'];
            
            $runModel = new FlowRunModel();
            $runModel->update($run['id'], ['vars' => json_encode($vars)]);
            $run['vars'] = json_encode($vars);
        }

        // Transition to button's target node
        $targetNodeKey = $selectedButton['next_node'] ?? null;
        if ($targetNodeKey) {
            $this->transitionTo($run, $targetNodeKey, $conversationId);
        } else {
            $this->transitionToNext($run, $node, $conversationId);
        }
    }

    private function handleListResponse(array $run, array $node, string $messageText, string $conversationId): void
    {
        // Similar to handleButtonResponse but for list items
        $config = json_decode($node['config'], true);
        $sections = $config['sections'];

        $selectedItem = null;
        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                if (strcasecmp(trim($messageText), trim($row['title'])) === 0) {
                    $selectedItem = $row;
                    break 2;
                }
            }
        }

        if (!$selectedItem) {
            $dispatcher = new JobDispatcher();
            $dispatcher->dispatch('send_message', [
                'conversation_id' => $conversationId,
                'content_type' => 'text',
                'content_text' => 'Please select an option from the list.'
            ], null, 6);
            return;
        }

        // Save selection
        if (!empty($config['save_to_variable'])) {
            $vars = json_decode($run['vars'], true);
            $vars[$config['save_to_variable']] = $selectedItem['id'];
            
            $runModel = new FlowRunModel();
            $runModel->update($run['id'], ['vars' => json_encode($vars)]);
            $run['vars'] = json_encode($vars);
        }

        $this->transitionToNext($run, $node, $conversationId);
    }

    private function executeCondition(array $run, array $node, array $config, string $conversationId, array $vars): void
    {
        $conditionType = $config['condition_type'];
        $result = false;

        switch ($conditionType) {
            case 'variable_equals':
                $varName = $config['variable'];
                $expectedValue = $config['value'];
                $result = isset($vars[$varName]) && $vars[$varName] === $expectedValue;
                break;

            case 'variable_contains':
                $varName = $config['variable'];
                $substring = $config['substring'];
                $result = isset($vars[$varName]) && stripos($vars[$varName], $substring) !== false;
                break;

            case 'contact_has_tag':
                $tagId = $config['tag_id'];
                $contactTagModel = new \App\Models\ContactTagModel();
                $has = $contactTagModel
                    ->where('contact_id', $run['contact_id'])
                    ->where('tag_id', $tagId)
                    ->first();
                $result = $has !== null;
                break;

            default:
                $result = false;
        }

        // Transition based on result
        $targetNodeKey = $result ? $config['true_node'] : $config['false_node'];
        $this->transitionTo($run, $targetNodeKey, $conversationId);
    }

    private function executeSetTag(array $run, array $node, array $config): void
    {
        $tagId = $config['tag_id'];
        $action = $config['action']; // 'add' or 'remove'

        $contactTagModel = new \App\Models\ContactTagModel();

        if ($action === 'add') {
            $existing = $contactTagModel
                ->where('contact_id', $run['contact_id'])
                ->where('tag_id', $tagId)
                ->first();

            if (!$existing) {
                $contactTagModel->insert([
                    'contact_id' => $run['contact_id'],
                    'tag_id' => $tagId
                ]);
            }
        } elseif ($action === 'remove') {
            $contactTagModel
                ->where('contact_id', $run['contact_id'])
                ->where('tag_id', $tagId)
                ->delete();
        }
    }

    private function executeHandoff(array $run, array $node, array $config, string $conversationId): void
    {
        // Hand off to human agent
        $agentId = $config['agent_id'] ?? null;

        $conversationModel = new ConversationModel();
        $conversationModel->update($conversationId, [
            'assigned_agent_id' => $agentId,
            'status' => 'open'
        ]);

        // Send handoff message
        $handoffMessage = $config['handoff_message'] ?? 'Connecting you with an agent...';
        
        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('send_message', [
            'conversation_id' => $conversationId,
            'content_type' => 'text',
            'content_text' => $handoffMessage
        ], null, 6);

        // End flow
        $this->endFlow($run, 'handed_off');
    }

    private function transitionToNext(array $run, array $node, string $conversationId): void
    {
        // Find next node in flow
        $nodeModel = new FlowNodeModel();
        $nextNode = $nodeModel
            ->where('flow_id', $run['flow_id'])
            ->where('node_key', $node['next_node'] ?? '')
            ->first();

        if ($nextNode) {
            $this->executeNode($run, $nextNode, null, $conversationId);
        } else {
            // No next node - end flow
            $this->endFlow($run, 'completed');
        }
    }

    private function transitionTo(array $run, string $targetNodeKey, string $conversationId): void
    {
        $nodeModel = new FlowNodeModel();
        $targetNode = $nodeModel
            ->where('flow_id', $run['flow_id'])
            ->where('node_key', $targetNodeKey)
            ->first();

        if ($targetNode) {
            $this->executeNode($run, $targetNode, null, $conversationId);
        } else {
            log_message('error', "Target node not found: {$targetNodeKey}");
            $this->endFlow($run, 'failed');
        }
    }

    private function endFlow(array $run, string $status): void
    {
        $runModel = new FlowRunModel();
        $runModel->update($run['id'], ['status' => $status]);

        // Update flow execution count
        $flowModel = new FlowModel();
        $flowModel->where('id', $run['flow_id'])->set([
            'execution_count' => 'execution_count + 1'
        ], false)->update();
    }

    private function replaceVariables(string $text, array $vars): string
    {
        // Replace {{variable_name}} with values
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    private function validateInput(string $input, array $validation): bool
    {
        $type = $validation['type'];

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;

            case 'phone':
                return preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $input));

            case 'number':
                return is_numeric($input);

            case 'min_length':
                return strlen($input) >= $validation['value'];

            case 'max_length':
                return strlen($input) <= $validation['value'];

            default:
                return true;
        }
    }

    private function sendInteractiveButtons(string $conversationId, string $bodyText, array $buttons): void
    {
        // Get conversation and send via MetaApi
        $conversationModel = new ConversationModel();
        $conversation = $conversationModel->find($conversationId);
        
        if (!$conversation) return;

        $contactModel = new ContactModel();
        $contact = $contactModel->find($conversation['contact_id']);
        
        if (!$contact) return;

        // Get WhatsApp config
        $waConfigModel = new \App\Models\WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('account_id', $conversation['account_id'])->first();
        
        if (!$waConfig) return;

        $encryption = new Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        $metaApi = new MetaApi();
        $metaApi->sendInteractiveButtons(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            $bodyText,
            $buttons
        );
    }

    private function sendInteractiveList(string $conversationId, string $bodyText, string $buttonText, array $sections): void
    {
        // Similar to sendInteractiveButtons but for lists
        $conversationModel = new ConversationModel();
        $conversation = $conversationModel->find($conversationId);
        
        if (!$conversation) return;

        $contactModel = new ContactModel();
        $contact = $contactModel->find($conversation['contact_id']);
        
        if (!$contact) return;

        $waConfigModel = new \App\Models\WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('account_id', $conversation['account_id'])->first();
        
        if (!$waConfig) return;

        $encryption = new Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        $metaApi = new MetaApi();
        $metaApi->sendInteractiveList(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            $bodyText,
            $buttonText,
            $sections
        );
    }
}

Update app/Commands/ProcessQueue.php (add case):

case 'check_flow':
    $engine = new \App\Libraries\FlowEngine();
    $engine->dispatchInbound($payload);
    CLI::write("  Flow check for contact {$payload['contact_id']}", 'cyan');
    break;

Create app/Commands/CleanupStaleFlows.php:

<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\FlowRunModel;
use App\Models\BaseModel;

class CleanupStaleFlows extends BaseCommand
{
    protected $group = 'Flows';
    protected $name = 'flows:cleanup-stale';
    protected $description = 'Mark stale flow runs as timed out';

    public function run(array $params)
    {
        BaseModel::setBypassAccountScope(true);

        // Flows active for > 24 hours are considered stale
        $staleDate = date('Y-m-d H:i:s', time() - 86400);

        $runModel = new FlowRunModel();
        $staleRuns = $runModel
            ->where('status', 'active')
            ->where('updated_at <', $staleDate)
            ->findAll();

        if (empty($staleRuns)) {
            CLI::write('No stale flow runs', 'yellow');
            return;
        }

        CLI::write('Marking ' . count($staleRuns) . ' stale flows as timed out...', 'green');

        foreach ($staleRuns as $run) {
            $runModel->update($run['id'], ['status' => 'timed_out']);
        }

        CLI::write('Done', 'green');
    }
}

Add to RunScheduled.php:

// Cleanup stale flows daily at 3 AM
if ((int)date('H') === 3) {
    command('flows:cleanup-stale');
}
```

This completes Phase 10.1 - Flow Engine Core. The runtime execution engine is now ready.

**Phase 10.1 includes:**
- ✅ Flow dispatching (keyword trigger detection)
- ✅ Flow run lifecycle management
- ✅ Node execution engine (9 node types)
- ✅ User response handling (buttons, lists, collect input)
- ✅ Variable storage and replacement
- ✅ Input validation
- ✅ Conditional branching
- ✅ Handoff to agent
- ✅ Stale flow cleanup

**Next:** Should I write **Phase 10.2 (Node Types & Configuration)** which details each of the 9 node types and their UI configs?
