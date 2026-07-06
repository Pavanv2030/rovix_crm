<?php

namespace Tests\Libraries;

use App\Libraries\FlowEngine;
use App\Models\ContactModel;
use App\Models\ContactTagModel;
use App\Models\ContactCustomValueModel;
use App\Models\CustomFieldModel;
use App\Models\ConversationModel;
use App\Models\FlowModel;
use App\Models\FlowNodeModel;
use App\Models\FlowRunModel;
use App\Models\FlowRunEventModel;
use App\Models\WhatsAppConfigModel;
use App\Models\TagModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class FlowEngineTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    private string $accountId;
    private string $contactId;
    private string $conversationId;
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->history = [];
        $this->setupCurlMock();

        // Create standard test references
        $this->accountId = 'acc-' . bin2hex(random_bytes(4));
        
        // Insert a dummy account
        $this->db->table('accounts')->insert([
            'id'   => $this->accountId,
            'name' => 'Test Account',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Insert WhatsApp Config
        (new WhatsAppConfigModel())->insert([
            'account_id' => $this->accountId,
            'phone_number_id' => '1234567890',
            'access_token' => (new \App\Libraries\WhatsApp\Encryption())->encrypt('mock-token'),
            'status' => 'connected',
            'phone_normalized' => '1234567890',
        ]);

        // Insert Contact
        $this->contactId = (new ContactModel())->insert([
            'account_id' => $this->accountId,
            'phone' => '+15550000001',
            'phone_normalized' => '15550000001',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Insert Conversation
        $this->conversationId = (new ConversationModel())->insert([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'status' => 'open',
        ]);
    }

    private function setupCurlMock(): void
    {
        $mockClient = $this->getMockBuilder(\CodeIgniter\HTTP\CURLRequest::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockClient->method('get')->willReturnCallback(function($url, $options = []) {
            $this->history[] = [
                'method'  => 'GET',
                'url'     => $url,
                'options' => $options,
            ];
            return $this->createMockResponse(200, '{"success":true}');
        });

        $mockClient->method('post')->willReturnCallback(function($url, $options = []) {
            $this->history[] = [
                'method'  => 'POST',
                'url'     => $url,
                'options' => $options,
            ];
            return $this->createMockResponse(200, '{"success":true, "id": "msg-123"}');
        });

        $mockClient->method('delete')->willReturnCallback(function($url, $options = []) {
            $this->history[] = [
                'method'  => 'DELETE',
                'url'     => $url,
                'options' => $options,
            ];
            return $this->createMockResponse(200, '{"success":true}');
        });

        $mockClient->method('put')->willReturnCallback(function($url, $options = []) {
            $this->history[] = [
                'method'  => 'PUT',
                'url'     => $url,
                'options' => $options,
            ];
            return $this->createMockResponse(200, '{"success":true}');
        });

        $mockClient->method('request')->willReturnCallback(function($method, $url, $options = []) {
            $this->history[] = [
                'method'  => $method,
                'url'     => $url,
                'options' => $options,
            ];
            if (str_contains($url, 'my-mock-api.com')) {
                return $this->createMockResponse(201, '{"status":"ok", "data":{"user_role":"moderator"}}');
            }
            return $this->createMockResponse(200, '{"success":true}');
        });

        Services::injectMock('curlrequest', $mockClient);
    }

    private function createMockResponse(int $statusCode, string $body)
    {
        $mockResponse = $this->getMockBuilder(\CodeIgniter\HTTP\Response::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        $mockResponse->method('getBody')->willReturn($body);
        return $mockResponse;
    }

    private function createFlow(string $name, array $keywords, array $nodes): string
    {
        $flowId = (new FlowModel())->insert([
            'account_id' => $this->accountId,
            'name' => $name,
            'is_active' => 1,
            'trigger_keywords' => json_encode($keywords),
        ]);

        foreach ($nodes as $node) {
            (new FlowNodeModel())->insert([
                'flow_id' => $flowId,
                'node_key' => $node['node_key'],
                'node_type' => $node['node_type'],
                'config' => json_encode($node['config']),
            ]);
        }

        return $flowId;
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function testFlowTriggeredByKeyword(): void
    {
        $this->createFlow('Welcome Flow', ['hello', 'hey'], [
            [
                'node_key' => 'start-node',
                'node_type' => 'start',
                'config' => ['next_node' => 'msg-node']
            ],
            [
                'node_key' => 'msg-node',
                'node_type' => 'send_message',
                'config' => ['message_text' => 'Hi John Doe!', 'next_node' => 'end-node']
            ],
            [
                'node_key' => 'end-node',
                'node_type' => 'end',
                'config' => []
            ]
        ]);

        $engine = new FlowEngine();
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'hey there',
        ]);

        // Verify run was created and completed
        $runs = (new FlowRunModel())->findAll();
        $this->assertCount(1, $runs);
        $this->assertSame('completed', $runs[0]['status']);
        $this->assertSame('end-node', $runs[0]['current_node_key']);

        // Verify Meta API text send was called
        $this->assertCount(1, $this->history);
        $this->assertSame('POST', $this->history[0]['method']);
        $this->assertStringContainsString('/messages', $this->history[0]['url']);
        $this->assertSame('Hi John Doe!', $this->history[0]['options']['json']['text']['body']);
    }

    public function testCollectInputValidationAndSyncing(): void
    {
        $flowId = $this->createFlow('Collect Email Flow', ['register'], [
            [
                'node_key' => 'start-node',
                'node_type' => 'start',
                'config' => ['next_node' => 'collect-node']
            ],
            [
                'node_key' => 'collect-node',
                'node_type' => 'collect_input',
                'config' => [
                    'variable_name' => 'user_email',
                    'validation' => [
                        'type' => 'email',
                        'error_message' => 'Please enter a valid email address.'
                    ],
                    'next_node' => 'thank-you-node'
                ]
            ],
            [
                'node_key' => 'thank-you-node',
                'node_type' => 'send_message',
                'config' => ['message_text' => 'Thank you for registering!', 'next_node' => 'end-node']
            ],
            [
                'node_key' => 'end-node',
                'node_type' => 'end',
                'config' => []
            ]
        ]);

        $engine = new FlowEngine();

        // 1. Trigger the flow
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'register now',
        ]);

        $runs = (new FlowRunModel())->findAll();
        $this->assertCount(1, $runs);
        $this->assertSame('active', $runs[0]['status']);
        $this->assertSame('collect-node', $runs[0]['current_node_key']);

        // Check prompt was sent (Meta API text post)
        $this->assertCount(1, $this->history);
        $this->assertSame('Please enter a valid email address.', $this->history[0]['options']['json']['text']['body']);

        // Clear history for clean asserts
        $this->history = [];

        // 2. Submit invalid email
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'not-an-email',
        ]);

        // Run should still be active on collect-node
        $runs = (new FlowRunModel())->findAll();
        $this->assertSame('active', $runs[0]['status']);
        $this->assertSame('collect-node', $runs[0]['current_node_key']);

        // Check error prompt was sent again
        $this->assertCount(1, $this->history);
        $this->assertSame('Please enter a valid email address.', $this->history[0]['options']['json']['text']['body']);

        $this->history = [];

        // 3. Submit valid email
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'hello@google.com',
        ]);

        // Run should now be completed
        $runs = (new FlowRunModel())->findAll();
        $this->assertSame('completed', $runs[0]['status']);
        $this->assertSame('end-node', $runs[0]['current_node_key']);

        // Verify variables stored in run
        $vars = json_decode($runs[0]['vars'], true);
        $this->assertSame('hello@google.com', $vars['user_email']);

        // Verify synced to contact custom fields
        $field = (new CustomFieldModel())->where('account_id', $this->accountId)->where('field_name', 'user_email')->first();
        $this->assertNotNull($field);
        $val = (new ContactCustomValueModel())->where('contact_id', $this->contactId)->where('custom_field_id', $field['id'])->first();
        $this->assertNotNull($val);
        $this->assertSame('hello@google.com', $val['value']);

        // Verify thank you message was sent
        $this->assertCount(1, $this->history);
        $this->assertSame('Thank you for registering!', $this->history[0]['options']['json']['text']['body']);
    }

    public function testConditionAndTagging(): void
    {
        // Add tags
        (new TagModel())->insert(['id' => 'tag-vip', 'name' => 'VIP', 'account_id' => $this->accountId]);
        (new TagModel())->insert(['id' => 'tag-standard', 'name' => 'Standard', 'account_id' => $this->accountId]);

        $this->createFlow('Segment Flow', ['segment'], [
            [
                'node_key' => 'start-node',
                'node_type' => 'start',
                'config' => ['next_node' => 'collect-score']
            ],
            [
                'node_key' => 'collect-score',
                'node_type' => 'collect_input',
                'config' => [
                    'variable_name' => 'score',
                    'next_node' => 'condition-node'
                ]
            ],
            [
                'node_key' => 'condition-node',
                'node_type' => 'condition',
                'config' => [
                    'condition_type' => 'variable_equals',
                    'variable' => 'score',
                    'value' => '10',
                    'true_node' => 'tag-vip-node',
                    'false_node' => 'tag-std-node'
                ]
            ],
            [
                'node_key' => 'tag-vip-node',
                'node_type' => 'set_tag',
                'config' => [
                    'tag_id' => 'tag-vip',
                    'action' => 'add',
                    'next_node' => 'end-node'
                ]
            ],
            [
                'node_key' => 'tag-std-node',
                'node_type' => 'set_tag',
                'config' => [
                    'tag_id' => 'tag-standard',
                    'action' => 'add',
                    'next_node' => 'end-node'
                ]
            ],
            [
                'node_key' => 'end-node',
                'node_type' => 'end',
                'config' => []
            ]
        ]);

        $engine = new FlowEngine();

        // Scenario A: Score is 10 (True path)
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'segment',
        ]);
        
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => '10',
        ]);

        // Check standard tag VIP exists on contact
        $vipTag = (new ContactTagModel())->where('contact_id', $this->contactId)->where('tag_id', 'tag-vip')->first();
        $this->assertNotNull($vipTag);

        // Delete runs for next scenario
        (new FlowRunModel())->truncate();
        (new ContactTagModel())->truncate();

        // Scenario B: Score is 5 (False path)
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'segment',
        ]);

        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => '5',
        ]);

        // Check standard tag exists
        $stdTag = (new ContactTagModel())->where('contact_id', $this->contactId)->where('tag_id', 'tag-standard')->first();
        $this->assertNotNull($stdTag);
    }

    public function testButtonsAndStaleVariablesPropagation(): void
    {
        $this->createFlow('Button Flow', ['options'], [
            [
                'node_key' => 'start-node',
                'node_type' => 'start',
                'config' => ['next_node' => 'buttons-node']
            ],
            [
                'node_key' => 'buttons-node',
                'node_type' => 'send_buttons',
                'config' => [
                    'body_text' => 'Do you like PHP?',
                    'save_to_variable' => 'likes_php',
                    'buttons' => [
                        ['id' => 'btn-yes', 'title' => 'Yes', 'next_node' => 'check-choice'],
                        ['id' => 'btn-no', 'title' => 'No', 'next_node' => 'check-choice']
                    ]
                ]
            ],
            [
                'node_key' => 'check-choice',
                'node_type' => 'condition',
                'config' => [
                    'condition_type' => 'variable_equals',
                    'variable' => 'likes_php',
                    'value' => 'btn-yes',
                    'true_node' => 'msg-yes-node',
                    'false_node' => 'msg-no-node'
                ]
            ],
            [
                'node_key' => 'msg-yes-node',
                'node_type' => 'send_message',
                'config' => ['message_text' => 'Awesome, PHP is great!', 'next_node' => 'end-node']
            ],
            [
                'node_key' => 'msg-no-node',
                'node_type' => 'send_message',
                'config' => ['message_text' => 'Oh, too bad.', 'next_node' => 'end-node']
            ],
            [
                'node_key' => 'end-node',
                'node_type' => 'end',
                'config' => []
            ]
        ]);

        $engine = new FlowEngine();

        // Trigger
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'options',
        ]);

        $this->history = [];

        // Click 'Yes' (btn-yes)
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'button_reply_id' => 'btn-yes',
        ]);

        // Verify flow ended as completed
        $runs = (new FlowRunModel())->findAll();
        $this->assertSame('completed', $runs[0]['status']);
        
        // Verify choice saved
        $vars = json_decode($runs[0]['vars'], true);
        $this->assertSame('btn-yes', $vars['likes_php']);

        // Verify correct message was sent (regression test for stale vars propagation bug)
        $this->assertCount(1, $this->history);
        $this->assertSame('Awesome, PHP is great!', $this->history[0]['options']['json']['text']['body']);
    }

    public function testHttpRequestNodeAndStaleVariablesPropagation(): void
    {
        $this->createFlow('HTTP Flow', ['test-http'], [
            [
                'node_key' => 'start-node',
                'node_type' => 'start',
                'config' => ['next_node' => 'http-node']
            ],
            [
                'node_key' => 'http-node',
                'node_type' => 'http_request',
                'config' => [
                    'method' => 'POST',
                    'url' => 'https://my-mock-api.com/verify-role',
                    'status_variable' => 'api_status',
                    'response_mapping' => [
                        ['response_path' => 'data.user_role', 'variable_name' => 'role']
                    ],
                    'next_node' => 'check-role-node'
                ]
            ],
            [
                'node_key' => 'check-role-node',
                'node_type' => 'condition',
                'config' => [
                    'condition_type' => 'variable_equals',
                    'variable' => 'role',
                    'value' => 'moderator',
                    'true_node' => 'msg-mod-node',
                    'false_node' => 'msg-other-node'
                ]
            ],
            [
                'node_key' => 'msg-mod-node',
                'node_type' => 'send_message',
                'config' => ['message_text' => 'Welcome Moderator!', 'next_node' => 'end-node']
            ],
            [
                'node_key' => 'msg-other-node',
                'node_type' => 'send_message',
                'config' => ['message_text' => 'Welcome Guest!', 'next_node' => 'end-node']
            ],
            [
                'node_key' => 'end-node',
                'node_type' => 'end',
                'config' => []
            ]
        ]);

        $engine = new FlowEngine();

        // Triggering this should run all nodes synchronously including the HTTP request
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'test-http',
        ]);

        // Verify completed
        $runs = (new FlowRunModel())->findAll();
        $this->assertSame('completed', $runs[0]['status']);
        $this->assertSame('end-node', $runs[0]['current_node_key']);

        // Verify variables saved
        $vars = json_decode($runs[0]['vars'], true);
        $this->assertSame(201, $vars['api_status']);
        $this->assertSame('moderator', $vars['role']);

        // Verify correct message was sent (regression test for stale vars propagation bug in HTTP requests)
        // There should be 1 outbound HTTP webhook request to Meta API (Welcome Moderator!)
        // and 1 mocked curlrequest request to my-mock-api.com
        $metaMsgSent = null;
        foreach ($this->history as $req) {
            if ($req['method'] === 'POST' && str_contains($req['url'], '/messages')) {
                $metaMsgSent = $req['options']['json']['text']['body'];
            }
        }
        
        $this->assertNotNull($metaMsgSent);
        $this->assertSame('Welcome Moderator!', $metaMsgSent);
    }

    public function testCollectFormValidationAndSyncing(): void
    {
        $this->createFlow('Form Flow', ['form-trigger'], [
            [
                'node_key' => 'start-node',
                'node_type' => 'start',
                'config' => ['next_node' => 'form-node']
            ],
            [
                'node_key' => 'form-node',
                'node_type' => 'collect_form',
                'config' => [
                    'fields' => [
                        ['label' => 'What is your email?', 'variable_name' => 'contact_email', 'validation' => 'email'],
                        ['label' => 'What is your phone?', 'variable_name' => 'contact_phone', 'validation' => 'phone']
                    ],
                    'completion_message' => 'Thank you for filling out the form!',
                    'next_node' => 'end-node'
                ]
            ],
            [
                'node_key' => 'end-node',
                'node_type' => 'end',
                'config' => []
            ]
        ]);

        $engine = new FlowEngine();

        // 1. Trigger flow
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'form-trigger',
        ]);

        $runs = (new FlowRunModel())->findAll();
        $this->assertCount(1, $runs);
        $this->assertSame('active', $runs[0]['status']);
        $this->assertSame('form-node', $runs[0]['current_node_key']);

        // Check first question was sent
        $this->assertCount(1, $this->history);
        $this->assertSame('What is your email?', $this->history[0]['options']['json']['text']['body']);
        $this->history = [];

        // 2. Submit invalid email to form
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'invalid-email',
        ]);

        // Should still be active on form-node, first question index (0)
        $runs = (new FlowRunModel())->findAll();
        $this->assertSame('active', $runs[0]['status']);
        $vars = json_decode($runs[0]['vars'], true);
        $this->assertSame(0, $vars['_cf_form-node_idx']);

        // Check error prompt sent
        $this->assertCount(1, $this->history);
        $this->assertSame('Invalid input. Please try again.', $this->history[0]['options']['json']['text']['body']);
        $this->history = [];

        // 3. Submit valid email to form
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => 'test@google.com',
        ]);

        // Should still be active on form-node, but now on second question index (1)
        $runs = (new FlowRunModel())->findAll();
        $this->assertSame('active', $runs[0]['status']);
        $vars = json_decode($runs[0]['vars'], true);
        $this->assertSame(1, $vars['_cf_form-node_idx']);
        $this->assertSame('test@google.com', $vars['contact_email']);

        // Check second question was sent
        $this->assertCount(1, $this->history);
        $this->assertSame('What is your phone?', $this->history[0]['options']['json']['text']['body']);
        $this->history = [];

        // 4. Submit valid phone to form
        $engine->dispatchInbound([
            'account_id' => $this->accountId,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_text' => '+15551234567',
        ]);

        // Flow should now be completed
        $runs = (new FlowRunModel())->findAll();
        $this->assertSame('completed', $runs[0]['status']);
        $vars = json_decode($runs[0]['vars'], true);
        $this->assertSame('+15551234567', $vars['contact_phone']);
        $this->assertArrayNotHasKey('_cf_form-node_idx', $vars); // cleaned up

        // Verify completion message and Meta API send calls
        $this->assertCount(1, $this->history);
        $this->assertSame('Thank you for filling out the form!', $this->history[0]['options']['json']['text']['body']);
    }
}
