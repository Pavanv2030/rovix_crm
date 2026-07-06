# Rovix CRM — WhatsApp Appointment Booking (WhatChimp-exact)
**Stack:** CodeIgniter 4 · Meta Flows API · Google Calendar API  
**Target:** Native calendar UI inside WhatsApp → public invoice page

---

## 1. How WhatChimp Actually Works (Real Flow)

```
Bot sends Flow message → WhatsApp opens native UI (calendar picker)
Customer picks date → time slots appear → confirm
→ Meta sends nfm_reply webhook → Rovix saves booking
→ Rovix sends confirmation WA message with link
→ Customer opens: yourdomain.com/booking/{token}
→ Sees invoice + appointment details + Meet link (printable)
```

**Key tech:** Meta WhatsApp Flows API (not plain text prompts)

---

## 2. What Changes vs Original Guide

| Original Guide | WhatChimp-exact (this guide) |
|---|---|
| Text prompt "type date" | Native calendar widget in WhatsApp |
| Text slot list "type number" | Native radio button slots in WhatsApp |
| WA text confirmation | WA message + public booking URL |
| No invoice page | `/booking/{token}` invoice + print |

Everything else same: Google Calendar, Meet link, reminders, DB schema.

---

## 3. DB Migrations (same as before + 1 addition)

### Migration 000034 — appointment_types + appointments
*(same as original guide — copy as-is)*

Add `booking_token` field to appointments in same migration:
```php
'booking_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'unique' => true],
```

### Migration 000035 — google_oauth_tokens
*(same as original guide)*

### Migration 000036 — whatsapp_flows table

**File: `app/Database/Migrations/2024-01-01-000036_CreateWhatsAppFlows.php`**

```php
<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateWhatsAppFlows extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'appointment_type_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'flow_id'             => ['type' => 'VARCHAR', 'constraint' => 100],
            // Meta's flow ID returned after creation
            'flow_name'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'status'              => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'published', 'deprecated'],
                'default'    => 'draft',
            ],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('whatsapp_flows');
    }

    public function down()
    {
        $this->forge->dropTable('whatsapp_flows', true);
    }
}
```

### Migration 000037 — conversations flow_state

```php
$this->forge->addColumn('conversations', [
    'flow_state' => ['type' => 'JSON', 'null' => true, 'after' => 'last_message_at'],
]);
```

---

## 4. Models

### AppointmentTypeModel.php
*(same as original guide)*

### AppointmentModel.php

```php
<?php
namespace App\Models;

class AppointmentModel extends BaseModel
{
    protected $table         = 'appointments';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'account_id', 'appointment_type_id', 'contact_id',
        'conversation_id', 'contact_name', 'contact_phone', 'contact_email',
        'scheduled_at', 'end_at', 'status', 'answers',
        'google_event_id', 'meet_link', 'price_paid', 'notes',
        'reminder_sent_at', 'booking_token',            // ← NEW
        'created_at', 'updated_at',
    ];

    public function generateBookingToken(): string
    {
        return bin2hex(random_bytes(16)); // 32-char hex
    }
}
```

---

## 5. MetaApi.php — Add Flow Methods

Add to `app/Libraries/WhatsApp/MetaApi.php`:

```php
/**
 * Create WhatsApp Flow via Meta API.
 * Returns flow_id to store in DB.
 */
public function createFlow(
    string $wabaId,
    string $accessToken,
    string $name,
    array  $categories = ['APPOINTMENT_BOOKING']
): array {
    return $this->callApi('POST', "{$wabaId}/flows", [
        'name'       => $name,
        'categories' => $categories,
    ], $accessToken);
}

/**
 * Upload Flow JSON to Meta.
 * Must call after createFlow().
 */
public function updateFlowJson(
    string $flowId,
    string $accessToken,
    array  $flowJson
): array {
    return $this->callApi('POST', "{$flowId}/assets", [
        'name'          => 'flow.json',
        'asset_type'    => 'FLOW_JSON',
        'file'          => json_encode($flowJson),
    ], $accessToken);
}

/**
 * Publish flow (make it live).
 * Cannot edit after publish — create new flow to update.
 */
public function publishFlow(string $flowId, string $accessToken): array
{
    return $this->callApi('POST', "{$flowId}/publish", [], $accessToken);
}

/**
 * Send appointment booking Flow message to customer.
 * Customer sees "Available Date & Time" button.
 * Tap opens native calendar UI.
 */
public function sendFlowMessage(
    string $phoneNumberId,
    string $accessToken,
    string $to,
    string $bodyText,
    string $buttonText,
    string $flowId,
    string $flowToken,   // unique per message send (use uniqid)
    array  $flowData = [] // pre-fill data passed to flow
): array {
    return $this->callApi('POST', "{$phoneNumberId}/messages", [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'interactive',
        'interactive'       => [
            'type'   => 'flow',
            'body'   => ['text' => $bodyText],
            'action' => [
                'name'       => 'flow',
                'parameters' => [
                    'flow_message_version' => '3',
                    'flow_token'           => $flowToken,
                    'flow_id'              => $flowId,
                    'flow_cta'             => $buttonText,
                    'flow_action'          => 'navigate',
                    'flow_action_payload'  => [
                        'screen'    => 'SELECT_DATE',
                        'data'      => $flowData,
                    ],
                ],
            ],
        ],
    ], $accessToken);
}
```

---

## 6. Flow JSON Schema (appointment booking UI)

WhatChimp sends this Flow JSON to Meta. Defines 3 screens:
- Screen 1: Date picker
- Screen 2: Time slot radio buttons
- Screen 3: Confirmation

**File: `app/Libraries/AppointmentFlowSchema.php`**

```php
<?php
namespace App\Libraries;

class AppointmentFlowSchema
{
    /**
     * Build Flow JSON for a given appointment type.
     * $slots format: ['09:00', '09:30', '10:00', ...]
     * Meta requires static slot list at flow creation time.
     * For dynamic slots → use Flow Data Exchange endpoint (advanced).
     */
    public static function build(string $typeName, string $typeDescription): array
    {
        return [
            'version'  => '6.1',
            'screens'  => [

                // SCREEN 1: Date picker
                [
                    'id'       => 'SELECT_DATE',
                    'title'    => 'Select Appointment Date',
                    'layout'   => [
                        'type'     => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type'    => 'TextHeading',
                                'text'    => "Book: {$typeName}",
                            ],
                            [
                                'type'    => 'TextBody',
                                'text'    => $typeDescription ?: 'Please choose a date & time for your appointment 👇',
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
                                'type'    => 'Footer',
                                'label'   => 'Next',
                                'on-click-action' => [
                                    'name'    => 'navigate',
                                    'next'    => ['type' => 'screen', 'name' => 'SELECT_TIME'],
                                    'payload' => ['selected_date' => '${form.selected_date}'],
                                ],
                            ],
                        ],
                    ],
                ],

                // SCREEN 2: Time slot picker
                [
                    'id'       => 'SELECT_TIME',
                    'title'    => 'Select Time Slot',
                    'data'     => [
                        'selected_date' => ['type' => 'string', '__example__' => '2026-07-10'],
                        'time_slots'    => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object', 'properties' => [
                                'id'    => ['type' => 'string'],
                                'title' => ['type' => 'string'],
                            ]],
                            '__example__' => [
                                ['id' => '09:00', 'title' => '09:00 AM'],
                                ['id' => '10:00', 'title' => '10:00 AM'],
                            ],
                        ],
                    ],
                    'layout'   => [
                        'type'     => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type' => 'TextHeading',
                                'text' => 'Available Time Slots',
                            ],
                            [
                                'type'     => 'TextBody',
                                'text'     => 'Date: ${data.selected_date}',
                            ],
                            [
                                'type'        => 'RadioButtonsGroup',
                                'label'       => 'Choose a slot',
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
```

> **Note on dynamic slots:** Above uses static example slots. For real-time availability, implement **Flow Data Exchange endpoint** (webhook Meta calls to fetch slots per date). See Step 8.

---

## 7. AppointmentsController.php (Web + OAuth)

*(Same as original guide — keep all methods)*

**Add flow creation method:**

```php
// POST /appointments/flows/create
public function createFlow()
{
    $typeId = $this->request->getPost('appointment_type_id');
    $type   = (new AppointmentTypeModel())
        ->where('account_id', session('account_id'))->find($typeId);

    if (!$type) {
        return $this->response->setJSON(['error' => 'Type not found'])->setStatusCode(404);
    }

    $waConfig    = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
    $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
    $metaApi     = new MetaApi();

    // Step 1: Create flow on Meta
    $created = $metaApi->createFlow(
        $waConfig['waba_id'],
        $accessToken,
        "Appointment: {$type['name']}"
    );

    if (empty($created['id'])) {
        return $this->response->setJSON(['error' => 'Meta flow creation failed', 'meta' => $created])->setStatusCode(500);
    }

    $flowId = $created['id'];

    // Step 2: Upload flow JSON
    $flowJson = AppointmentFlowSchema::build($type['name'], $type['description'] ?? '');
    $metaApi->updateFlowJson($flowId, $accessToken, $flowJson);

    // Step 3: Publish
    $metaApi->publishFlow($flowId, $accessToken);

    // Step 4: Save to DB
    \Config\Database::connect()->table('whatsapp_flows')->insert([
        'id'                  => generate_uuid(),
        'account_id'          => session('account_id'),
        'appointment_type_id' => $typeId,
        'flow_id'             => $flowId,
        'flow_name'           => "Appointment: {$type['name']}",
        'status'              => 'published',
        'created_at'          => date('Y-m-d H:i:s'),
    ]);

    return $this->response->setJSON(['success' => true, 'flow_id' => $flowId]);
}
```

---

## 8. Flow Data Exchange Endpoint (dynamic slots)

Meta calls THIS endpoint when customer picks a date in WhatsApp.  
Returns real-time available slots for that date.

**Add to Routes.php:**
```php
$routes->post('api/flows/data-exchange', 'Api\FlowDataController::handle');
```

**File: `app/Controllers/Api/FlowDataController.php`**

```php
<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppointmentTypeModel;

class FlowDataController extends BaseController
{
    /**
     * Meta calls this when customer picks date in Flow.
     * Returns available time slots for that date.
     *
     * Meta sends encrypted request — decrypt before reading.
     * Respond with encrypted response.
     *
     * Setup: Meta Developer Console → WhatsApp → Flows
     *        → set "Endpoint URI" = https://yourdomain.com/api/flows/data-exchange
     */
    public function handle()
    {
        // Meta encrypts flow data exchange requests.
        // Decrypt using your flow private key (set in Meta Dev Console).
        // For simplicity in MVP — use unencrypted mode (set in Meta Console: "Endpoint Type = Unencrypted")

        $body = $this->request->getJSON(true);

        // Meta sends: { flow_token, action, screen, data: { selected_date }, version }
        $action        = $body['action'] ?? '';
        $screen        = $body['screen'] ?? '';
        $flowToken     = $body['flow_token'] ?? '';
        $data          = $body['data'] ?? [];

        // Ping check from Meta
        if ($action === 'ping') {
            return $this->response->setJSON(['data' => ['status' => 'active']]);
        }

        // Customer picked a date on SELECT_DATE screen → return slots for SELECT_TIME
        if ($screen === 'SELECT_DATE' && $action === 'data_exchange') {
            $selectedDate = $data['selected_date'] ?? null;

            // Resolve account from flow_token (store mapping when sending flow)
            $flowMeta = \Config\Database::connect()
                ->table('flow_token_map')
                ->where('flow_token', $flowToken)
                ->get()->getRowArray();

            $typeId = $flowMeta['appointment_type_id'] ?? null;
            $slots  = [];

            if ($typeId && $selectedDate) {
                $rawSlots = (new AppointmentTypeModel())->getAvailableSlots($typeId, $selectedDate);
                foreach ($rawSlots as $slot) {
                    $slots[] = [
                        'id'    => $slot,
                        'title' => date('h:i A', strtotime("{$selectedDate} {$slot}")),
                    ];
                }
            }

            if (empty($slots)) {
                $slots = [['id' => 'none', 'title' => 'No slots available']];
            }

            return $this->response->setJSON([
                'screen' => 'SELECT_TIME',
                'data'   => [
                    'selected_date' => $selectedDate,
                    'time_slots'    => $slots,
                ],
            ]);
        }

        return $this->response->setJSON(['data' => []]);
    }
}
```

**Add flow_token_map table in migration 000036:**
```php
$this->forge->addField([
    'flow_token'          => ['type' => 'VARCHAR', 'constraint' => 100],
    'account_id'          => ['type' => 'CHAR', 'constraint' => 36],
    'appointment_type_id' => ['type' => 'CHAR', 'constraint' => 36],
    'contact_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
    'conversation_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
    'created_at'          => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addPrimaryKey('flow_token');
$this->forge->createTable('flow_token_map');
```

---

## 9. Api/AppointmentsController.php

```php
<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\GoogleCalendar;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Models\AppointmentTypeModel;
use App\Models\AppointmentModel;
use App\Models\WhatsAppConfigModel;
use App\Models\ConversationModel;
use App\Models\ContactModel;

class AppointmentsController extends BaseController
{
    /**
     * POST /api/appointments/send-flow
     * Send Flow booking message from Inbox.
     * Customer sees "Available Date & Time" button.
     */
    public function sendFlow()
    {
        $typeId         = $this->request->getPost('appointment_type_id');
        $conversationId = $this->request->getPost('conversation_id');
        $bodyText       = $this->request->getPost('body_text')
            ?? 'Please choose a date & time for your appointment 👇';
        $buttonText     = $this->request->getPost('button_text')
            ?? 'Available Date & Time';

        $type         = (new AppointmentTypeModel())->find($typeId);
        $conversation = (new ConversationModel())->find($conversationId);
        $contact      = $conversation ? (new ContactModel())->find($conversation['contact_id']) : null;
        $waConfig     = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        if (!$type || !$conversation || !$contact || !$waConfig) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Missing data']);
        }

        // Get published flow for this type
        $flowRow = \Config\Database::connect()
            ->table('whatsapp_flows')
            ->where('appointment_type_id', $typeId)
            ->where('status', 'published')
            ->get()->getRowArray();

        if (!$flowRow) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'No published flow for this appointment type. Create flow first.',
            ]);
        }

        $flowToken   = uniqid('apt_', true);
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        // Save flow_token → context mapping for data exchange endpoint
        \Config\Database::connect()->table('flow_token_map')->insert([
            'flow_token'          => $flowToken,
            'account_id'          => session('account_id'),
            'appointment_type_id' => $typeId,
            'contact_id'          => $contact['id'],
            'conversation_id'     => $conversationId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        // Min date = tomorrow, max date = 60 days ahead
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
                'max_date' => date('Y-m-d', strtotime('+60 days')),
            ]
        );

        return $this->response->setJSON(['success' => true, 'meta' => $response]);
    }
}
```

---

## 10. WebhookController.php — Handle Flow Completion

When customer completes flow (taps "Confirm Booking"), Meta sends `nfm_reply`.

Add to `processInboundMessage()` switch case `'interactive'`:

```php
case 'interactive':
    $interactive = $message['interactive'] ?? [];
    $contentText = $this->extractInteractiveResponse($interactive);

    // Flow completion = nfm_reply with flow payload
    if (isset($interactive['nfm_reply'])) {
        $this->processFlowCompletion(
            $interactive['nfm_reply'],
            $contact,
            $conversation,
            $waConfig,
            $accountId
        );
    }
    break;
```

Add private method:

```php
private function processFlowCompletion(
    array  $nfmReply,
    array  $contact,
    array  $conversation,
    array  $waConfig,
    string $accountId
): void {
    // nfm_reply contains: response_json (base64 encoded), name, body
    $responseJson = json_decode(base64_decode($nfmReply['response_json'] ?? ''), true);

    $selectedDate  = $responseJson['selected_date'] ?? null;
    $selectedTime  = $responseJson['selected_time'] ?? null;
    $flowToken     = $responseJson['flow_token']    ?? null;

    if (!$selectedDate || !$selectedTime) {
        log_message('warning', 'Flow completion missing date/time: ' . json_encode($responseJson));
        return;
    }

    // Get context from flow_token_map
    $flowMeta = \Config\Database::connect()
        ->table('flow_token_map')
        ->where('flow_token', $flowToken)
        ->get()->getRowArray();

    $typeId = $flowMeta['appointment_type_id'] ?? null;
    if (!$typeId) {
        log_message('warning', "No flow_token_map entry for token: {$flowToken}");
        return;
    }

    $type        = (new \App\Models\AppointmentTypeModel())->find($typeId);
    $scheduledAt = date('Y-m-d H:i:s', strtotime("{$selectedDate} {$selectedTime}"));
    $endAt       = date('Y-m-d H:i:s', strtotime($scheduledAt) + ($type['duration_minutes'] * 60));
    $accessToken = (new \App\Libraries\WhatsApp\Encryption())->decrypt($waConfig['access_token']);

    // Google Calendar event
    $meetLink      = null;
    $googleEventId = null;
    $tokenRow      = \Config\Database::connect()
        ->table('google_oauth_tokens')
        ->where('account_id', $accountId)
        ->get()->getRowArray();

    if ($tokenRow) {
        try {
            $gc    = new \App\Libraries\GoogleCalendar();
            $gcTok = $gc->getValidToken($tokenRow);
            $tz    = env('APP_TIMEZONE', 'Asia/Kolkata');

            $event = $gc->createEvent(
                $gcTok,
                $tokenRow['calendar_id'],
                "Appointment: {$type['name']} with {$contact['name']}",
                "Customer: {$contact['name']}\nPhone: {$contact['phone']}",
                date('Y-m-d\TH:i:s', strtotime($scheduledAt)),
                date('Y-m-d\TH:i:s', strtotime($endAt)),
                $tz,
                $contact['email'] ?? ''
            );

            $meetLink      = $event['conferenceData']['entryPoints'][0]['uri'] ?? null;
            $googleEventId = $event['id'] ?? null;
        } catch (\Exception $e) {
            log_message('error', 'Google Calendar failed: ' . $e->getMessage());
        }
    }

    // Generate booking token for public page
    $bookingToken = bin2hex(random_bytes(16));

    // Save appointment
    $appointmentModel = new \App\Models\AppointmentModel();
    $appointmentId    = $appointmentModel->insert([
        'account_id'          => $accountId,
        'appointment_type_id' => $typeId,
        'contact_id'          => $contact['id'],
        'conversation_id'     => $conversation['id'],
        'contact_name'        => $contact['name'],
        'contact_phone'       => $contact['phone'],
        'scheduled_at'        => $scheduledAt,
        'end_at'              => $endAt,
        'status'              => 'confirmed',
        'google_event_id'     => $googleEventId,
        'meet_link'           => $meetLink,
        'price_paid'          => $type['price'],
        'booking_token'       => $bookingToken,
        'created_at'          => date('Y-m-d H:i:s'),
        'updated_at'          => date('Y-m-d H:i:s'),
    ]);

    // Public booking URL (like WhatChimp's app.whatchimp.com/a/23333455)
    $bookingUrl = base_url("booking/{$bookingToken}");

    // Send WA confirmation with booking URL
    $dateFormatted = date('D, d M Y', strtotime($scheduledAt));
    $timeFormatted = date('h:i A', strtotime($scheduledAt));

    $msg  = "✅ *Booking Confirmed!*\n\n";
    $msg .= "Your booking has been confirmed.\n";
    $msg .= "Track your booking anytime: {$bookingUrl}\n\n";
    $msg .= "📋 *{$type['name']}*\n";
    $msg .= "📅 {$dateFormatted} at {$timeFormatted}\n";
    $msg .= "⏱ {$type['duration_minutes']} mins\n";
    if ($meetLink) $msg .= "🎥 Google Meet: {$meetLink}";

    (new \App\Libraries\WhatsApp\MetaApi())->sendTextMessage(
        $waConfig['phone_number_id'],
        $accessToken,
        $contact['phone_normalized'],
        $msg
    );

    // Cleanup flow_token_map
    \Config\Database::connect()
        ->table('flow_token_map')
        ->where('flow_token', $flowToken)
        ->delete();
}
```

---

## 11. Public Booking Page (WhatChimp's `/a/{id}` equivalent)

**Route:**
```php
$routes->get('booking/(:segment)', 'BookingController::show/$1');
```

**File: `app/Controllers/BookingController.php`**

```php
<?php
namespace App\Controllers;

use App\Models\AppointmentModel;
use App\Models\AppointmentTypeModel;

class BookingController extends BaseController
{
    /**
     * Public page — no login required.
     * Token-based access only.
     * URL: yourdomain.com/booking/abc123def456...
     */
    public function show(string $token)
    {
        $appointment = (new AppointmentModel())
            ->where('booking_token', $token)
            ->first();

        if (!$appointment) {
            return $this->response->setStatusCode(404)
                ->setBody('<h1>Booking not found</h1>');
        }

        $type = (new AppointmentTypeModel())->find($appointment['appointment_type_id']);

        // Generate invoice number from appointment id (last 6 chars)
        $invoiceNumber = '#' . strtoupper(substr($appointment['id'], -6));

        return view('booking/public', [
            'appointment'   => $appointment,
            'type'          => $type,
            'invoiceNumber' => $invoiceNumber,
        ]);
    }
}
```

**File: `app/Views/booking/public.php`** *(WhatChimp invoice layout)*

```php
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking Confirmed</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, sans-serif; }
  body { background: #f3f4f6; min-height: 100vh; }

  .confirmed-banner {
    background: #22c55e;
    color: white;
    padding: 20px 24px;
    font-size: 15px;
  }
  .confirmed-banner h2 { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
  .confirmed-banner a { color: white; }

  .container { max-width: 800px; margin: 24px auto; padding: 0 16px; display: grid;
               grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 600px) { .container { grid-template-columns: 1fr; } }

  .card { background: white; border-radius: 12px; padding: 24px;
          border: 1px solid #e5e7eb; }

  .card h3 { font-size: 14px; font-weight: 600; color: #6b7280;
             text-transform: uppercase; letter-spacing: .05em; margin-bottom: 16px; }

  .invoice-number { font-size: 24px; font-weight: 700; color: #111; margin-bottom: 4px; }
  .invoice-service { font-size: 16px; color: #374151; margin-bottom: 4px; }
  .invoice-location { font-size: 14px; color: #6b7280; }
  .invoice-datetime { font-size: 14px; color: #6b7280; margin-bottom: 20px; }

  .invoice-line { display: flex; justify-content: space-between;
                  padding: 10px 0; border-top: 1px solid #f3f4f6;
                  font-size: 14px; }
  .invoice-line.total { font-weight: 600; font-size: 15px; border-top: 1px solid #d1d5db; }

  .apt-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
  .apt-icon { width: 44px; height: 44px; border-radius: 10px;
              background: #dcfce7; display: flex; align-items: center;
              justify-content: center; font-size: 22px; }
  .apt-name { font-size: 17px; font-weight: 600; }
  .apt-sub  { font-size: 13px; color: #6b7280; }

  .apt-detail { background: #1f2937; border-radius: 8px; padding: 16px; }
  .apt-row { display: flex; justify-content: space-between; padding: 6px 0;
             font-size: 13px; border-bottom: 1px solid #374151; }
  .apt-row:last-child { border-bottom: none; }
  .apt-row .label { color: #9ca3af; }
  .apt-row .value { color: #f3f4f6; font-weight: 500; }
  .apt-row .value a { color: #60a5fa; }

  .print-btn { display: block; text-align: right; margin-bottom: 12px; }
  .print-btn button { background: white; border: 1px solid #d1d5db;
                      border-radius: 6px; padding: 6px 16px; cursor: pointer;
                      font-size: 13px; }
  @media print { .print-btn { display: none; } }
</style>
</head>
<body>

<div class="confirmed-banner">
  <h2>Booking Confirmed</h2>
  <p>Your booking has been confirmed.<br>
  You can track your booking status any time from here:
  <a href="<?= current_url() ?>"><?= current_url() ?></a></p>
</div>

<div class="container">

  <!-- LEFT: Invoice -->
  <div class="card">
    <div class="invoice-number"><?= esc($invoiceNumber) ?></div>
    <div class="invoice-service"><?= esc($type['name']) ?></div>
    <div class="invoice-location">Online</div>
    <div class="invoice-datetime">
      <?= date('jS M y g:ia', strtotime($appointment['scheduled_at'])) ?>
      – <?= date('g:ia', strtotime($appointment['end_at'])) ?>
    </div>

    <div class="invoice-line">
      <span>Tax (0%)</span>
      <span>
        <?= strtoupper($type['currency'] ?? 'USD') ?>
        <?= number_format(0, 2) ?>
      </span>
    </div>
    <div class="invoice-line total">
      <span>Checkout Amount</span>
      <span>
        <?= strtoupper($type['currency'] ?? 'USD') ?>
        <?= number_format($appointment['price_paid'] ?? 0, 2) ?>
      </span>
    </div>
  </div>

  <!-- RIGHT: Appointment details -->
  <div class="card">
    <div class="print-btn">
      <button onclick="window.print()">Print</button>
    </div>

    <h3>Appointment</h3>

    <div class="apt-header">
      <div class="apt-icon">📅</div>
      <div>
        <div class="apt-name"><?= esc($type['name']) ?></div>
        <div class="apt-sub"><?= esc($type['description'] ?? '') ?></div>
      </div>
    </div>

    <div class="apt-detail">
      <div class="apt-row">
        <span class="label">Appointment Start</span>
        <span class="value"><?= date('jS M y g:i a', strtotime($appointment['scheduled_at'])) ?></span>
      </div>
      <div class="apt-row">
        <span class="label">Appointment End</span>
        <span class="value"><?= date('jS M y g:i a', strtotime($appointment['end_at'])) ?></span>
      </div>
      <div class="apt-row">
        <span class="label">Appointment Location</span>
        <span class="value">Online</span>
      </div>
      <?php if (!empty($appointment['meet_link'])): ?>
      <div class="apt-row">
        <span class="label">Google Meet</span>
        <span class="value">
          <a href="<?= esc($appointment['meet_link']) ?>" target="_blank">
            <?= esc(parse_url($appointment['meet_link'], PHP_URL_HOST) . parse_url($appointment['meet_link'], PHP_URL_PATH)) ?>
          </a>
        </span>
      </div>
      <?php endif; ?>
      <div class="apt-row">
        <span class="label">Checkout at</span>
        <span class="value"><?= date('jS M y H:i a', strtotime($appointment['created_at'])) ?></span>
      </div>
    </div>
  </div>

</div>
</body>
</html>
```

---

## 12. Routes.php (all new routes)

```php
// Appointments (web)
$routes->get('appointments',                               'AppointmentsController::index');
$routes->get('appointments/types',                         'AppointmentsController::types');
$routes->post('appointments/types/create',                 'AppointmentsController::createType');
$routes->post('appointments/types/(:segment)/delete',      'AppointmentsController::deleteType/$1');
$routes->post('appointments/flows/create',                 'AppointmentsController::createFlow');
$routes->post('appointments/(:segment)/cancel',            'AppointmentsController::cancel/$1');
$routes->post('appointments/(:segment)/status',            'AppointmentsController::updateStatus/$1');

// Google OAuth
$routes->get('appointments/google/connect',                'AppointmentsController::googleConnect');
$routes->get('appointments/google/callback',               'AppointmentsController::googleCallback');
$routes->post('appointments/google/disconnect',            'AppointmentsController::googleDisconnect');

// API (inbox + flow)
$routes->get('api/appointments/types',                     'Api\AppointmentsController::types');
$routes->get('api/appointments/slots',                     'Api\AppointmentsController::slots');
$routes->post('api/appointments/send-flow',                'Api\AppointmentsController::sendFlow');

// Flow data exchange (Meta calls this)
$routes->post('api/flows/data-exchange',                   'Api\FlowDataController::handle');

// Public booking page (no auth)
$routes->get('booking/(:segment)',                         'BookingController::show/$1');
```

---

## 13. Meta Developer Console Setup (one-time)

```
1. developers.facebook.com → your app → WhatsApp → Flows
2. Click "Create Flow" OR let Rovix API create (Step 7)
3. Set Endpoint URI:
   https://yourdomain.com/api/flows/data-exchange
4. Endpoint Type: Unencrypted (MVP) → Encrypted (production)
5. For encrypted: generate RSA key pair, upload public key to Meta,
   decrypt requests in FlowDataController using private key

Encrypted request format (production):
  - Meta encrypts with your public key
  - Decrypt body with private_key before reading JSON
  - Return encrypted response using AES-GCM
```

---

## 14. .env additions

```env
# Google OAuth
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret

# App
APP_TIMEZONE=Asia/Kolkata

# Flow encryption (production only)
# FLOW_PRIVATE_KEY=-----BEGIN RSA PRIVATE KEY-----...
```

---

## 15. Sidebar + Inbox Updates

**Sidebar:**
```html
<li><a href="<?= base_url('appointments') ?>">📅 Appointments</a></li>
<li><a href="<?= base_url('appointments/types') ?>">⚙️ Appointment Types</a></li>
```

**Inbox compose bar** — add booking button:
```html
<button onclick="openFlowSend()" title="Send Booking Flow">📅</button>
```

```javascript
async function openFlowSend() {
    const res   = await fetch('/api/appointments/types');
    const data  = await res.json();
    const types = data.types;
    if (!types.length) { alert('Create appointment type first.'); return; }

    // Show picker — simple for now, build modal later
    const names  = types.map((t, i) => `${i+1}. ${t.name}`).join('\n');
    const choice = prompt(`Select appointment type:\n${names}`);
    if (!choice) return;

    const type = types[parseInt(choice) - 1];
    if (!type) return;

    const formData = new FormData();
    formData.append('appointment_type_id', type.id);
    formData.append('conversation_id', CONVERSATION_ID);

    const res2 = await fetch('/api/appointments/send-flow', { method: 'POST', body: formData });
    const d2   = await res2.json();
    d2.success ? alert('Booking flow sent!') : alert('Error: ' + d2.error);
}
```

---

## 16. FlowNodeSchemas.php + FlowEngine.php

*(Same node config as original guide — `book_appointment` node)*

FlowEngine `executeBookAppointment()` change:
```php
// Instead of sending text prompt, send Flow message
(new MetaApi())->sendFlowMessage(
    $waConfig['phone_number_id'],
    $accessToken,
    $contact['phone_normalized'],
    $bodyText ?? 'Please choose a date & time 👇',
    'Available Date & Time',
    $flowRow['flow_id'],
    $flowToken,
    [
        'min_date' => date('Y-m-d', strtotime('+1 day')),
        'max_date' => date('Y-m-d', strtotime('+60 days')),
    ]
);
```

---

## 17. Reminder Job + Post-Appointment Follow-Ups (Tailored Follow-Ups)

Extend cron command to handle both: 24hr before reminder AND post-appointment follow-up.

**File: `app/Commands/SendAppointmentReminders.php`** *(full replacement)*

```php
<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use App\Models\AppointmentModel;
use App\Models\AppointmentTypeModel;
use App\Models\WhatsAppConfigModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class SendAppointmentReminders extends BaseCommand
{
    protected $group       = 'Appointments';
    protected $name        = 'appointments:reminders';
    protected $description = 'Send 24hr reminders + post-appointment follow-ups';

    public function run(array $params)
    {
        $this->sendReminders();
        $this->sendFollowUps();
        $this->markCompleted();
    }

    // ── 1. 24hr before reminder ───────────────────────────────────────────

    private function sendReminders(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $appointments = (new AppointmentModel())
            ->where('DATE(scheduled_at)', $tomorrow)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('reminder_sent_at', null)
            ->findAll();

        foreach ($appointments as $appt) {
            $this->sendWaMessage($appt, $this->buildReminderMsg($appt));

            (new AppointmentModel())->update($appt['id'], [
                'reminder_sent_at' => date('Y-m-d H:i:s'),
            ]);

            CLI::write("Reminder sent → {$appt['contact_phone']}");
        }
    }

    private function buildReminderMsg(array $appt): string
    {
        $type          = (new AppointmentTypeModel())->find($appt['appointment_type_id']);
        $dateFormatted = date('D, d M Y', strtotime($appt['scheduled_at']));
        $timeFormatted = date('h:i A', strtotime($appt['scheduled_at']));

        $msg  = "⏰ *Appointment Reminder*\n\n";
        $msg .= "Your appointment is *tomorrow*!\n\n";
        $msg .= "📋 *{$type['name']}*\n";
        $msg .= "📅 {$dateFormatted} at {$timeFormatted}\n";
        $msg .= "⏱ {$type['duration_minutes']} mins\n";

        if ($appt['meet_link']) {
            $msg .= "🎥 Meet: {$appt['meet_link']}\n";
        }

        $msg .= "\nReply *CANCEL* if you can't make it.";
        return $msg;
    }

    // ── 2. Post-appointment follow-up (1hr after end_at) ─────────────────

    private function sendFollowUps(): void
    {
        // Find completed/confirmed appointments where end_at passed 1hr ago
        // AND follow_up_sent_at is null
        $cutoff = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $appointments = \Config\Database::connect()
            ->table('appointments')
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('end_at <=', $cutoff)
            ->where('follow_up_sent_at', null)
            ->get()->getResultArray();

        foreach ($appointments as $appt) {
            $this->sendWaMessage($appt, $this->buildFollowUpMsg($appt));

            \Config\Database::connect()->table('appointments')
                ->where('id', $appt['id'])
                ->update([
                    'follow_up_sent_at' => date('Y-m-d H:i:s'),
                    'status'            => 'completed',
                ]);

            CLI::write("Follow-up sent → {$appt['contact_phone']}");
        }
    }

    private function buildFollowUpMsg(array $appt): string
    {
        $type     = (new AppointmentTypeModel())->find($appt['appointment_type_id']);
        $bookingUrl = base_url("booking/{$appt['booking_token']}");

        $msg  = "👋 Hi *{$appt['contact_name']}*!\n\n";
        $msg .= "Hope your *{$type['name']}* session went well 😊\n\n";
        $msg .= "We'd love to hear your feedback!\n";
        $msg .= "Reply with:\n";
        $msg .= "⭐ 1-5 rating\n";
        $msg .= "💬 Any comments\n\n";
        $msg .= "📄 View your booking: {$bookingUrl}\n\n";
        $msg .= "Want to *book again*? Just reply *BOOK* anytime!";

        return $msg;
    }

    // ── 3. Auto-mark completed (appointments past end_at + 2hr) ──────────

    private function markCompleted(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-2 hours'));

        \Config\Database::connect()->table('appointments')
            ->where('status', 'confirmed')
            ->where('end_at <=', $cutoff)
            ->where('follow_up_sent_at IS NOT NULL')
            ->update(['status' => 'completed', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    // ── Shared WA sender ──────────────────────────────────────────────────

    private function sendWaMessage(array $appt, string $message): void
    {
        if (empty($appt['contact_phone'])) return;

        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', $appt['account_id'])->first();

        if (!$waConfig) return;

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            (new MetaApi())->sendTextMessage(
                $waConfig['phone_number_id'],
                $accessToken,
                $appt['contact_phone'],
                $message
            );
        } catch (\Exception $e) {
            log_message('error', "Appointment msg failed [{$appt['id']}]: " . $e->getMessage());
        }
    }
}
```

---

### Migration 000038 — add follow_up_sent_at column

**File: `app/Database/Migrations/2024-01-01-000038_AddAppointmentFollowUp.php`**

```php
<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddAppointmentFollowUp extends Migration
{
    public function up()
    {
        $this->forge->addColumn('appointments', [
            'follow_up_sent_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'reminder_sent_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('appointments', 'follow_up_sent_at');
    }
}
```

---

### Cron setup (2 jobs)

```bash
# 24hr reminder — runs 9am daily
0 9 * * * /usr/bin/php /path/to/rovix/spark appointments:reminders

# OR run every hour to catch follow-ups faster
0 * * * * /usr/bin/php /path/to/rovix/spark appointments:reminders
```

Hourly = best. Catches follow-up within 1hr of session end.

---

### Handle "BOOK" reply in WebhookController

Customer replies "BOOK" after follow-up → re-trigger booking flow.

Add to `processInboundMessage()` text handler:

```php
// In case 'text':
$textBody = strtoupper(trim($message['text']['body'] ?? ''));

if ($textBody === 'BOOK') {
    $this->handleRebookRequest($contact, $conversation, $waConfig);
    return;
}
```

Add private method:

```php
private function handleRebookRequest(
    array $contact, array $conversation, array $waConfig
): void {
    // Find last appointment type this contact used
    $lastAppt = \Config\Database::connect()
        ->table('appointments')
        ->where('contact_id', $contact['id'])
        ->orderBy('created_at', 'DESC')
        ->limit(1)
        ->get()->getRowArray();

    $typeId = $lastAppt['appointment_type_id'] ?? null;
    if (!$typeId) return;

    $flowRow = \Config\Database::connect()
        ->table('whatsapp_flows')
        ->where('appointment_type_id', $typeId)
        ->where('status', 'published')
        ->get()->getRowArray();

    if (!$flowRow) return;

    $flowToken   = uniqid('rebook_', true);
    $accessToken = (new \App\Libraries\WhatsApp\Encryption())->decrypt($waConfig['access_token']);

    \Config\Database::connect()->table('flow_token_map')->insert([
        'flow_token'          => $flowToken,
        'account_id'          => $waConfig['account_id'],
        'appointment_type_id' => $typeId,
        'contact_id'          => $contact['id'],
        'conversation_id'     => $conversation['id'],
        'created_at'          => date('Y-m-d H:i:s'),
    ]);

    (new \App\Libraries\WhatsApp\MetaApi())->sendFlowMessage(
        $waConfig['phone_number_id'],
        $accessToken,
        $contact['phone_normalized'],
        "Book another appointment 📅",
        'Available Date & Time',
        $flowRow['flow_id'],
        $flowToken,
        [
            'min_date' => date('Y-m-d', strtotime('+1 day')),
            'max_date' => date('Y-m-d', strtotime('+60 days')),
        ]
    );
}
```

---

## 18. Implementation Order

```
1.  php spark migrate              ← 000034 to 000038
2.  AppointmentTypeModel           ← new model
3.  AppointmentModel               ← new model (+ booking_token + follow_up_sent_at)
4.  GoogleCalendar.php             ← library
5.  AppointmentFlowSchema.php      ← new library
6.  MetaApi.php                    ← add 4 flow methods
7.  AppointmentsController.php     ← web controller + createFlow()
8.  Api/AppointmentsController.php ← send-flow endpoint
9.  Api/FlowDataController.php     ← data exchange (Meta calls this)
10. BookingController.php          ← public invoice page
11. WebhookController.php          ← processFlowCompletion() + handleRebookRequest()
12. FlowNodeSchemas.php            ← book_appointment node
13. FlowEngine.php                 ← send flow msg not text prompt
14. Routes.php                     ← all routes
15. Views                          ← booking/public.php, appointments/*, sidebar, inbox
16. .env                           ← Google + timezone keys
17. Cron (hourly)                  ← reminders + follow-ups job
18. Meta Console                   ← set Flows endpoint URL
```

---

## 19. Testing Checklist

- [ ] Migrations 000034–000038 run OK
- [ ] Create appointment type (name, duration, availability JSON)
- [ ] `/appointments/types` → "Create Flow" → creates+publishes Meta Flow
- [ ] Connect Google Calendar
- [ ] Inbox → 📅 button → select type → "Booking flow sent"
- [ ] WhatsApp shows "Available Date & Time" button
- [ ] Tap → native calendar opens in WhatsApp
- [ ] Pick date → slots appear (data exchange endpoint)
- [ ] Confirm → `nfm_reply` webhook fires
- [ ] Appointment saved with `booking_token`
- [ ] WA confirmation sent with `yourdomain.com/booking/{token}`
- [ ] Invoice page renders correctly
- [ ] Google Calendar event + Meet link created
- [ ] Print button works
- [ ] Cron: reminder sent 24hr before (`reminder_sent_at` set)
- [ ] Cron: follow-up sent 1hr after `end_at` (`follow_up_sent_at` set)
- [ ] Cron: status → `completed` after follow-up sent
- [ ] Customer replies "BOOK" → flow message re-sent
- [ ] Cancel → Google Calendar event deleted
