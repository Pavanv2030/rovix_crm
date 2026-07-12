<?php

namespace App\Libraries;

class FlowNodeSchemas
{
    /**
     * Returns the UI schema for a node type.
     * Used by the flow editor to render config panels.
     */
    public static function getSchema(string $nodeType): array
    {
        return match ($nodeType) {
            'start'              => self::start(),
            'send_message'       => self::sendMessage(),
            'send_buttons'       => self::sendButtons(),
            'send_list'          => self::sendList(),
            'send_media'         => self::sendMedia(),
            'send_media_buttons' => self::sendMediaButtons(),
            'url_button'         => self::urlButton(),
            'request_location'   => self::requestLocation(),
            'collect_input'      => self::collectInput(),
            'collect_form'       => self::collectForm(),
            'condition'          => self::condition(),
            'set_tag'            => self::setTag(),
            'add_to_group'       => self::addToGroup(),
            'handoff'            => self::handoff(),
            'end'                => self::end(),
            'send_catalog'       => self::sendCatalog(),
            'send_product'       => self::sendProduct(),
            'send_template'      => self::sendTemplate(),
            'appointment_booking' => self::appointmentBooking(),
            'http_request'       => self::httpRequest(),
            'ai_node'            => self::aiNode(),
            'trigger_flow'       => self::triggerFlow(),
            default              => [],
        };
    }

    public static function getAllTypes(): array
    {
        return [
            'start', 'send_message', 'send_buttons', 'send_list',
            'send_media', 'send_media_buttons', 'url_button',
            'request_location', 'collect_input', 'collect_form',
            'condition', 'set_tag', 'add_to_group', 'handoff', 'end',
            'send_catalog', 'send_product', 'send_template', 'appointment_booking',
            'http_request', 'ai_node', 'trigger_flow',
        ];
    }

    /**
     * Returns all schemas keyed by type — for bulk loading by the editor.
     */
    public static function getAllSchemas(): array
    {
        $result = [];
        foreach (self::getAllTypes() as $type) {
            $result[$type] = self::getSchema($type);
        }
        return $result;
    }

    // ─── Node schemas ────────────────────────────────────────────────────────

    private static function start(): array
    {
        return [
            'name'               => 'Start',
            'description'        => 'Entry point — triggered by a keyword',
            'icon'               => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.84Z"/></svg>',
            'color'              => '#10B981',
            'has_single_output'  => true,
            'terminates_flow'    => false,
            'config_fields'      => [
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function sendMessage(): array
    {
        return [
            'name'              => 'Send Message',
            'description'       => 'Send a WhatsApp text message',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M2 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6l-4 4V5Z" clip-rule="evenodd"/></svg>',
            'color'             => '#3B82F6',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('message_text', 'Message', 'textarea', true, [
                    'placeholder' => 'Type your message. Use {{variable}} for dynamic content.',
                    'max_length'  => 1024,
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function sendButtons(): array
    {
        return [
            'name'                => 'Send Buttons',
            'description'         => 'Send interactive buttons (max 3)',
            'icon'                => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M3 4a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4Zm7 0a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V4ZM3 10a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2Zm7 0a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1v-2Z"/></svg>',
            'color'               => '#8B5CF6',
            'has_multiple_outputs' => true,
            'terminates_flow'     => false,
            'config_fields'       => [
                self::field('body_text', 'Message Text', 'textarea', true, ['max_length' => 1024]),
                self::field('buttons', 'Buttons', 'button_list', true, [
                    'min_items'   => 1,
                    'max_items'   => 3,
                    'item_schema' => [
                        self::field('id',        'Button ID',   'text',        true,  ['placeholder' => 'btn_yes']),
                        self::field('title',     'Button Text', 'text',        true,  ['max_length'  => 20]),
                        self::field('next_node', 'Go To',       'node_select', true),
                    ],
                ]),
                self::field('save_to_variable', 'Save Selection To Variable', 'text', false, [
                    'placeholder' => 'variable_name',
                    'help'        => 'Optional — saves the clicked button ID',
                ]),
            ],
        ];
    }

    private static function sendList(): array
    {
        return [
            'name'              => 'Send List',
            'description'       => 'Send an interactive list menu (up to 10 items)',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M3 5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h8a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Z" clip-rule="evenodd"/></svg>',
            'color'             => '#F59E0B',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('body_text',   'Message Text',  'textarea', true, ['max_length' => 1024]),
                self::field('button_text', 'Button Label',  'text',     true, ['max_length' => 20, 'placeholder' => 'View Options']),
                self::field('sections', 'List Sections', 'list_sections', true, [
                    'max_sections'  => 10,
                    'section_schema' => [
                        self::field('title', 'Section Title', 'text', false),
                        self::field('rows', 'Items', 'list', true, [
                            'item_schema' => [
                                self::field('id',          'ID',          'text', true, ['placeholder' => 'item_1']),
                                self::field('title',       'Title',       'text', true, ['max_length'  => 24]),
                                self::field('description', 'Description', 'text', false, ['max_length' => 72]),
                            ],
                        ]),
                    ],
                ]),
                self::field('save_to_variable', 'Save Selection To Variable', 'text', false, ['placeholder' => 'list_choice']),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function sendMedia(): array
    {
        return [
            'name'              => 'Send Media',
            'description'       => 'Send an image, video, document, or audio',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M4 3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H4Zm12 12H4l4-8 3 6 2-4 3 6Z" clip-rule="evenodd"/></svg>',
            'color'             => '#EC4899',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('media_type', 'Media Type', 'select', true, [
                    'options' => [
                        ['value' => 'image',    'label' => 'Image'],
                        ['value' => 'video',    'label' => 'Video'],
                        ['value' => 'document', 'label' => 'Document'],
                        ['value' => 'audio',    'label' => 'Audio'],
                    ],
                ]),
                self::field('media_url', 'Media URL', 'url', true, ['placeholder' => 'https://example.com/image.jpg']),
                self::field('caption', 'Caption (optional)', 'textarea', false, ['max_length' => 1024]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function collectInput(): array
    {
        return [
            'name'              => 'Collect Input',
            'description'       => 'Ask a question and save the reply as a variable',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793Zm-2.207 2.207L3 13.172V16h2.828l8.38-8.379-2.83-2.828Z"/></svg>',
            'color'             => '#06B6D4',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('prompt_text', 'Question to Ask', 'textarea', true, [
                    'placeholder' => 'What is your email address?',
                ]),
                self::field('variable_name', 'Save Response As', 'text', true, [
                    'placeholder' => 'user_email',
                    'pattern'     => '^[a-z_][a-z0-9_]*$',
                    'help'        => 'Lowercase letters and underscores only',
                ]),
                self::field('validation', 'Validation', 'validation_rules', false, [
                    'options' => [
                        ['value' => 'none',       'label' => 'No validation'],
                        ['value' => 'email',      'label' => 'Email address'],
                        ['value' => 'phone',      'label' => 'Phone number'],
                        ['value' => 'number',     'label' => 'Number only'],
                        ['value' => 'min_length', 'label' => 'Minimum length'],
                        ['value' => 'max_length', 'label' => 'Maximum length'],
                    ],
                ]),
                self::field('error_message', 'Error Message', 'text', false, [
                    'placeholder' => 'Invalid input. Please try again.',
                    'show_if'     => 'validation != none',
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function condition(): array
    {
        return [
            'name'                 => 'Condition',
            'description'          => 'Branch the flow based on a condition',
            'icon'                 => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M10 3a1 1 0 0 1 .707.293l3 3a1 1 0 0 1-1.414 1.414L11 6.414V9h2a1 1 0 1 1 0 2h-2v2.586l1.293-1.293a1 1 0 0 1 1.414 1.414l-3 3A1 1 0 0 1 9 16V6.414L7.707 7.707a1 1 0 0 1-1.414-1.414l3-3A1 1 0 0 1 10 3Z" clip-rule="evenodd"/></svg>',
            'color'                => '#EF4444',
            'has_multiple_outputs' => true,
            'outputs'              => ['true', 'false'],
            'terminates_flow'      => false,
            'config_fields'        => [
                self::field('condition_type', 'Condition Type', 'select', true, [
                    'options' => [
                        ['value' => 'variable_equals',   'label' => 'Variable equals value'],
                        ['value' => 'variable_contains', 'label' => 'Variable contains text'],
                        ['value' => 'contact_has_tag',   'label' => 'Contact has tag'],
                        ['value' => 'ai_decision',       'label' => 'AI Decision'],
                    ],
                ]),
                self::field('variable', 'Variable Name', 'text', true, [
                    'show_if' => 'condition_type in [variable_equals, variable_contains]',
                ]),
                self::field('value', 'Expected Value', 'text', true, [
                    'show_if' => 'condition_type == variable_equals',
                ]),
                self::field('substring', 'Text to Find', 'text', true, [
                    'show_if' => 'condition_type == variable_contains',
                ]),
                self::field('tag_id', 'Tag', 'tag_select', true, [
                    'show_if' => 'condition_type == contact_has_tag',
                ]),
                self::field('ai_prompt', 'AI Decision Prompt', 'textarea', true, [
                    'show_if' => 'condition_type == ai_decision',
                    'placeholder' => 'Use {{variables}} in your prompt. AI will evaluate to TRUE or FALSE.\n\nExample: "Does {{user_message}} indicate customer is angry or frustrated?"',
                    'help' => 'AI will evaluate this prompt and return TRUE or FALSE',
                ]),
                self::field('true_node',  'If TRUE → Go To',  'node_select', true),
                self::field('false_node', 'If FALSE → Go To', 'node_select', true),
            ],
        ];
    }

    private static function setTag(): array
    {
        return [
            'name'              => 'Set Tag',
            'description'       => 'Add or remove a tag on the contact',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 0 1 0 1.414l-7 7a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 10V5a3 3 0 0 1 3-3h5c.256 0 .512.098.707.293l7 7ZM5 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>',
            'color'             => '#14B8A6',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('action', 'Action', 'select', true, [
                    'options' => [
                        ['value' => 'add',    'label' => 'Add Tag'],
                        ['value' => 'remove', 'label' => 'Remove Tag'],
                    ],
                ]),
                self::field('tag_id',    'Tag',       'tag_select',  true),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function handoff(): array
    {
        return [
            'name'             => 'Handoff to Agent',
            'description'      => 'Transfer the conversation to a human agent',
            'icon'             => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M2 3a1 1 0 0 1 1-1h2.153a1 1 0 0 1 .986.836l.74 4.435a1 1 0 0 1-.54 1.06l-1.548.773a11.037 11.037 0 0 0 6.105 6.105l.774-1.548a1 1 0 0 1 1.059-.54l4.435.74a1 1 0 0 1 .836.986V17a1 1 0 0 1-1 1h-2C7.82 18 2 12.18 2 5V3Z"/></svg>',
            'color'            => '#6366F1',
            'has_single_output' => false,
            'terminates_flow'  => true,
            'config_fields'    => [
                self::field('agent_id', 'Assign To Agent', 'agent_select', false, [
                    'help' => 'Leave blank to assign to any available agent',
                ]),
                self::field('handoff_message', 'Handoff Message', 'textarea', false, [
                    'placeholder' => 'Connecting you with our team…',
                    'max_length'  => 200,
                ]),
            ],
        ];
    }

    private static function end(): array
    {
        return [
            'name'             => 'End',
            'description'      => 'End the flow conversation',
            'icon'             => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8 7a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H8Z" clip-rule="evenodd"/></svg>',
            'color'            => '#6B7280',
            'has_single_output' => false,
            'terminates_flow'  => true,
            'config_fields'    => [],
        ];
    }

    private static function sendMediaButtons(): array
    {
        return [
            'name'                 => 'Media + Buttons',
            'description'          => 'Send an image or video with interactive reply buttons',
            'icon'                 => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M4 3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H4Zm12 12H4l4-8 3 6 2-4 3 6Z" clip-rule="evenodd"/><path d="M13 8.5a.5.5 0 0 1 .5-.5H16v3.5a.5.5 0 0 1-1 0V9.5h-1.5a.5.5 0 0 1-.5-.5 1 1 0 0 0-2 0 .5.5 0 0 1-1 0 2 2 0 0 1 3-1.732V8Z"/></svg>',
            'color'                => '#F97316',
            'has_multiple_outputs' => true,
            'terminates_flow'      => false,
            'config_fields'        => [
                self::field('media_type', 'Media Type', 'select', true, [
                    'options' => [
                        ['value' => 'image', 'label' => 'Image'],
                        ['value' => 'video', 'label' => 'Video'],
                    ],
                ]),
                self::field('media_url', 'Media URL', 'url', true, ['placeholder' => 'https://example.com/image.jpg']),
                self::field('body_text', 'Body Text', 'textarea', true, ['max_length' => 1024]),
                self::field('buttons', 'Buttons', 'button_list', true, [
                    'min_items'   => 1,
                    'max_items'   => 3,
                    'item_schema' => [
                        self::field('id',        'Button ID',   'text',        true,  ['placeholder' => 'btn_1']),
                        self::field('title',     'Button Text', 'text',        true,  ['max_length'  => 20]),
                        self::field('next_node', 'Go To',       'node_select', true),
                    ],
                ]),
                self::field('save_to_variable', 'Save Selection To Variable', 'text', false, ['placeholder' => 'user_choice']),
            ],
        ];
    }

    private static function urlButton(): array
    {
        return [
            'name'              => 'URL Button',
            'description'       => 'Send a message with a button that opens a website URL',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 1 1 2.828 2.828l-3 3a2 2 0 0 1-2.828 0 1 1 0 0 0-1.414 1.414 4 4 0 0 0 5.656 0l3-3a4 4 0 0 0-5.656-5.656l-1.5 1.5a1 1 0 1 0 1.414 1.414l1.5-1.5Zm-5 5a2 2 0 0 1 2.828 0 1 1 0 1 0 1.414-1.414 4 4 0 0 0-5.656 0l-3 3a4 4 0 1 0 5.656 5.656l1.5-1.5a1 1 0 1 0-1.414-1.414l-1.5 1.5a2 2 0 1 1-2.828-2.828l3-3Z" clip-rule="evenodd"/></svg>',
            'color'             => '#0EA5E9',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('body_text',   'Message Text',  'textarea', true,  ['max_length' => 1024]),
                self::field('footer_text', 'Footer Text',   'text',     false, ['max_length' => 60, 'placeholder' => 'Optional footer line']),
                self::field('button_text', 'Button Label',  'text',     true,  ['max_length' => 20, 'placeholder' => 'Visit Website']),
                self::field('button_url',  'Button URL',    'url',      true,  ['placeholder' => 'https://example.com']),
                self::field('next_node',   'Next Node',     'node_select', true),
            ],
        ];
    }

    private static function requestLocation(): array
    {
        return [
            'name'              => 'Request Location',
            'description'       => 'Ask the contact to share their GPS location',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 1 1 9.9 9.9L10 18.9l-4.95-4.95a7 7 0 0 1 0-9.9ZM10 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" clip-rule="evenodd"/></svg>',
            'color'             => '#D946EF',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('message_text', 'Request Message', 'textarea', true, [
                    'placeholder' => 'Please share your location so we can find the nearest store.',
                    'max_length'  => 1024,
                ]),
                self::field('variable_name', 'Save Location As Variable', 'text', false, [
                    'placeholder' => 'user_location',
                    'help'        => 'Stores as "lat,lng". Leave blank to skip saving.',
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function collectForm(): array
    {
        return [
            'name'              => 'Collect Form',
            'description'       => 'Ask multiple questions in sequence and save all answers',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M8 3a1 1 0 0 1 1-1h2a1 1 0 1 1 0 2H9a1 1 0 0 1-1-1Z"/><path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2 3 3 0 0 0 3 3h2a3 3 0 0 0 3-3 2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm2 3a1 1 0 0 0 0 2h8a1 1 0 1 0 0-2H6Zm0 4a1 1 0 0 0 0 2h4a1 1 0 1 0 0-2H6Z" clip-rule="evenodd"/></svg>',
            'color'             => '#7C3AED',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('fields', 'Form Fields', 'form_fields', true, [
                    'min_items'   => 1,
                    'max_items'   => 10,
                    'item_schema' => [
                        self::field('label',         'Question',         'text',   true,  ['placeholder' => 'What is your email?']),
                        self::field('variable_name', 'Save Response As', 'text',   true,  ['placeholder' => 'user_email']),
                        self::field('validation',    'Validation',       'select', false, [
                            'options' => [
                                ['value' => 'none',   'label' => 'None'],
                                ['value' => 'email',  'label' => 'Email address'],
                                ['value' => 'phone',  'label' => 'Phone number'],
                                ['value' => 'number', 'label' => 'Number only'],
                            ],
                        ]),
                    ],
                ]),
                self::field('completion_message', 'Completion Message', 'textarea', false, [
                    'placeholder' => 'Thank you! We\'ve got all your details.',
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function addToGroup(): array
    {
        return [
            'name'              => 'Add to Group',
            'description'       => 'Add the contact to a WhatsApp group',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M8 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM8 11a6 6 0 0 0-6 6h12a6 6 0 0 0-6-6Zm6-1h1V9h1a1 1 0 1 0 0-2h-1V6a1 1 0 1 0-2 0v1h-1a1 1 0 1 0 0 2h1v1Z"/></svg>',
            'color'             => '#059669',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('group_id', 'WhatsApp Group ID', 'text', true, [
                    'placeholder' => 'Enter WhatsApp group ID',
                    'help'        => 'Get the group ID from your Meta Business dashboard',
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private static function sendCatalog(): array
    {
        return [
            'name'              => 'Send Catalog',
            'description'       => 'Send full product catalog to customer',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M3 1a1 1 0 0 0 0 2h1.22l.305 1.222a.997.997 0 0 0 .01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 0 0 0-2H6.414l1-1H14a1 1 0 0 0 .894-.553l3-6A1 1 0 0 0 17 3H6.28l-.31-1.243A1 1 0 0 0 5 1H3Z"/></svg>',
            'color'             => '#F59E0B',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('catalog_id', 'Catalog', 'catalog_select', false, [
                    'help' => 'Leave empty to use account default catalog',
                ]),
                self::field('body_text', 'Message Body', 'textarea', true, [
                    'placeholder' => 'Check out our products! Browse and order below.',
                    'max_length'  => 1024,
                ]),
                self::field('footer_text', 'Footer Text', 'text', false, [
                    'placeholder' => 'Tap "View catalog" to browse',
                    'max_length'  => 60,
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function sendProduct(): array
    {
        return [
            'name'              => 'Send Product',
            'description'       => 'Send a single product card',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M10 2a4 4 0 0 0-4 4v1H5a1 1 0 0 0-.994.89l-1 9A1 1 0 0 0 4 18h12a1 1 0 0 0 .994-1.11l-1-9A1 1 0 0 0 15 7h-1V6a4 4 0 0 0-4-4Zm2 5V6a2 2 0 1 0-4 0v1h4Z" clip-rule="evenodd"/></svg>',
            'color'             => '#EF4444',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('catalog_id', 'Catalog', 'catalog_select', false, [
                    'help' => 'Leave empty to use account default catalog',
                ]),
                self::field('product_retailer_id', 'Product Retailer ID', 'text', true, [
                    'placeholder' => 'Enter product retailer_id from your catalog',
                ]),
                self::field('body_text', 'Message Body', 'textarea', false, [
                    'placeholder' => 'Here is the product you asked about:',
                    'max_length'  => 1024,
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function httpRequest(): array
    {
        return [
            'name'              => 'HTTP Request',
            'description'       => 'Call an external API and save the response into variables',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm-.5 4a.5.5 0 0 1 1 0v3.5H14a.5.5 0 0 1 0 1h-3.5a.5.5 0 0 1-.5-.5V6Z" clip-rule="evenodd"/></svg>',
            'color'             => '#0891B2',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('api_name', 'API Name', 'text', true, [
                    'placeholder' => 'e.g. Create Lead in CRM',
                ]),
                self::field('method', 'HTTP Method', 'select', true, [
                    'options' => [
                        ['value' => 'GET',    'label' => 'GET'],
                        ['value' => 'POST',   'label' => 'POST'],
                        ['value' => 'PUT',    'label' => 'PUT'],
                        ['value' => 'PATCH',  'label' => 'PATCH'],
                        ['value' => 'DELETE', 'label' => 'DELETE'],
                    ],
                ]),
                self::field('url', 'Endpoint URL', 'url', true, [
                    'placeholder' => 'https://api.example.com/leads',
                    'help'        => 'Supports {{variable}} placeholders from earlier in the flow',
                ]),
                self::field('headers', 'Headers', 'key_value_list', false, [
                    'max_items'   => 10,
                    'item_schema' => [
                        self::field('key',   'Header Name',  'text', true, ['placeholder' => 'Authorization']),
                        self::field('value', 'Header Value', 'text', true, ['placeholder' => 'Bearer {{api_token}}']),
                    ],
                ]),
                self::field('body_format', 'Body Format', 'select', false, [
                    'options' => [
                        ['value' => 'json', 'label' => 'JSON'],
                        ['value' => 'form', 'label' => 'Form (x-www-form-urlencoded)'],
                    ],
                    'help' => 'Ignored for GET requests',
                ]),
                self::field('body_fields', 'Body Fields', 'key_value_list', false, [
                    'max_items'   => 20,
                    'item_schema' => [
                        self::field('key',   'Field Name', 'text', true, ['placeholder' => 'email']),
                        self::field('value', 'Value',      'text', true, ['placeholder' => '{{user_email}}']),
                    ],
                ]),
                self::field('response_mapping', 'Save Response To Variables', 'key_value_list', false, [
                    'max_items'   => 10,
                    'item_schema' => [
                        self::field('variable_name', 'Save As',             'text', true, ['placeholder' => 'lead_id']),
                        self::field('response_path', 'Response Field Path', 'text', true, ['placeholder' => 'data.id', 'help' => 'Dot notation, e.g. data.user.id']),
                    ],
                ]),
                self::field('status_variable', 'Save HTTP Status As Variable', 'text', false, [
                    'placeholder' => 'api_status',
                    'help'        => 'Optional — lets a Condition node branch on success/failure afterward',
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function aiNode(): array
    {
        return [
            'name'              => 'AI Node',
            'description'       => 'Ask an AI model for a response, using earlier flow answers as context',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M10 2a1 1 0 0 1 1 1v1.09a7.002 7.002 0 0 1 5.91 5.91H18a1 1 0 1 1 0 2h-1.09A7.002 7.002 0 0 1 11 17.91V19a1 1 0 1 1-2 0v-1.09a7.002 7.002 0 0 1-5.91-5.91H2a1 1 0 1 1 0-2h1.09A7.002 7.002 0 0 1 9 4.09V3a1 1 0 0 1 1-1Zm0 4a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z" clip-rule="evenodd"/></svg>',
            'color'             => '#8B5CF6',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('system_prompt', 'System Prompt', 'textarea', false, [
                    'placeholder' => 'You are a helpful sales assistant for Rovix AI. Keep replies short and friendly.',
                    'help'        => 'Optional — sets the AI\'s role/behavior',
                ]),
                self::field('user_prompt', 'User Prompt', 'textarea', true, [
                    'placeholder' => 'The customer said their use case is {{use_case}}. Write a short, personalized reply.',
                    'help'        => 'Supports {{variable}} placeholders from earlier in the flow',
                ]),
                self::field('model', 'Model', 'select', false, [
                    'options' => [
                        ['value' => '',             'label' => 'Use account default'],
                        ['value' => 'gpt-4o-mini',  'label' => 'GPT-4o mini (fastest, cheapest)'],
                        ['value' => 'gpt-4o',       'label' => 'GPT-4o'],
                        ['value' => 'gpt-4-turbo',  'label' => 'GPT-4 Turbo'],
                    ],
                ]),
                self::field('save_to_variable', 'Save Response As', 'text', true, [
                    'placeholder' => 'ai_reply',
                ]),
                self::field('send_as_message', 'Send Response To Customer', 'select', false, [
                    'options' => [
                        ['value' => 'no',  'label' => 'No — just save it, use it later in the flow'],
                        ['value' => 'yes', 'label' => 'Yes — send it as a WhatsApp message immediately'],
                    ],
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function sendTemplate(): array
    {
        return [
            'name'              => 'Send Template',
            'description'       => 'Send an approved WhatsApp message template',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M4 3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H4Zm3 2h6v2H7V5Zm0 4h6v2H7V9Zm0 4h4v2H7v-2Z" clip-rule="evenodd"/></svg>',
            'color'             => '#8B5CF6',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('template_id', 'Template', 'template_select', true, [
                    'help' => 'Select an approved message template',
                ]),
                self::field('template_params', 'Template Parameters', 'key_value_list', false, [
                    'max_items'   => 10,
                    'item_schema' => [
                        self::field('key',   'Parameter Name', 'text', true, ['placeholder' => '1']),
                        self::field('value', 'Value',          'text', true, ['placeholder' => '{{user_name}}']),
                    ],
                    'help' => 'Map template placeholders (use {{variable}} for dynamic values)',
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function appointmentBooking(): array
    {
        return [
            'name'              => 'Appointment Booking',
            'description'       => 'Collect appointment details and schedule booking',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M6 2a1 1 0 0 0-1 1v1H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1V3a1 1 0 1 0-2 0v1H7V3a1 1 0 0 0-1-1Zm0 5a1 1 0 0 0 0 2h8a1 1 0 1 0 0-2H6Z" clip-rule="evenodd"/></svg>',
            'color'             => '#10B981',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('prompt_text', 'Booking Prompt', 'textarea', true, [
                    'placeholder' => 'Would you like to book an appointment? Please provide your preferred date and time.',
                    'max_length'  => 1024,
                ]),
                self::field('date_variable', 'Save Date As', 'text', true, [
                    'placeholder' => 'appointment_date',
                ]),
                self::field('time_variable', 'Save Time As', 'text', true, [
                    'placeholder' => 'appointment_time',
                ]),
                self::field('name_variable', 'Save Name As', 'text', false, [
                    'placeholder' => 'customer_name',
                ]),
                self::field('notes_variable', 'Save Notes As', 'text', false, [
                    'placeholder' => 'appointment_notes',
                ]),
                self::field('confirmation_message', 'Confirmation Message', 'textarea', false, [
                    'placeholder' => 'Your appointment is confirmed for {{appointment_date}} at {{appointment_time}}',
                    'max_length'  => 500,
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function triggerFlow(): array
    {
        return [
            'name'              => 'Trigger Flow',
            'description'       => 'Start another flow programmatically and pass variables',
            'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M5.22 14.78a.75.75 0 0 0 1.06 0l7.22-7.22v5.69a.75.75 0 0 0 1.5 0v-7.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0 0 1.5h5.69l-7.22 7.22a.75.75 0 0 0 0 1.06Z"/></svg>',
            'color'             => '#F59E0B',
            'has_single_output' => true,
            'terminates_flow'   => false,
            'config_fields'     => [
                self::field('target_flow_id', 'Target Flow', 'flow_select', true, [
                    'help' => 'Select the flow you want to trigger',
                ]),
                self::field('end_current', 'End Current Flow', 'select', false, [
                    'options' => [
                        ['value' => '0', 'label' => 'No - continue both flows'],
                        ['value' => '1', 'label' => 'Yes - end this flow after triggering'],
                    ],
                ]),
                self::field('variable_mapping', 'Pass Variables', 'key_value_list', false, [
                    'max_items'   => 10,
                    'item_schema' => [
                        self::field('source', 'From (current flow)', 'text', true, ['placeholder' => 'user_email']),
                        self::field('target', 'To (target flow)',    'text', true, ['placeholder' => 'email']),
                    ],
                    'help' => 'Map variables from this flow to the target flow',
                ]),
                self::field('next_node', 'Next Node', 'node_select', true),
            ],
        ];
    }

    private static function field(string $name, string $label, string $type, bool $required, array $extra = []): array
    {
        return array_merge(['name' => $name, 'label' => $label, 'type' => $type, 'required' => $required], $extra);
    }
}
