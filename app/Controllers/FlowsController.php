<?php

namespace App\Controllers;

use App\Models\FlowModel;
use App\Models\FlowNodeModel;
use App\Models\FlowRunModel;
use App\Models\ProfileModel;
use App\Models\TagModel;
use App\Libraries\FlowNodeSchemas;

class FlowsController extends BaseController
{
    // tag_select/agent_select config fields need real account tags/agents to
    // pick from — without this the editor has nothing to render but a free
    // text box, and a typed tag/agent name can never match a real UUID.
    private function editorLookups(): array
    {
        $tags = (new TagModel())->findAll();

        ProfileModel::setBypassAccountScope(true);
        $agents = (new ProfileModel())->where('account_id', session('account_id'))
            ->whereIn('account_role', ['owner', 'admin', 'agent'])
            ->findAll();
        ProfileModel::setBypassAccountScope(false);

        return ['tags' => $tags, 'agents' => $agents];
    }

    public function index()
    {
        $flowModel = new FlowModel();
        $flows     = $flowModel->orderBy('created_at', 'DESC')->findAll();

        $runModel = new FlowRunModel();
        foreach ($flows as &$f) {
            $f['run_count'] = $runModel->where('flow_id', $f['id'])->countAllResults();
        }
        unset($f);

        return view('flows/index', [
            'pageTitle' => 'Flows',
            'flows'     => $flows,
        ]);
    }

    public function create()
    {
        return view('flows/editor', array_merge([
            'pageTitle'    => 'New Flow',
            'flow'         => [],
            'nodes'        => [],
            'drawflowData' => null,
            'nodeSchemas'  => FlowNodeSchemas::getAllSchemas(),
        ], $this->editorLookups()));
    }

    public function store()
    {
        $body = $this->request->getJSON(true);

        $name     = trim($body['name'] ?? '');
        $keywords = $body['trigger_keywords'] ?? [];
        $isActive = (int)($body['is_active'] ?? 1);
        $flowData = $body['flow_data'] ?? null;

        if (empty($name)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Name is required.']);
        }
        if (empty($keywords)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'At least one trigger keyword is required.']);
        }
        if (!$flowData) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Flow canvas is empty.']);
        }

        $flowModel = new FlowModel();
        $flowId    = $flowModel->insert([
            'name'             => $name,
            'is_active'        => $isActive,
            'trigger_keywords' => json_encode(array_values($keywords)),
            'execution_count'  => 0,
        ]);

        $this->saveNodes($flowId, $flowData);

        return $this->response->setJSON(['success' => true, 'flow_id' => $flowId]);
    }

    public function view(string $flowId)
    {
        $flowModel = new FlowModel();
        $flow = $flowModel->find($flowId);
        if (!$flow) return redirect()->to(base_url('flows'))->with('error', 'Flow not found.');

        $nodes = (new FlowNodeModel())->where('flow_id', $flowId)->findAll();
        $runs  = (new FlowRunModel())->where('flow_id', $flowId)->orderBy('started_at', 'DESC')->findAll(50);

        return view('flows/view', [
            'pageTitle' => $flow['name'],
            'flow'      => $flow,
            'nodes'     => $nodes,
            'runs'      => $runs,
        ]);
    }

    public function edit(string $flowId)
    {
        $flowModel = new FlowModel();
        $flow = $flowModel->find($flowId);
        if (!$flow) return redirect()->to(base_url('flows'))->with('error', 'Flow not found.');

        $nodes        = (new FlowNodeModel())->where('flow_id', $flowId)->findAll();
        $drawflowData = $this->buildDrawflowJson($nodes);

        return view('flows/editor', array_merge([
            'pageTitle'    => 'Edit: ' . $flow['name'],
            'flow'         => $flow,
            'nodes'        => $nodes,
            'drawflowData' => $drawflowData,
            'nodeSchemas'  => FlowNodeSchemas::getAllSchemas(),
        ], $this->editorLookups()));
    }

    public function update(string $flowId)
    {
        $body = $this->request->getJSON(true);

        $flowModel = new FlowModel();
        $flow = $flowModel->find($flowId);
        if (!$flow) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Flow not found.']);
        }

        $name     = trim($body['name'] ?? '');
        $keywords = $body['trigger_keywords'] ?? [];
        $isActive = (int)($body['is_active'] ?? $flow['is_active']);
        $flowData = $body['flow_data'] ?? null;

        if (empty($name)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Name is required.']);
        }

        $flowModel->update($flowId, [
            'name'             => $name,
            'is_active'        => $isActive,
            'trigger_keywords' => json_encode(array_values($keywords)),
        ]);

        if ($flowData) {
            (new FlowNodeModel())->where('flow_id', $flowId)->delete();
            $this->saveNodes($flowId, $flowData);
        }

        return $this->response->setJSON(['success' => true]);
    }

    public function toggle(string $flowId)
    {
        $flowModel = new FlowModel();
        $flow = $flowModel->find($flowId);
        if (!$flow) return redirect()->to(base_url('flows'))->with('error', 'Flow not found.');

        $flowModel->update($flowId, ['is_active' => $flow['is_active'] ? 0 : 1]);
        return redirect()->to(base_url('flows'))->with('success', 'Flow ' . ($flow['is_active'] ? 'paused' : 'activated') . '.');
    }

    public function delete(string $flowId)
    {
        $flowModel = new FlowModel();
        $flow = $flowModel->find($flowId);
        if (!$flow) return redirect()->to(base_url('flows'))->with('error', 'Flow not found.');

        (new FlowNodeModel())->where('flow_id', $flowId)->delete();
        (new FlowRunModel())->where('flow_id', $flowId)->delete();
        $flowModel->delete($flowId);

        return redirect()->to(base_url('flows'))->with('success', 'Flow deleted.');
    }

    public function duplicate(string $flowId)
    {
        $flowModel = new FlowModel();
        $flow = $flowModel->find($flowId);
        if (!$flow) return redirect()->to(base_url('flows'))->with('error', 'Flow not found.');

        $newId = $flowModel->insert([
            'name'             => $flow['name'] . ' (Copy)',
            'is_active'        => 0,
            'trigger_keywords' => $flow['trigger_keywords'],
            'execution_count'  => 0,
        ]);

        $nodes = (new FlowNodeModel())->where('flow_id', $flowId)->findAll();
        $nodeModel = new FlowNodeModel();
        foreach ($nodes as $node) {
            $nodeModel->insert([
                'flow_id'    => $newId,
                'node_key'   => $node['node_key'],
                'node_type'  => $node['node_type'],
                'config'     => $node['config'],
                'position_x' => $node['position_x'],
                'position_y' => $node['position_y'],
            ]);
        }

        return redirect()->to(base_url('flows/' . $newId . '/edit'))->with('success', 'Flow duplicated. You are editing the copy.');
    }

    // ─── Test Console ────────────────────────────────────────────────────────

    public function test(string $flowId)
    {
        $flow = (new FlowModel())->find($flowId);
        if (!$flow) return redirect()->to(base_url('flows'))->with('error', 'Flow not found.');

        // Initialize a fresh test session state
        session()->set($this->testKey($flowId), [
            'current_node_key' => null,
            'vars'             => [],
            'status'           => 'active',
            'waiting_for'      => null,
        ]);

        $nodes = (new FlowNodeModel())->where('flow_id', $flowId)->findAll();
        $runs  = (new FlowRunModel())->where('flow_id', $flowId)->orderBy('started_at', 'DESC')->findAll(50);

        return view('flows/view', [
            'pageTitle'    => $flow['name'],
            'flow'         => $flow,
            'nodes'        => $nodes,
            'runs'         => $runs,
            'drawflowData' => $this->buildDrawflowJson($nodes),
            'activeTab'    => 'test',
        ]);
    }

    public function testMessage(string $flowId)
    {
        $flow = (new FlowModel())->find($flowId);
        if (!$flow) return $this->response->setStatusCode(404)->setJSON(['error' => 'Flow not found']);

        $body    = $this->request->getJSON(true);
        $message = trim($body['message'] ?? '');
        if ($message === '') return $this->response->setJSON(['error' => 'Empty message']);

        $nodes      = (new FlowNodeModel())->where('flow_id', $flowId)->findAll();
        $nodesByKey = array_column($nodes, null, 'node_key');

        $sessionKey = $this->testKey($flowId);
        $state = session($sessionKey) ?? [
            'current_node_key' => null,
            'vars'             => [],
            'status'           => 'active',
            'waiting_for'      => null,
        ];

        $responses = [];

        if ($state['status'] !== 'active') {
            return $this->response->setJSON([
                'responses'    => [['type' => 'system', 'text' => 'Flow already ended. Click Reset to start again.']],
                'current_node' => $state['current_node_key'],
                'vars'         => $state['vars'],
                'is_complete'  => true,
            ]);
        }

        $waitingFor = $state['waiting_for'];
        $currentKey = $state['current_node_key'];

        if ($waitingFor === null && $currentKey === null) {
            // First message — check keyword, then start from start node
            $startNode = null;
            foreach ($nodes as $n) {
                if ($n['node_type'] === 'start') { $startNode = $n; break; }
            }
            if (!$startNode) {
                return $this->response->setJSON(['error' => 'No start node in this flow']);
            }
            $state['current_node_key'] = $startNode['node_key'];
            $state['waiting_for']      = null;
            // Execute from start
            $nextKey = (json_decode($startNode['config'] ?? '{}', true) ?? [])['next_node'] ?? null;
            $this->testExecute($nextKey, $nodesByKey, $state, $responses);

        } elseif ($waitingFor === 'collect_input') {
            $node   = $nodesByKey[$currentKey] ?? null;
            $config = json_decode($node['config'] ?? '{}', true) ?? [];
            $varN   = $config['variable_name'] ?? 'input';

            // Validate if needed
            $validation = $config['validation'] ?? 'none';
            if ($validation && $validation !== 'none') {
                if (!$this->testValidate($message, $validation)) {
                    $errMsg = $config['error_message'] ?? 'Invalid input. Please try again.';
                    $responses[] = ['type' => 'text', 'text' => $errMsg];
                    session()->set($sessionKey, $state);
                    return $this->response->setJSON([
                        'responses'    => $responses,
                        'current_node' => $state['current_node_key'],
                        'vars'         => $state['vars'],
                        'is_complete'  => false,
                    ]);
                }
            }

            $state['vars'][$varN]  = $message;
            $state['waiting_for']  = null;
            $nextKey = $config['next_node'] ?? null;
            $this->testExecute($nextKey, $nodesByKey, $state, $responses);

        } elseif ($waitingFor === 'send_buttons') {
            $node    = $nodesByKey[$currentKey] ?? null;
            $config  = json_decode($node['config'] ?? '{}', true) ?? [];
            $buttons = $config['buttons'] ?? [];
            $matched = null;

            foreach ($buttons as $btn) {
                if (strtolower($btn['id'] ?? '') === strtolower($message)
                    || strtolower($btn['title'] ?? '') === strtolower($message)) {
                    $matched = $btn;
                    break;
                }
            }

            if (!$matched) {
                $titles = implode(' / ', array_column($buttons, 'title'));
                $responses[] = ['type' => 'text', 'text' => "Please choose: {$titles}"];
            } else {
                if (!empty($config['save_to_variable'])) {
                    $state['vars'][$config['save_to_variable']] = $matched['id'];
                }
                $state['waiting_for'] = null;
                $this->testExecute($matched['next_node'] ?? null, $nodesByKey, $state, $responses);
            }

        } elseif ($waitingFor === 'send_list') {
            $node    = $nodesByKey[$currentKey] ?? null;
            $config  = json_decode($node['config'] ?? '{}', true) ?? [];
            $varN    = $config['save_to_variable'] ?? 'list_selection';
            $state['vars'][$varN] = $message;
            $state['waiting_for'] = null;
            $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses);

        } elseif ($waitingFor === 'send_media_buttons') {
            $node    = $nodesByKey[$currentKey] ?? null;
            $config  = json_decode($node['config'] ?? '{}', true) ?? [];
            $buttons = $config['buttons'] ?? [];
            $matched = null;
            foreach ($buttons as $btn) {
                if (strtolower($btn['id'] ?? '') === strtolower($message)
                    || strtolower($btn['title'] ?? '') === strtolower($message)) {
                    $matched = $btn; break;
                }
            }
            if (!$matched) {
                $titles = implode(' / ', array_column($buttons, 'title'));
                $responses[] = ['type' => 'text', 'text' => "Please tap a button: {$titles}"];
            } else {
                if (!empty($config['save_to_variable'])) {
                    $state['vars'][$config['save_to_variable']] = $matched['id'];
                }
                $state['waiting_for'] = null;
                $this->testExecute($matched['next_node'] ?? null, $nodesByKey, $state, $responses);
            }

        } elseif ($waitingFor === 'request_location') {
            // Accept any text as a simulated location in test mode
            $varName = $state['location_var'] ?? null;
            if ($varName) {
                $state['vars'][$varName] = $message;
            }
            $responses[] = ['type' => 'system', 'text' => "Location received: {$message}"];
            $state['waiting_for'] = null;
            unset($state['location_var']);
            $node   = $nodesByKey[$currentKey] ?? null;
            $config = json_decode($node['config'] ?? '{}', true) ?? [];
            $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses);

        } elseif ($waitingFor === 'collect_form') {
            $fields     = $state['form_fields']    ?? [];
            $idx        = (int)($state['form_idx'] ?? 0);
            $field      = $fields[$idx]            ?? null;

            if (!$field) {
                $state['waiting_for'] = null;
                $this->testExecute($state['form_next_node'] ?? null, $nodesByKey, $state, $responses);
            } else {
                $validation = $field['validation'] ?? 'none';
                if ($validation && $validation !== 'none' && !$this->testValidate($message, $validation)) {
                    $responses[] = ['type' => 'text', 'text' => 'Invalid input. Please try again.'];
                } else {
                    $state['vars'][$field['variable_name']] = $message;
                    $idx++;
                    if (isset($fields[$idx])) {
                        $state['form_idx'] = $idx;
                        $responses[] = ['type' => 'text', 'text' => $fields[$idx]['label'] ?? 'Next:'];
                    } else {
                        // All fields done
                        $completion = $state['form_completion'] ?? '';
                        if ($completion) {
                            $responses[] = ['type' => 'text', 'text' => $this->testInterpolate($completion, $state['vars'])];
                        }
                        $nextNode = $state['form_next_node'] ?? null;
                        unset($state['form_fields'], $state['form_idx'], $state['form_next_node'], $state['form_completion']);
                        $state['waiting_for'] = null;
                        $this->testExecute($nextNode, $nodesByKey, $state, $responses);
                    }
                }
            }
        }

        $isComplete = in_array($state['status'], ['completed', 'handed_off', 'failed']);
        session()->set($sessionKey, $state);

        return $this->response->setJSON([
            'responses'    => $responses,
            'current_node' => $state['current_node_key'],
            'vars'         => $state['vars'],
            'is_complete'  => $isComplete,
        ]);
    }

    public function testReset(string $flowId)
    {
        session()->remove($this->testKey($flowId));
        return $this->response->setJSON(['ok' => true]);
    }

    // ─── Test runner (no DB writes, no MetaApi) ───────────────────────────────

    private function testKey(string $flowId): string
    {
        return 'flow_test_' . $flowId;
    }

    private function testExecute(?string $nodeKey, array $nodesByKey, array &$state, array &$responses, int $depth = 0): void
    {
        // A missing next node (e.g. a button with no "Go To" set) previously
        // just returned here silently — the chat looked frozen forever with
        // no feedback and the run stayed "active" indefinitely. The real
        // FlowEngine already treats this as a normal end-of-flow; match that.
        if (!$nodeKey) {
            $responses[]          = ['type' => 'system', 'text' => 'Flow completed — this branch has no next step configured.'];
            $state['status']      = 'completed';
            $state['waiting_for'] = null;
            return;
        }
        if ($depth > 15) {
            $responses[]          = ['type' => 'system', 'text' => 'Flow stopped — too many steps without user input (possible loop).'];
            $state['status']      = 'failed';
            $state['waiting_for'] = null;
            return;
        }

        $node = $nodesByKey[$nodeKey] ?? null;
        if (!$node) {
            $state['status'] = 'completed';
            return;
        }

        $type   = $node['node_type'];
        $config = json_decode($node['config'] ?? '{}', true) ?? [];
        $vars   = $state['vars'];

        $state['current_node_key'] = $nodeKey;

        switch ($type) {
            case 'start':
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'send_message':
                $responses[] = ['type' => 'text', 'text' => $this->testInterpolate($config['message_text'] ?? '', $vars)];
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'send_buttons':
                $responses[] = [
                    'type'    => 'buttons',
                    'text'    => $this->testInterpolate($config['body_text'] ?? '', $vars),
                    'buttons' => $config['buttons'] ?? [],
                ];
                $state['waiting_for'] = 'send_buttons';
                break;

            case 'send_list':
                $responses[] = [
                    'type'     => 'list',
                    'text'     => $this->testInterpolate($config['body_text'] ?? '', $vars),
                    'button'   => $config['button_text'] ?? 'View options',
                    'sections' => $config['sections'] ?? [],
                ];
                $state['waiting_for'] = 'send_list';
                break;

            case 'send_media':
                $responses[] = [
                    'type'       => 'media',
                    'media_type' => $config['media_type'] ?? 'image',
                    'url'        => $config['media_url']  ?? '',
                    'caption'    => $this->testInterpolate($config['caption'] ?? '', $vars),
                ];
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'collect_input':
                $responses[] = ['type' => 'text', 'text' => $this->testInterpolate($config['prompt_text'] ?? '', $vars)];
                $state['waiting_for'] = 'collect_input';
                break;

            case 'condition':
                $pass = false;
                switch ($config['condition_type'] ?? 'variable_equals') {
                    case 'variable_equals':
                        $pass = (string)($vars[$config['variable'] ?? ''] ?? '') === (string)($config['value'] ?? '');
                        break;
                    case 'variable_contains':
                        $pass = stripos((string)($vars[$config['variable'] ?? ''] ?? ''), (string)($config['substring'] ?? '')) !== false;
                        break;
                }
                $responses[] = ['type' => 'system', 'text' => 'Condition → ' . ($pass ? 'TRUE' : 'FALSE')];
                $this->testExecute($pass ? ($config['true_node'] ?? null) : ($config['false_node'] ?? null), $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'send_media_buttons':
                $responses[] = [
                    'type'       => 'media_buttons',
                    'media_type' => $config['media_type'] ?? 'image',
                    'url'        => $config['media_url']  ?? '',
                    'text'       => $this->testInterpolate($config['body_text'] ?? '', $vars),
                    'buttons'    => $config['buttons'] ?? [],
                ];
                $state['waiting_for'] = 'send_media_buttons';
                break;

            case 'url_button':
                $responses[] = [
                    'type'        => 'url_button',
                    'text'        => $this->testInterpolate($config['body_text'] ?? '', $vars),
                    'footer'      => $config['footer_text']  ?? '',
                    'button_text' => $config['button_text']  ?? 'Open',
                    'button_url'  => $config['button_url']   ?? '#',
                ];
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'request_location':
                $responses[] = [
                    'type' => 'location_request',
                    'text' => $this->testInterpolate($config['message_text'] ?? 'Please share your location.', $vars),
                ];
                $state['waiting_for']       = 'request_location';
                $state['location_var']      = $config['variable_name'] ?? null;
                break;

            case 'collect_form':
                $fields = $config['fields'] ?? [];
                if (empty($fields)) {
                    $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                    break;
                }
                $responses[] = ['type' => 'text', 'text' => $fields[0]['label'] ?? 'Please answer:'];
                $state['waiting_for']      = 'collect_form';
                $state['form_fields']      = $fields;
                $state['form_idx']         = 0;
                $state['form_next_node']   = $config['next_node'] ?? null;
                $state['form_completion']  = $config['completion_message'] ?? '';
                break;

            case 'add_to_group':
                $responses[] = ['type' => 'system', 'text' => 'Add to group [test mode — skipped]'];
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'set_tag':
                $action = $config['action'] ?? 'add';
                $responses[] = ['type' => 'system', 'text' => ucfirst($action) . ' tag [test mode — skipped]'];
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'http_request':
                // Real external calls are skipped in test mode (avoid side
                // effects on a third-party system / burning rate limits on
                // repeated test clicks) — same philosophy as add_to_group/set_tag above.
                $responses[] = ['type' => 'system', 'text' => ($config['api_name'] ?? 'HTTP Request') . ' [test mode — skipped, response_mapping vars not populated]'];
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'ai_node':
                // Skipped in test mode — avoids burning real OpenAI usage on
                // every test-console click. Real flows still call it for real.
                $varName = $config['save_to_variable'] ?? 'ai_reply';
                $state['vars'][$varName] = '(AI response — not called in test mode)';
                $responses[] = ['type' => 'system', 'text' => 'AI Node [test mode — skipped, ' . $varName . ' not actually generated]'];
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
                break;

            case 'handoff':
                $msg = $config['handoff_message'] ?? 'Connecting you with our team…';
                if ($msg) $responses[] = ['type' => 'text', 'text' => $msg];
                $responses[] = ['type' => 'system', 'text' => 'Handed off to agent'];
                $state['status']      = 'handed_off';
                $state['waiting_for'] = null;
                break;

            case 'end':
                $responses[] = ['type' => 'system', 'text' => 'Flow completed'];
                $state['status']      = 'completed';
                $state['waiting_for'] = null;
                break;

            default:
                $this->testExecute($config['next_node'] ?? null, $nodesByKey, $state, $responses, $depth + 1);
        }
    }

    private function testInterpolate(string $text, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $text = str_replace('{{' . $k . '}}', (string)$v, $text);
        }
        return $text;
    }

    private function testValidate(string $input, string $type): bool
    {
        return match ($type) {
            'email'  => filter_var($input, FILTER_VALIDATE_EMAIL) !== false,
            'phone'  => (bool)preg_match('/^\+?[1-9]\d{9,14}$/', preg_replace('/\s+/', '', $input)),
            'number' => is_numeric($input),
            default  => trim($input) !== '',
        };
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Parses a Drawflow export, derives connections from edges, and inserts flow_nodes.
     */
    private function saveNodes(string $flowId, array $flowData): void
    {
        $dfNodes = $flowData['drawflow']['Home']['data'] ?? [];
        if (empty($dfNodes)) return;

        // Build a dfId → nodeKey map first
        $keyMap = [];
        foreach ($dfNodes as $dfId => $dfNode) {
            $keyMap[(string)$dfId] = 'df_' . $dfId;
        }

        $nodeModel = new FlowNodeModel();

        foreach ($dfNodes as $dfId => $dfNode) {
            $nodeType = $dfNode['name'] ?? ($dfNode['data']['type'] ?? 'send_message');
            $config   = $dfNode['data']['config'] ?? [];
            $outputs  = $dfNode['outputs'] ?? [];

            // Derive next_node from single output_1 for non-condition nodes
            if ($nodeType !== 'condition') {
                $conn = $outputs['output_1']['connections'][0] ?? null;
                if ($conn) {
                    $config['next_node'] = $keyMap[(string)$conn['node']] ?? null;
                }
                // Sync drawn connections for buttons lists if they exist
                if (isset($config['buttons']) && is_array($config['buttons'])) {
                    $i = 1;
                    foreach ($config['buttons'] as &$btn) {
                        $conn = $outputs['output_' . $i]['connections'][0] ?? null;
                        if ($conn) {
                            $btn['next_node'] = $keyMap[(string)$conn['node']] ?? null;
                        }
                        $i++;
                    }
                    unset($btn);
                }
            } else {
                // Condition: output_1 = true_node, output_2 = false_node
                $trueConn  = $outputs['output_1']['connections'][0] ?? null;
                $falseConn = $outputs['output_2']['connections'][0] ?? null;
                if ($trueConn)  $config['true_node']  = $keyMap[(string)$trueConn['node']]  ?? null;
                if ($falseConn) $config['false_node'] = $keyMap[(string)$falseConn['node']] ?? null;
            }

            $nodeModel->insert([
                'flow_id'    => $flowId,
                'node_key'   => $keyMap[(string)$dfId],
                'node_type'  => $nodeType,
                'config'     => json_encode($config),
                'position_x' => (int)($dfNode['pos_x'] ?? 0),
                'position_y' => (int)($dfNode['pos_y'] ?? 0),
            ]);
        }
    }

    /**
     * Rebuilds Drawflow-compatible JSON from stored flow_nodes for the editor.
     */
    private function buildDrawflowJson(array $nodes): array
    {
        $schemas  = FlowNodeSchemas::getAllSchemas();
        $dfData   = [];
        $nodeCount = 0;

        // Assign numeric Drawflow IDs to node_keys
        $keyToId = [];
        foreach ($nodes as $idx => $node) {
            $dfId = $idx + 1;
            $keyToId[$node['node_key']] = $dfId;
        }

        foreach ($nodes as $idx => $node) {
            $dfId    = $idx + 1;
            $nodeKey = $node['node_key'];
            $type    = $node['node_type'];
            $schema  = $schemas[$type] ?? [];
            $config  = json_decode($node['config'] ?? '{}', true) ?? [];

            $hasSingleOutput   = $schema['has_single_output']   ?? false;
            $hasMultipleOutput = $schema['has_multiple_outputs'] ?? false;

            // Build output connections from stored config
            $outputs = [];
            if ($hasSingleOutput) {
                $nextId = isset($config['next_node']) ? ($keyToId[$config['next_node']] ?? null) : null;
                $outputs['output_1'] = [
                    'connections' => $nextId ? [['node' => (string)$nextId, 'output' => 'input_1']] : [],
                ];
            } elseif ($hasMultipleOutput) {
                if ($type === 'condition') {
                    $trueId  = isset($config['true_node'])  ? ($keyToId[$config['true_node']]  ?? null) : null;
                    $falseId = isset($config['false_node']) ? ($keyToId[$config['false_node']] ?? null) : null;
                    $outputs['output_1'] = ['connections' => $trueId  ? [['node' => (string)$trueId,  'output' => 'input_1']] : []];
                    $outputs['output_2'] = ['connections' => $falseId ? [['node' => (string)$falseId, 'output' => 'input_1']] : []];
                } elseif (isset($config['buttons']) && is_array($config['buttons'])) {
                    $i = 1;
                    foreach ($config['buttons'] as $btn) {
                        $targetNode = $btn['next_node'] ?? null;
                        $targetId   = $targetNode ? ($keyToId[$targetNode] ?? null) : null;
                        $outputs['output_' . $i] = [
                            'connections' => $targetId ? [['node' => (string)$targetId, 'output' => 'input_1']] : [],
                        ];
                        $i++;
                    }
                }
            }

            $inputs = ($type === 'start') ? [] : ['input_1' => ['connections' => []]];

            $dfData[(string)$dfId] = [
                'id'       => $dfId,
                'name'     => $type,
                'data'     => ['type' => $type, 'config' => $config, 'node_key' => $nodeKey],
                'class'    => $type,
                'html'     => '',
                'typenode' => false,
                'inputs'   => $inputs,
                'outputs'  => $outputs,
                'pos_x'    => (int)$node['position_x'],
                'pos_y'    => (int)$node['position_y'],
            ];

            $nodeCount = max($nodeCount, $dfId);
        }

        return [
            'drawflow' => ['Home' => ['data' => $dfData]],
        ];
    }
}
