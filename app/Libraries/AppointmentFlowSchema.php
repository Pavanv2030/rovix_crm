<?php

namespace App\Libraries;

class AppointmentFlowSchema
{
    /**
     * Build Flow JSON for appointment booking.
     * Uses Data Exchange for dynamic slots per date.
     * flow_token threads through screens so webhook can resolve context.
     */
    public static function build(string $typeName, string $typeDescription): array
    {
        return [
            'version'         => '7.0',
            // Required whenever a screen uses a data_exchange action (ours
            // does, on SELECT_DATE) — Meta's publish validator rejects the
            // flow with a generic "invalid Flow JSON" error without it.
            'data_api_version' => '3.0',
            'routing_model'   => [
                'SELECT_DATE' => ['SELECT_TIME'],
                'SELECT_TIME' => [],
            ],
            'screens' => [

                // SCREEN 1: Date picker
                [
                    'id'    => 'SELECT_DATE',
                    'title' => 'Select Date',
                    'data'  => [
                        'flow_token' => ['type' => 'string', '__example__' => 'token_abc'],
                        'min_date'   => ['type' => 'string', '__example__' => '2026-07-03'],
                        'max_date'   => ['type' => 'string', '__example__' => '2026-09-01'],
                    ],
                    'layout' => [
                        'type'     => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type' => 'TextHeading',
                                'text' => "Book: {$typeName}",
                            ],
                            [
                                'type' => 'TextBody',
                                'text' => $typeDescription ?: 'Choose a date for your appointment.',
                            ],
                            [
                                'type'     => 'DatePicker',
                                'label'    => 'Select Date',
                                'name'     => 'selected_date',
                                'required' => true,
                                'min-date' => '${data.min_date}',
                                'max-date' => '${data.max_date}',
                            ],
                            [
                                'type'  => 'Footer',
                                'label' => 'Next',
                                'on-click-action' => [
                                    'name'    => 'data_exchange',
                                    'payload' => [
                                        'selected_date' => '${form.selected_date}',
                                        'flow_token'    => '${data.flow_token}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                // SCREEN 2: Time slot picker (slots injected by data exchange)
                // Must be explicitly marked terminal — Meta v7.0 requires this
                // on any screen using a "complete" on-click-action; it's no
                // longer inferred from routing_model having no outgoing edges.
                [
                    'id'       => 'SELECT_TIME',
                    'title'    => 'Select Time',
                    'terminal' => true,
                    'data'  => [
                        'selected_date' => ['type' => 'string', '__example__' => '2026-07-10'],
                        'flow_token'    => ['type' => 'string', '__example__' => 'token_abc'],
                        'time_slots'    => [
                            'type'        => 'array',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'id'    => ['type' => 'string'],
                                    'title' => ['type' => 'string'],
                                ],
                            ],
                            '__example__' => [
                                ['id' => '09:00', 'title' => '09:00 AM'],
                                ['id' => '10:00', 'title' => '10:00 AM'],
                            ],
                        ],
                    ],
                    'layout' => [
                        'type'     => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type' => 'TextHeading',
                                'text' => 'Available Time Slots',
                            ],
                            [
                                'type' => 'TextBody',
                                'text' => 'Date: ${data.selected_date}',
                            ],
                            [
                                'type'        => 'RadioButtonsGroup',
                                'label'       => 'Choose a time',
                                'name'        => 'selected_time',
                                'required'    => true,
                                'data-source' => '${data.time_slots}',
                            ],
                            [
                                'type'  => 'Footer',
                                'label' => 'Confirm Booking',
                                'on-click-action' => [
                                    'name'    => 'complete',
                                    'payload' => [
                                        'selected_date' => '${data.selected_date}',
                                        'selected_time' => '${form.selected_time}',
                                        'flow_token'    => '${data.flow_token}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
