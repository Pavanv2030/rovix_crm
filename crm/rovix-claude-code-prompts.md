# Rovix AI Leads Tool — Claude Code Prompts
## Phase-by-phase, module-by-module migration guide
### Project: https://rovixai.com/ | Stack: PHP 8.1 + CodeIgniter 4 + MySQL 8.0

---

## How to use this document

Each prompt below is designed to be **copy-pasted directly into Claude Code**. Run them in order — each phase builds on the previous one. Wait for each prompt to complete before moving to the next.

**Before starting**: Place the original `wacrm-main` source code in a reference folder so Claude Code can read the TypeScript implementations while building PHP equivalents.

---

## PHASE 1: Project Setup + Database Schema (Week 1)

### Prompt 1.1 — CI4 Project Initialization

```
Create a new CodeIgniter 4 project for "Rovix AI Leads Tool" — a WhatsApp CRM and lead automation platform for https://rovixai.com/.

1. Run: composer create-project codeigniter4/appstarter rovix-ai-leads-tool
2. Create .env from env file with these sections:
   - App config (CI_ENVIRONMENT=development, app.baseURL)
   - Database (MySQLi driver, localhost, port 3306)
   - WhatsApp config keys: whatsapp.phoneNumberId, whatsapp.wabaId, whatsapp.accessToken, whatsapp.verifyToken, whatsapp.metaAppSecret
   - Encryption: rovix.encryptionKey (64-char hex, 32 bytes)
   - Daily report: rovix.dailyReportPhones (comma-separated phone numbers)
   - Session: app.sessionDriver=CodeIgniter\Session\Handlers\DatabaseHandler, app.sessionSavePath=ci_sessions
3. Create app/Config/WhatsApp.php — config class reading all whatsapp.* env vars
4. Create app/Config/Rovix.php — config class reading rovix.* env vars
5. Update app/Config/App.php with proper timezone (Asia/Kolkata)
6. Create the full directory structure:
   - app/Controllers/Api/
   - app/Libraries/WhatsApp/
   - app/Filters/
   - app/Commands/
   - app/Helpers/
   - public/assets/css/ and public/assets/js/
   - app/Views/layouts/, app/Views/layouts/partials/
   - app/Views/auth/, dashboard/, inbox/, contacts/, pipelines/, broadcasts/, automations/, flows/, settings/

Don't create placeholder files yet — just the directory structure and config.
```

### Prompt 1.2 — Database Migrations (Part 1: Core tables)

```
Create CI4 database migrations for the Rovix AI Leads Tool. These are ported from the original wacrm Supabase/Postgres schema to MySQL 8.0.

Key differences from Postgres:
- Use CHAR(36) for UUID primary keys (generate UUIDs in PHP, not DB)
- Use JSON column type for complex fields
- Use ENUM for status fields
- No RLS — we'll handle tenant isolation in PHP
- Add account_id column + composite index on every tenant-scoped table

Create these migrations in app/Database/Migrations/:

Migration 1 — CreateAccounts:
- id CHAR(36) PK
- name VARCHAR(255) NOT NULL
- owner_user_id CHAR(36) NULL
- default_currency VARCHAR(3) DEFAULT 'INR'
- created_at DATETIME, updated_at DATETIME

Migration 2 — CreateProfiles:
- id CHAR(36) PK
- user_id CHAR(36) UNIQUE NOT NULL (this is the auth user ID)
- account_id CHAR(36) NOT NULL FK→accounts
- full_name VARCHAR(255)
- email VARCHAR(255) NOT NULL
- password_hash VARCHAR(255) NOT NULL
- avatar_url VARCHAR(500) NULL
- account_role ENUM('owner','admin','agent','viewer') DEFAULT 'owner'
- created_at, updated_at
- INDEX on (account_id), UNIQUE on (email)

Migration 3 — CreateContacts:
- id CHAR(36) PK
- account_id CHAR(36) NOT NULL FK→accounts
- phone VARCHAR(20) NOT NULL
- phone_normalized VARCHAR(20) NOT NULL
- name VARCHAR(255) NULL
- email VARCHAR(255) NULL
- company VARCHAR(255) NULL
- avatar_url VARCHAR(500) NULL
- created_at, updated_at
- UNIQUE on (account_id, phone_normalized)
- INDEX on (account_id)

Migration 4 — CreateTags:
- id CHAR(36) PK, account_id, name VARCHAR(100), color VARCHAR(7)
- UNIQUE on (account_id, name)

Migration 5 — CreateContactTags:
- id CHAR(36) PK, contact_id FK→contacts ON DELETE CASCADE, tag_id FK→tags ON DELETE CASCADE
- UNIQUE on (contact_id, tag_id)

Migration 6 — CreateCustomFields:
- id CHAR(36) PK, account_id, field_name VARCHAR(100), field_type ENUM('text','number','date','dropdown'), field_options JSON NULL
- INDEX on (account_id)

Migration 7 — CreateContactCustomValues:
- id CHAR(36) PK, contact_id FK→contacts ON DELETE CASCADE, custom_field_id FK→custom_fields ON DELETE CASCADE, value TEXT NULL

Migration 8 — CreateContactNotes:
- id CHAR(36) PK, contact_id FK→contacts ON DELETE CASCADE, user_id CHAR(36), note_text TEXT, created_at

Migration 9 — CreateConversations:
- id CHAR(36) PK, account_id NOT NULL
- contact_id FK→contacts ON DELETE SET NULL
- status ENUM('open','pending','closed') DEFAULT 'open'
- assigned_agent_id CHAR(36) NULL
- unread_count INT DEFAULT 0
- last_message_text TEXT NULL
- last_message_at DATETIME NULL
- created_at, updated_at
- INDEX on (account_id, status), INDEX on (account_id, last_message_at)

Migration 10 — CreateMessages:
- id CHAR(36) PK, conversation_id FK→conversations ON DELETE CASCADE
- account_id CHAR(36) NOT NULL
- sender_type ENUM('agent','customer','system')
- content_type ENUM('text','image','video','document','audio','sticker','location','template','interactive','reaction') DEFAULT 'text'
- content_text TEXT NULL
- media_url VARCHAR(500) NULL, media_mime_type VARCHAR(100) NULL, media_filename VARCHAR(255) NULL
- status ENUM('sending','sent','delivered','read','failed') DEFAULT 'sending'
- whatsapp_message_id VARCHAR(255) NULL
- reply_to_message_id CHAR(36) NULL
- template_name VARCHAR(255) NULL
- error_message TEXT NULL
- created_at DATETIME
- INDEX on (conversation_id, created_at), INDEX on (whatsapp_message_id), INDEX on (account_id)

Make sure all migrations use $this->forge syntax and include proper up() and down() methods.
```

### Prompt 1.3 — Database Migrations (Part 2: Remaining tables)

```
Continue creating CI4 migrations for Rovix AI Leads Tool.

Migration 11 — CreateMessageReactions:
- id CHAR(36) PK, message_id FK→messages ON DELETE CASCADE
- conversation_id CHAR(36) NOT NULL
- actor_type ENUM('agent','customer'), actor_id CHAR(36) NULL
- emoji VARCHAR(10) NOT NULL
- created_at DATETIME

Migration 12 — CreateWhatsAppConfig:
- id CHAR(36) PK, account_id UNIQUE NOT NULL
- phone_number_id VARCHAR(100) NULL
- waba_id VARCHAR(100) NULL
- access_token TEXT NULL (stored encrypted via AES-256-GCM)
- business_name VARCHAR(255) NULL
- status ENUM('disconnected','connected','registered') DEFAULT 'disconnected'
- subscription_status ENUM('inactive','active') DEFAULT 'inactive'
- created_at, updated_at

Migration 13 — CreateMessageTemplates:
- id CHAR(36) PK, account_id NOT NULL
- name VARCHAR(255), language VARCHAR(10) DEFAULT 'en'
- category ENUM('marketing','utility','authentication')
- header_type ENUM('none','text','image','video','document') DEFAULT 'none'
- header_content TEXT NULL
- body_text TEXT NOT NULL
- footer_text VARCHAR(60) NULL
- buttons JSON NULL
- sample_values JSON NULL
- status ENUM('draft','pending','approved','rejected','paused','disabled','in_appeal') DEFAULT 'draft'
- meta_template_id VARCHAR(100) NULL
- quality_score VARCHAR(20) NULL
- created_at, updated_at
- INDEX on (account_id, status)

Migration 14 — CreatePipelines:
- id CHAR(36) PK, account_id NOT NULL, name VARCHAR(255), created_at, updated_at

Migration 15 — CreatePipelineStages:
- id CHAR(36) PK, pipeline_id FK→pipelines ON DELETE CASCADE
- name VARCHAR(100), position INT DEFAULT 0, color VARCHAR(7) DEFAULT '#3B82F6'

Migration 16 — CreateDeals:
- id CHAR(36) PK, account_id NOT NULL
- pipeline_id FK→pipelines ON DELETE CASCADE
- stage_id FK→pipeline_stages ON DELETE SET NULL
- contact_id FK→contacts ON DELETE SET NULL
- conversation_id CHAR(36) NULL
- title VARCHAR(255) NOT NULL
- value DECIMAL(12,2) DEFAULT 0, currency VARCHAR(3) DEFAULT 'INR'
- status ENUM('open','won','lost') DEFAULT 'open'
- expected_close_date DATE NULL
- assigned_agent_id CHAR(36) NULL
- notes TEXT NULL
- created_at, updated_at
- INDEX on (account_id, status), INDEX on (pipeline_id, stage_id)

Migration 17 — CreateBroadcasts:
- id CHAR(36) PK, account_id NOT NULL
- name VARCHAR(255), template_name VARCHAR(255), template_language VARCHAR(10) DEFAULT 'en'
- audience_filter JSON NULL
- status ENUM('draft','scheduled','sending','sent','failed') DEFAULT 'draft'
- scheduled_at DATETIME NULL
- total_recipients INT DEFAULT 0, sent_count INT DEFAULT 0, delivered_count INT DEFAULT 0, read_count INT DEFAULT 0, replied_count INT DEFAULT 0, failed_count INT DEFAULT 0
- created_at, updated_at

Migration 18 — CreateBroadcastRecipients:
- id CHAR(36) PK, broadcast_id FK→broadcasts ON DELETE CASCADE
- contact_id FK→contacts ON DELETE SET NULL
- variables JSON NULL
- status ENUM('pending','sent','delivered','read','replied','failed') DEFAULT 'pending'
- whatsapp_message_id VARCHAR(255) NULL, error_message TEXT NULL
- created_at, updated_at

Migration 19 — CreateAutomations:
- id CHAR(36) PK, account_id NOT NULL, user_id CHAR(36) NOT NULL
- name VARCHAR(255) NOT NULL
- trigger_type ENUM('new_message_received','first_inbound_message','keyword_match','new_contact_created','conversation_assigned','tag_added','time_based')
- trigger_config JSON NULL
- is_active TINYINT(1) DEFAULT 1
- execution_count INT DEFAULT 0, last_executed_at DATETIME NULL
- created_at, updated_at

Migration 20 — CreateAutomationSteps:
- id CHAR(36) PK, automation_id FK→automations ON DELETE CASCADE
- parent_step_id CHAR(36) NULL (self-ref)
- branch ENUM('yes','no') NULL
- step_type ENUM('send_message','send_template','add_tag','remove_tag','assign_conversation','update_contact_field','create_deal','wait','condition','send_webhook','close_conversation')
- step_config JSON NOT NULL
- position INT DEFAULT 0

Migration 21 — CreateAutomationLogs:
- id CHAR(36) PK, automation_id FK→automations ON DELETE CASCADE
- contact_id FK→contacts ON DELETE SET NULL
- trigger_event VARCHAR(255), steps_executed JSON NULL
- status ENUM('running','completed','failed','skipped') DEFAULT 'running'
- error_message TEXT NULL, created_at

Migration 22 — CreateFlows (creates 4 tables):
flows: id, account_id, name, is_active, trigger_keywords JSON, execution_count, created_at, updated_at
flow_nodes: id, flow_id FK, node_key VARCHAR(100), node_type ENUM('start','send_message','send_buttons','send_list','send_media','collect_input','condition','set_tag','handoff','end'), config JSON, position_x FLOAT, position_y FLOAT
flow_runs: id, flow_id FK, contact_id FK, conversation_id, status ENUM('active','completed','handed_off','timed_out','failed'), current_node_key VARCHAR(100), vars JSON, meta_message_id VARCHAR(255), started_at, updated_at. UNIQUE INDEX on (flow_id, contact_id, status) WHERE status='active' — use partial unique workaround for MySQL
flow_run_events: id, flow_run_id FK, node_key, event_type, event_data JSON, created_at

Migration 23 — CreateAccountInvitations:
- id CHAR(36) PK, account_id NOT NULL
- role ENUM('admin','agent','viewer') NOT NULL
- token_hash VARCHAR(64) NOT NULL (SHA-256)
- expires_at DATETIME NOT NULL
- accepted_at DATETIME NULL, accepted_by_user_id CHAR(36) NULL
- created_at

Migration 24 — CreateJobQueue:
- id INT UNSIGNED AUTO_INCREMENT PK
- job_type VARCHAR(100) NOT NULL
- payload JSON NOT NULL
- status ENUM('pending','processing','done','failed') DEFAULT 'pending'
- run_after DATETIME NULL
- attempts TINYINT DEFAULT 0, max_retries TINYINT DEFAULT 3
- error TEXT NULL
- created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX on (status, run_after)

Migration 25 — CreateCiSessions:
- Create the ci_sessions table for DatabaseHandler session driver

Run php spark migrate to verify all tables create successfully.
```

### Prompt 1.4 — BaseModel + All Models

```
Create the BaseModel and all model files for Rovix AI Leads Tool.

CRITICAL — BaseModel (app/Models/BaseModel.php):
This replaces Supabase Row-Level Security. Every tenant-scoped model MUST extend this.

class BaseModel extends \CodeIgniter\Model
{
    protected $useAutoIncrement = false; // UUIDs
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Flag to bypass account scoping (for webhook/cron processing)
    protected static bool $bypassAccountScope = false;

    public static function setBypassAccountScope(bool $bypass): void
    {
        static::$bypassAccountScope = $bypass;
    }

    protected function initialize(): void
    {
        parent::initialize();

        // Auto-scope all queries to current account
        if (!static::$bypassAccountScope && $this->hasAccountId()) {
            $accountId = session('account_id');
            if ($accountId) {
                $this->where($this->table . '.account_id', $accountId);
            }
        }
    }

    // Check if this model's table has account_id column
    private function hasAccountId(): bool
    {
        // Tables WITHOUT account_id scoping
        $excludedTables = ['accounts', 'ci_sessions', 'job_queue'];
        return !in_array($this->table, $excludedTables);
    }

    // Auto-generate UUID and inject account_id on insert
    protected $beforeInsert = ['generateUuid', 'injectAccountId'];

    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['id'])) {
            $data['data']['id'] = $this->generateUuidV4();
        }
        return $data;
    }

    protected function injectAccountId(array $data): array
    {
        if ($this->hasAccountId() && empty($data['data']['account_id'])) {
            $data['data']['account_id'] = session('account_id');
        }
        return $data;
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

Now create all model files extending BaseModel. Each model needs: $table, $primaryKey='id', $allowedFields array, and proper $returnType='array'.

Create these models in app/Models/:
AccountModel, ProfileModel, ContactModel, TagModel, ContactTagModel, CustomFieldModel, ContactCustomValueModel, ContactNoteModel, ConversationModel, MessageModel, MessageReactionModel, WhatsAppConfigModel, MessageTemplateModel, PipelineModel, PipelineStageModel, DealModel, BroadcastModel, BroadcastRecipientModel, AutomationModel, AutomationStepModel, AutomationLogModel, FlowModel, FlowNodeModel, FlowRunModel, FlowRunEventModel, AccountInvitationModel, JobQueueModel.

For JobQueueModel: set $useAutoIncrement = true and override hasAccountId to return false.
For AccountModel: override hasAccountId to return false.
For PipelineStageModel: the table is tenant-scoped via pipeline, not directly — join through pipeline.

Also create helper files:
- app/Helpers/auth_helper.php: current_user_id(), current_account_id(), current_profile() — all read from session
- app/Helpers/format_helper.php: format_phone(), format_currency(), format_relative_time(), generate_uuid()
- app/Helpers/role_helper.php: role_rank($role), has_min_role($required), can_send_messages(), can_manage_members(), can_edit_settings(), can_view_only(), can_delete_account(), can_transfer_ownership()

Role hierarchy: owner=4, admin=3, agent=2, viewer=1
```

---

## PHASE 2: Authentication System (Week 1-2)

### Prompt 2.1 — Auth Controller + Filters

```
Build the authentication system for Rovix AI Leads Tool.

Reference the original wacrm files:
- src/app/(auth)/login/page.tsx
- src/app/(auth)/signup/page.tsx
- src/middleware.ts
- src/lib/auth/roles.ts

Create app/Controllers/AuthController.php:

1. login() GET: show login form
2. attemptLogin() POST: validate email+password, password_verify() against profiles.password_hash, start session with user_id + account_id + account_role + full_name, session_regenerate(), redirect to /dashboard
3. signup() GET: show signup form
4. register() POST: validate inputs (email unique, password min 8 chars), create account row, create profile row with password_hash(PASSWORD_BCRYPT), auto-login, redirect to /dashboard
5. forgotPassword() GET: show form
6. logout() POST: session_destroy(), redirect to /login

Create app/Filters/AuthFilter.php:
- Check session('user_id') exists
- Skip for routes: login, signup, forgot-password, api/whatsapp/webhook, join/*
- Redirect to /login if not authenticated
- Redirect to /dashboard if authenticated user visits /login or /signup

Create app/Filters/AccountFilter.php:
- Load profile from DB using session user_id
- Set session: account_id, account_role, full_name, email, avatar_url
- This feeds BaseModel tenant scoping

Create app/Filters/RoleFilter.php:
- Accept minimum required role as parameter
- Check session('account_role') against role hierarchy
- Return 403 if insufficient

Register all filters in app/Config/Filters.php:
- 'auth' → AuthFilter (global except exclusions)
- 'account' → AccountFilter (after auth)
- 'role' → RoleFilter (applied per-route)

Update app/Config/Routes.php with auth routes:
GET/POST /login, /signup, /forgot-password, POST /logout
```

### Prompt 2.2 — Layout Views + Sidebar

```
Create the layout views for Rovix AI Leads Tool with Rovix AI branding.

Use the Rovix AI brand colors from https://rovixai.com/ — dark navy sidebar, clean white content area.

Create app/Views/layouts/main.php:
- HTML5 document with <head> including:
  - <script src="https://cdn.tailwindcss.com"></script>
  - <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  - <link rel="icon" href="/assets/img/favicon.ico">
  - <title>Rovix AI Leads Tool</title>
- Body: flex layout with sidebar (fixed, 256px) + main content area
- Include partials: sidebar.php, header.php, flash_messages.php
- Content rendered via $this->renderSection('content')
- Dark mode support via Tailwind dark: classes + localStorage toggle

Create app/Views/layouts/auth.php:
- Centered card layout for login/signup
- Rovix AI logo at top
- Clean minimal design

Create app/Views/layouts/partials/sidebar.php:
- Dark navy background (#1B2A4A)
- Rovix AI logo at top (text "Rovix" bold + "AI" lighter)
- Navigation links with icons (use simple SVG or text icons):
  - Dashboard (home icon)
  - Inbox (message icon) — with unread badge
  - Contacts (users icon)
  - Pipelines (kanban icon)
  - Broadcasts (megaphone icon)
  - Automations (zap icon)
  - Flows (git-branch icon) — show "Beta" badge
  - Settings (gear icon)
- Active state: lighter background + white text
- Role-aware: hide Settings for viewers, hide Flows if not admin+
- Mobile: hidden by default, toggle via hamburger
- Use Alpine.js for mobile toggle: x-data="{ open: false }"

Create app/Views/layouts/partials/header.php:
- White background, border-bottom
- Left: page title (passed as $pageTitle variable)
- Right: user dropdown (Alpine.js) with: profile name, avatar, Settings link, Logout button
- Mobile: hamburger button that toggles sidebar

Create app/Views/layouts/partials/flash_messages.php:
- Toast notifications for success/error/warning using Alpine.js
- Auto-dismiss after 5 seconds
- session()->getFlashdata('success'), session()->getFlashdata('error')

Create app/Views/auth/login.php:
- Uses auth.php layout
- Email + password fields
- "Sign in to Rovix AI Leads Tool" heading
- Link to signup
- CSRF token via csrf_field()

Create app/Views/auth/signup.php:
- Full name, email, password, confirm password
- "Create your Rovix AI account"
- Link to login

Create app/Views/dashboard/index.php:
- Uses main.php layout
- Placeholder content: "Welcome to Rovix AI Leads Tool" + quick stats cards (empty for now)
- Set $pageTitle = 'Dashboard'

Create app/Controllers/DashboardController.php:
- index(): return view with main layout

Update Routes.php:
- GET / → redirect to /dashboard
- GET /dashboard → DashboardController::index (filtered by auth+account)
```

---

## PHASE 3: WhatsApp Core Integration (Week 2-3)

### Prompt 3.1 — Encryption + Phone Utils + Webhook Signature

```
Port the WhatsApp security layer for Rovix AI Leads Tool.

Reference original wacrm files:
- src/lib/whatsapp/encryption.ts
- src/lib/whatsapp/webhook-signature.ts
- src/lib/whatsapp/phone-utils.ts

Create app/Libraries/WhatsApp/Encryption.php:
- encrypt(string $plaintext): string
  - Get key from env: rovix.encryptionKey (64 hex chars = 32 bytes)
  - Generate random 12-byte IV: random_bytes(12)
  - openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16)
  - Return: bin2hex($iv) . ':' . bin2hex($ciphertext) . ':' . bin2hex($tag)

- decrypt(string $encrypted): string
  - Split by ':'
  - If 3 parts → GCM format: iv:ciphertext:tag
  - If 2 parts → legacy CBC format: iv:ciphertext (backward compat)
  - For GCM: openssl_decrypt with tag
  - For CBC: openssl_decrypt with aes-256-cbc
  - Return plaintext

- isLegacyFormat(string $encrypted): bool — check if 2 parts

Create app/Libraries/WhatsApp/WebhookSignature.php:
- verify(string $rawBody, string $signature, string $appSecret): bool
  - Compute: 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret)
  - Compare using hash_equals() (timing-safe)
  - Return true/false

Create app/Filters/WebhookSignatureFilter.php:
- Read raw body: file_get_contents('php://input')
- Get header: $request->getHeaderLine('X-Hub-Signature-256')
- Get META_APP_SECRET from config
- If no secret configured → return 403 (fail closed)
- If no signature header → return 403
- If verify fails → return 403
- Pass through if valid

Create app/Libraries/WhatsApp/PhoneUtils.php:
- normalize(string $phone): string — strip everything except digits
- isValid(string $phone): bool — at least 10 digits
- format(string $phone): string — display format with country code
```

### Prompt 3.2 — Meta API Client

```
Port the Meta WhatsApp Cloud API client for Rovix AI Leads Tool.

Reference: src/lib/whatsapp/meta-api.ts

Create app/Libraries/WhatsApp/MetaApi.php:

Use PHP cURL for all API calls. Base URL: https://graph.facebook.com/v21.0/

Private method: callApi(string $method, string $url, ?array $data, string $accessToken): array
- Initialize cURL
- Set CURLOPT_TIMEOUT = 30
- Set Authorization: Bearer $accessToken header
- For POST: JSON encode body, set Content-Type: application/json
- Execute, json_decode response
- Log errors
- Return decoded response

Public methods:

1. sendText($phoneNumberId, $accessToken, $to, $text, $replyToMessageId = null)
   POST /{phoneNumberId}/messages
   Body: messaging_product: whatsapp, to, type: text, text: { body }, context: { message_id } if reply

2. sendImage($phoneNumberId, $accessToken, $to, $imageUrl, $caption = null)
3. sendVideo($phoneNumberId, $accessToken, $to, $videoUrl, $caption = null)
4. sendDocument($phoneNumberId, $accessToken, $to, $documentUrl, $filename, $caption = null)
5. sendAudio($phoneNumberId, $accessToken, $to, $audioUrl)
   — All media sends follow same pattern with type-specific payload

6. sendTemplate($phoneNumberId, $accessToken, $to, $templateName, $language, $components = [])
   Body: type: template, template: { name, language: { code }, components }

7. sendReaction($phoneNumberId, $accessToken, $messageId, $emoji)
   Body: type: reaction, reaction: { message_id, emoji }

8. sendInteractiveButtons($phoneNumberId, $accessToken, $to, $bodyText, $buttons, $headerText = null)
   Body: type: interactive, interactive: { type: button, header, body, action: { buttons } }

9. sendInteractiveList($phoneNumberId, $accessToken, $to, $bodyText, $buttonText, $sections)
   Body: type: interactive, interactive: { type: list, body, action: { button, sections } }

10. getMediaUrl($mediaId, $accessToken): string — GET /{mediaId}
11. downloadMedia($url, $accessToken): string — cURL binary download, save to writable/uploads/chat-media/, return local path
12. uploadMedia($phoneNumberId, $accessToken, $filePath, $mimeType): string — POST /{phoneNumberId}/media, multipart form

Also create app/Libraries/WhatsApp/TemplateSendBuilder.php:
- buildComponents($template, $variables): array — Build template components array from template model + per-recipient variables
```

### Prompt 3.3 — Webhook Controller

```
Port the WhatsApp webhook handler for Rovix AI Leads Tool. This is the most critical file — it processes ALL inbound WhatsApp events.

Reference: src/app/api/whatsapp/webhook/route.ts (969 lines)

Create app/Controllers/Api/WebhookController.php:

1. verify() — GET handler for Meta webhook verification:
   - Check hub_mode === 'subscribe'
   - Check hub_verify_token matches config
   - Return hub_challenge as plain text with 200
   - Return 403 if mismatch

2. handle() — POST handler for inbound events:
   - Set BaseModel::setBypassAccountScope(true) — webhook runs without session
   - Parse JSON body
   - For each entry → changes → value:
     a. If 'messages' present → processInboundMessage()
     b. If 'statuses' present → processStatusUpdate()
     c. If 'message_template_status_update' → processTemplateStatus()

3. processInboundMessage($accountId, $message, $contactInfo, $phoneNumberId):
   - Determine message type (text, image, video, document, audio, sticker, location, reaction, interactive)
   - Find WhatsApp config by phoneNumberId → get account_id
   - Find or create contact by phone number (normalize first)
   - Find or create conversation for this contact+account
   - For media types: download via MetaApi::getMediaUrl + downloadMedia
   - For reactions: insert/update/delete in message_reactions
   - For regular messages: insert into messages table
   - Update conversation: last_message_text, last_message_at, unread_count++, status='open' if was closed
   - Dispatch automations to job_queue: type='run_automation', payload with account_id, contact_id, message, trigger_type
   - Dispatch flow check to job_queue: type='check_flow', payload with same data
   - Return 200 immediately (fire-and-forget)

4. processStatusUpdate($status):
   - Find message by whatsapp_message_id
   - Update status: sent/delivered/read/failed
   - If broadcast_recipient exists with this whatsapp_message_id, update that too
   - Increment broadcast counts (sent_count, delivered_count, read_count, failed_count)

5. processTemplateStatus($event):
   - Find template by meta_template_id
   - Update status (approved/rejected/paused/disabled)

Add route in Routes.php:
GET /api/whatsapp/webhook → WebhookController::verify (no auth filter)
POST /api/whatsapp/webhook → WebhookController::handle (webhook signature filter only)
```

### Prompt 3.4 — Job Queue System

```
Build the background job queue system for Rovix AI Leads Tool.

This replaces Node.js in-process async with a MySQL-based queue processed by cron.

Create app/Libraries/JobDispatcher.php:
- dispatch(string $jobType, array $payload, ?string $runAfter = null): int
  - Insert into job_queue: job_type, payload (json_encode), status='pending', run_after, attempts=0
  - Return inserted ID

Create app/Commands/ProcessQueue.php (CI4 Command):
- Command name: queue:process
- Description: Process pending background jobs
- Logic:
  1. SELECT * FROM job_queue WHERE status='pending' AND (run_after IS NULL OR run_after <= NOW()) ORDER BY created_at LIMIT 50
  2. For each job: UPDATE status='processing', attempts++
  3. Switch on job_type:
     - 'send_message' → call MetaApi::sendText with payload data
     - 'run_automation' → call AutomationEngine::processForTrigger() (stub for now)
     - 'check_flow' → call FlowEngine::dispatchInbound() (stub for now)
     - 'send_broadcast_batch' → process batch of template sends (stub for now)
     - 'execute_wait_step' → resume automation from wait step (stub for now)
     - 'send_daily_report' → generate and send daily lead report (stub for now)
  4. On success: UPDATE status='done'
  5. On failure: if attempts < max_retries → status='pending', else status='failed', store error message
  6. Wrap each job in try/catch — one failure must not stop other jobs

Create app/Commands/RunScheduled.php (CI4 Command):
- Command name: run:scheduled
- Runs ProcessQueue + other scheduled tasks
- This is what cPanel cron calls every minute:
  * * * * * cd /path/to/rovix && php spark run:scheduled >> /dev/null 2>&1

Create app/Controllers/Api/SendController.php:
- send() POST: authenticated endpoint to send WhatsApp message
  - Validate: conversation_id, content_type, content_text or media
  - Get WhatsApp config for current account
  - Decrypt access token
  - Call MetaApi based on content_type
  - Insert message row with status='sending'
  - Update message with whatsapp_message_id from Meta response
  - Return JSON response

Create app/Controllers/Api/ReactController.php:
- react() POST: send emoji reaction to a message

Add routes for send and react endpoints.
```

---

## PHASE 4-14: Remaining Prompts

### Prompt 4.1 — Inbox UI (Conversation List + Thread)
### Prompt 4.2 — Inbox Composer (Text + Media + Voice + Templates)
### Prompt 5.1 — Contacts CRUD + Tags
### Prompt 5.2 — Custom Fields + CSV Import
### Prompt 6.1 — Pipelines + Stages + Kanban Board
### Prompt 7.1 — Message Template Manager
### Prompt 8.1 — Broadcasts + Batch Send
### Prompt 9.1 — Automation Engine (Triggers)
### Prompt 9.2 — Automation Engine (Actions + Conditions + Waits)
### Prompt 10.1 — Flow Engine (Node Runner)
### Prompt 10.2 — Flow Visual Editor (Drawflow.js)
### Prompt 11.1 — Dashboard + Analytics + Daily Report
### Prompt 12.1 — Team Management + Invitations
### Prompt 13.1 — Settings Module
### Prompt 14.1 — Security Hardening

*(These follow the exact same pattern — detailed prompts referencing original wacrm source files, specifying exact PHP files to create, database tables involved, and acceptance criteria. Continuing below with the most critical remaining ones.)*

---

## PHASE 11 — Daily Lead Report Automation

### Prompt 11.2 — Daily Morning Lead Status Report

```
Build the automated daily morning lead status report for Rovix AI Leads Tool.

This is a KEY FEATURE: every morning, a cron job sends a WhatsApp message to configured phone numbers (the founder and Priyanvi) with a complete lead status summary.

Create app/Commands/DailyLeadReport.php (CI4 Command):
- Command name: report:daily
- Description: Send daily lead status report via WhatsApp

Logic:
1. Get configured phone numbers from env: rovix.dailyReportPhones (comma-separated)
2. Get WhatsApp config for sending (use the first active account's config)
3. Set BaseModel::setBypassAccountScope(true)

4. Query the database for these metrics:
   a. New leads yesterday: COUNT contacts WHERE created_at >= yesterday 00:00 AND < today 00:00
   b. Open deals by stage: SELECT stage_name, COUNT(*), SUM(value) FROM deals JOIN pipeline_stages GROUP BY stage
   c. Unattended conversations: COUNT conversations WHERE status='open' AND last_message_at < NOW() - INTERVAL 4 HOUR AND sender of last message was 'customer'
   d. Agent performance yesterday: COUNT messages grouped by sender agent_id WHERE created_at yesterday
   e. Broadcast results: any broadcasts completed yesterday — sent_count, delivered_count, read_count, replied_count
   f. Hot leads: contacts tagged with 'Hot Lead' or similar high-priority tag
   g. Deals closing this week: deals WHERE expected_close_date BETWEEN today AND today+7

5. Format as a WhatsApp-friendly text message (plain text, use emojis for visual):
   Example format:
   ```
   📊 *Rovix AI — Daily Lead Report*
   📅 {date}

   📥 *New Leads Yesterday:* {count}
   {list of names if < 10}

   📋 *Pipeline Status:*
   • Qualified: {count} deals (₹{value})
   • Proposal: {count} deals (₹{value})
   • Negotiation: {count} deals (₹{value})
   • Won yesterday: {count} (₹{value})

   ⚠️ *Needs Attention:*
   • {unattended_count} conversations waiting 4+ hours
   • {closing_this_week} deals closing this week

   👥 *Agent Activity Yesterday:*
   • {agent_name}: {msg_count} messages
   • {agent_name}: {msg_count} messages

   📢 *Broadcast Results:*
   • Sent: {sent} | Delivered: {delivered} | Read: {read}

   🔥 *Hot Leads:* {count}
   ```

6. Send via MetaApi::sendText() to each configured phone number
7. Log success/failure

Register in cPanel cron — add to RunScheduled.php:
- Check if current hour is 8 AM (IST) → dispatch report:daily
- Or create a separate cron entry: 0 8 * * * cd /path && php spark report:daily

Also add to the Settings UI (Phase 13) — admin can configure:
- Report time (default 8:00 AM)
- Report phone numbers
- Which metrics to include
- Enable/disable toggle
```

---

## PHASE 14 — Security Hardening

### Prompt 14.1 — Security Headers + Rate Limiting + Final Audit

```
Implement security hardening for Rovix AI Leads Tool.

Create app/Filters/SecurityHeadersFilter.php:
Add these response headers:
- Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera=(), microphone=(self), geolocation=()
- Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.tailwindcss.com cdn.jsdelivr.net cdnjs.cloudflare.com unpkg.com; style-src 'self' 'unsafe-inline' cdn.tailwindcss.com; img-src 'self' data: blob: *.whatsapp.net; connect-src 'self'; font-src 'self' cdn.jsdelivr.net; media-src 'self' blob:

Create app/Filters/RateLimitFilter.php:
- Use job_queue table or in-memory cache for counters
- Per-user, per-endpoint rate limits:
  - /api/whatsapp/send: 60/min
  - /api/whatsapp/react: 120/min
  - /broadcasts/*/send: 5/min
  - /account/invite: 30/min
  - /join/*: 10/min (invite redeem)
  - Default: 120/min
- Return 429 with Retry-After header on limit exceeded

Security audit checklist — verify:
1. Every model extends BaseModel (tenant isolation)
2. All views use esc() for dynamic output (XSS prevention)
3. All forms include csrf_field() (CSRF protection)
4. No raw SQL without parameter bindings (SQL injection)
5. File uploads validated: MIME whitelist, max size, .htaccess denies PHP execution in writable/uploads/
6. Passwords stored as bcrypt via password_hash()
7. Session regenerates on login
8. WhatsApp tokens encrypted at rest (AES-256-GCM)
9. Webhook signature verified with hash_equals()
10. CI_ENVIRONMENT=production in .env on deploy (no stack traces)

Create writable/uploads/.htaccess:
<FilesMatch "\.php$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

Register SecurityHeadersFilter and RateLimitFilter in Config/Filters.php as global 'after' and 'before' filters respectively.
```

---

## Usage Notes for Claude Code

1. **Run prompts in order** — Phase 1 → 2 → 3 → ... Each builds on previous
2. **Test after each phase** — Run `php spark serve` and verify
3. **Keep original wacrm source accessible** — Claude Code can reference TypeScript implementations
4. **After Phase 4** — you have a working WhatsApp inbox (MVP)
5. **After Phase 11** — daily reports are sending automatically
6. **Phase 14 is mandatory before production deploy**

For the remaining phases (4-10, 12-13), use this pattern for each prompt:
```
Build [MODULE NAME] for Rovix AI Leads Tool.

Reference original wacrm files:
- [list specific .ts/.tsx files from wacrm-main/src/]

Create these PHP files:
- [Controller file with methods]
- [View files with layout]
- [JS file for interactivity]

Database tables involved: [list tables]

Features to implement:
- [detailed feature list from PRD]

Acceptance criteria:
- [testable pass/fail conditions]

Add routes to Config/Routes.php for all new endpoints.
```
