### Prompt 10.2 — Node Types & Configuration

```
Define all node types and their configuration schemas for Rovix AI Leads Tool flows.

Reference: src/components/flows/nodes/ (various node components in wacrm)

IMPORTANT: Each node type has specific config schema. The visual editor (10.3) will use these schemas to build the config UI.

Create app/Libraries/FlowNodeSchemas.php:

<?php
namespace App\Libraries;

class FlowNodeSchemas
{
    /**
     * Get schema for a specific node type
     * Used by flow editor to render config forms
     */
    public static function getSchema(string $nodeType): array
    {
        return match($nodeType) {
            'start' => self::startSchema(),
            'send_message' => self::sendMessageSchema(),
            'send_buttons' => self::sendButtonsSchema(),
            'send_list' => self::sendListSchema(),
            'send_media' => self::sendMediaSchema(),
            'collect_input' => self::collectInputSchema(),
            'condition' => self::conditionSchema(),
            'set_tag' => self::setTagSchema(),
            'handoff' => self::handoffSchema(),
            'end' => self::endSchema(),
            default => []
        };
    }

    private static function startSchema(): array
    {
        return [
            'name' => 'Start',
            'description' => 'Entry point of the flow',
            'icon' => '▶️',
            'color' => '#10B981',
            'config_fields' => [
                [
                    'name' => 'next_node',
                    'label' => 'Next Node',
                    'type' => 'node_select',
                    'required' => true
                ]
            ],
            'has_single_output' => true
        ];
    }

    private static function sendMessageSchema(): array
    {
        return [
            'name' => 'Send Message',
            'description' => 'Send a text message',
            'icon' => '💬',
            'color' => '#3B82F6',
            'config_fields' => [
                [
                    'name' => 'message_text',
                    'label' => 'Message Text',
                    'type' => 'textarea',
                    'required' => true,
                    'placeholder' => 'Type your message here. Use {{variable}} for dynamic content.',
                    'max_length' => 1024
                ],
                [
                    'name' => 'next_node',
                    'label' => 'Next Node',
                    'type' => 'node_select',
                    'required' => true
                ]
            ],
            'has_single_output' => true
        ];
    }

    private static function sendButtonsSchema(): array
    {
        return [
            'name' => 'Send Buttons',
            'description' => 'Send message with interactive buttons (max 3)',
            'icon' => '🔘',
            'color' => '#8B5CF6',
            'config_fields' => [
                [
                    'name' => 'body_text',
                    'label' => 'Message Text',
                    'type' => 'textarea',
                    'required' => true,
                    'max_length' => 1024
                ],
                [
                    'name' => 'buttons',
                    'label' => 'Buttons',
                    'type' => 'button_list',
                    'required' => true,
                    'min_items' => 1,
                    'max_items' => 3,
                    'item_schema' => [
                        ['name' => 'id', 'type' => 'text', 'label' => 'Button ID'],
                        ['name' => 'title', 'type' => 'text', 'label' => 'Button Text', 'max_length' => 20],
                        ['name' => 'next_node', 'type' => 'node_select', 'label' => 'Go To']
                    ]
                ],
                [
                    'name' => 'save_to_variable',
                    'label' => 'Save Selection To Variable',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'variable_name'
                ]
            ],
            'has_multiple_outputs' => true
        ];
    }

    private static function sendListSchema(): array
    {
        return [
            'name' => 'Send List',
            'description' => 'Send message with interactive list (up to 10 items)',
            'icon' => '📋',
            'color' => '#F59E0B',
            'config_fields' => [
                [
                    'name' => 'body_text',
                    'label' => 'Message Text',
                    'type' => 'textarea',
                    'required' => true,
                    'max_length' => 1024
                ],
                [
                    'name' => 'button_text',
                    'label' => 'Button Text',
                    'type' => 'text',
                    'required' => true,
                    'max_length' => 20,
                    'placeholder' => 'e.g., "View Options"'
                ],
                [
                    'name' => 'sections',
                    'label' => 'List Sections',
                    'type' => 'list_sections',
                    'required' => true,
                    'max_sections' => 10,
                    'section_schema' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Section Title'],
                        [
                            'name' => 'rows',
                            'type' => 'list',
                            'label' => 'Items',
                            'item_schema' => [
                                ['name' => 'id', 'type' => 'text', 'label' => 'ID'],
                                ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'max_length' => 24],
                                ['name' => 'description', 'type' => 'text', 'label' => 'Description', 'max_length' => 72]
                            ]
                        ]
                    ]
                ],
                [
                    'name' => 'save_to_variable',
                    'label' => 'Save Selection To Variable',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'next_node',
                    'label' => 'Next Node',
                    'type' => 'node_select',
                    'required' => true
                ]
            ],
            'has_single_output' => true
        ];
    }

    private static function sendMediaSchema(): array
    {
        return [
            'name' => 'Send Media',
            'description' => 'Send image, video, or document',
            'icon' => '🖼️',
            'color' => '#EC4899',
            'config_fields' => [
                [
                    'name' => 'media_type',
                    'label' => 'Media Type',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'image', 'label' => 'Image'],
                        ['value' => 'video', 'label' => 'Video'],
                        ['value' => 'document', 'label' => 'Document']
                    ]
                ],
                [
                    'name' => 'media_url',
                    'label' => 'Media URL',
                    'type' => 'url',
                    'required' => true,
                    'placeholder' => 'https://example.com/image.jpg'
                ],
                [
                    'name' => 'caption',
                    'label' => 'Caption (optional)',
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 1024
                ],
                [
                    'name' => 'next_node',
                    'label' => 'Next Node',
                    'type' => 'node_select',
                    'required' => true
                ]
            ],
            'has_single_output' => true
        ];
    }

    private static function collectInputSchema(): array
    {
        return [
            'name' => 'Collect Input',
            'description' => 'Ask a question and save response',
            'icon' => '✍️',
            'color' => '#06B6D4',
            'config_fields' => [
                [
                    'name' => 'prompt_text',
                    'label' => 'Question to Ask',
                    'type' => 'textarea',
                    'required' => true,
                    'placeholder' => 'What is your email address?'
                ],
                [
                    'name' => 'variable_name',
                    'label' => 'Save Response As',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'user_email',
                    'pattern' => '^[a-z_][a-z0-9_]*$',
                    'help' => 'Variable name (lowercase, underscores only)'
                ],
                [
                    'name' => 'validation',
                    'label' => 'Validation',
                    'type' => 'validation_rules',
                    'required' => false,
                    'options' => [
                        ['value' => 'none', 'label' => 'No validation'],
                        ['value' => 'email', 'label' => 'Email address'],
                        ['value' => 'phone', 'label' => 'Phone number'],
                        ['value' => 'number', 'label' => 'Number'],
                        ['value' => 'min_length', 'label' => 'Minimum length'],
                        ['value' => 'max_length', 'label' => 'Maximum length']
                    ]
                ],
                [
                    'name' => 'error_message',
                    'label' => 'Error Message',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'Invalid input. Please try again.',
                    'show_if' => 'validation != none'
                ],
                [
                    'name' => 'next_node',
                    'label' => 'Next Node',
                    'type' => 'node_select',
                    'required' => true
                ]
            ],
            'has_single_output' => true
        ];
    }

    private static function conditionSchema(): array
    {
        return [
            'name' => 'Condition',
            'description' => 'Branch based on a condition',
            'icon' => '🔀',
            'color' => '#EF4444',
            'config_fields' => [
                [
                    'name' => 'condition_type',
                    'label' => 'Condition Type',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'variable_equals', 'label' => 'Variable equals value'],
                        ['value' => 'variable_contains', 'label' => 'Variable contains text'],
                        ['value' => 'contact_has_tag', 'label' => 'Contact has tag']
                    ]
                ],
                [
                    'name' => 'variable',
                    'label' => 'Variable Name',
                    'type' => 'text',
                    'required' => true,
                    'show_if' => 'condition_type in [variable_equals, variable_contains]'
                ],
                [
                    'name' => 'value',
                    'label' => 'Expected Value',
                    'type' => 'text',
                    'required' => true,
                    'show_if' => 'condition_type == variable_equals'
                ],
                [
                    'name' => 'substring',
                    'label' => 'Text to Find',
                    'type' => 'text',
                    'required' => true,
                    'show_if' => 'condition_type == variable_contains'
                ],
                [
                    'name' => 'tag_id',
                    'label' => 'Tag',
                    'type' => 'tag_select',
                    'required' => true,
                    'show_if' => 'condition_type == contact_has_tag'
                ],
                [
                    'name' => 'true_node',
                    'label' => 'If TRUE, Go To',
                    'type' => 'node_select',
                    'required' => true
                ],
                [
                    'name' => 'false_node',
                    'label' => 'If FALSE, Go To',
                    'type' => 'node_select',
                    'required' => true
                ]
            ],
            'has_multiple_outputs' => true,
            'outputs' => ['true', 'false']
        ];
    }

    private static function setTagSchema(): array
    {
        return [
            'name' => 'Set Tag',
            'description' => 'Add or remove a tag from contact',
            'icon' => '🏷️',
            'color' => '#14B8A6',
            'config_fields' => [
                [
                    'name' => 'action',
                    'label' => 'Action',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'add', 'label' => 'Add Tag'],
                        ['value' => 'remove', 'label' => 'Remove Tag']
                    ]
                ],
                [
                    'name' => 'tag_id',
                    'label' => 'Tag',
                    'type' => 'tag_select',
                    'required' => true
                ],
                [
                    'name' => 'next_node',
                    'label' => 'Next Node',
                    'type' => 'node_select',
                    'required' => true
                ]
            ],
            'has_single_output' => true
        ];
    }

    private static function handoffSchema(): array
    {
        return [
            'name' => 'Handoff to Agent',
            'description' => 'Transfer conversation to human agent',
            'icon' => '👤',
            'color' => '#6366F1',
            'config_fields' => [
                [
                    'name' => 'agent_id',
                    'label' => 'Assign To Agent',
                    'type' => 'agent_select',
                    'required' => false,
                    'help' => 'Leave empty to assign to any available agent'
                ],
                [
                    'name' => 'handoff_message',
                    'label' => 'Handoff Message',
                    'type' => 'textarea',
                    'required' => false,
                    'placeholder' => 'Connecting you with an agent...',
                    'max_length' => 200
                ]
            ],
            'has_single_output' => false, // Flow ends here
            'terminates_flow' => true
        ];
    }

    private static function endSchema(): array
    {
        return [
            'name' => 'End',
            'description' => 'End the flow',
            'icon' => '🏁',
            'color' => '#6B7280',
            'config_fields' => [],
            'has_single_output' => false,
            'terminates_flow' => true
        ];
    }

    /**
     * Get all available node types
     */
    public static function getAllTypes(): array
    {
        return [
            'start',
            'send_message',
            'send_buttons',
            'send_list',
            'send_media',
            'collect_input',
            'condition',
            'set_tag',
            'handoff',
            'end'
        ];
    }
}

Create app/Controllers/Api/FlowNodesController.php:

<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\FlowNodeSchemas;

class FlowNodesController extends BaseController
{
    /**
     * GET /api/flows/node-types
     * Return all available node types with their schemas
     */
    public function getNodeTypes()
    {
        $types = FlowNodeSchemas::getAllTypes();
        $schemas = [];

        foreach ($types as $type) {
            $schemas[$type] = FlowNodeSchemas::getSchema($type);
        }

        return $this->response->setJSON($schemas);
    }

    /**
     * GET /api/flows/node-types/{type}
     * Return schema for specific node type
     */
    public function getNodeType($type)
    {
        $schema = FlowNodeSchemas::getSchema($type);

        if (empty($schema)) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Node type not found'
            ]);
        }

        return $this->response->setJSON($schema);
    }
}

Add routes:
GET /api/flows/node-types → Api\FlowNodesController::getNodeTypes
GET /api/flows/node-types/{type} → Api\FlowNodesController::getNodeType
```

This completes Phase 10.2 - Node Types & Configuration.

**Phase 10.2 includes:**
- ✅ 10 node type schemas defined
- ✅ Config field definitions for each type
- ✅ Validation rules and constraints
- ✅ Conditional field display logic (show_if)
- ✅ Output/branching configuration
- ✅ API endpoint to fetch schemas for UI builder

**Next:** Should I write **Phase 10.3 (Drawflow.js Visual Editor)** which builds the drag-and-drop flow canvas?
