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

IMPORTANT: Each migration must include both up() and down() methods for rollback capability.

Example down() method:
public function down()
{
    $this->forge->dropTable('table_name', true);
}

Make sure all migrations use $this->forge syntax and include proper up() and down() methods.
```

### Prompt 1.3 — Database Migrations (Part 2: Remaining tables)

```
Continue creating CI4 migrations for Rovix AI Leads Tool. Include down() methods for rollback.

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
flow_runs: id, flow_id FK, contact_id FK, conversation_id, status ENUM('active','completed','handed_off','timed_out','failed'), current_node_key VARCHAR(100), vars JSON, meta_message_id VARCHAR(255), started_at, updated_at
flow_run_events: id, flow_run_id FK, node_key, event_type, event_data JSON, created_at

For flow_runs: Use a regular unique index instead of partial WHERE clause for MySQL compatibility

Migration 23 — CreateAccountInvitations:
- id CHAR(36) PK, account_id NOT NULL
- role ENUM('admin','agent','viewer') NOT NULL
- token_hash VARCHAR(64) NOT NULL (SHA-256)
- expires_at DATETIME NOT NULL
- accepted_at DATETIME NULL, accepted_by_user_id CHAR(36) NULL
- created_at

Migration 24 — CreateJobQueue (IMPROVED):
- id INT UNSIGNED AUTO_INCREMENT PK
- job_type VARCHAR(100) NOT NULL
- payload JSON NOT NULL
- status ENUM('pending','processing','done','failed') DEFAULT 'pending'
- priority TINYINT DEFAULT 0 (higher = more urgent)
- locked_until DATETIME NULL (for concurrency control)
- run_after DATETIME NULL
- attempts TINYINT DEFAULT 0, max_retries TINYINT DEFAULT 3
- error TEXT NULL
- failed_attempts_log JSON NULL
- created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX on (status, priority, run_after)
- INDEX on (locked_until)

Migration 25 — CreateCiSessions:
- Create the ci_sessions table for DatabaseHandler session driver using CI4's session migration template

Migration 26 — CreateMediaFiles:
- id CHAR(36) PK
- account_id CHAR(36) NOT NULL
- file_path VARCHAR(500) NOT NULL
- mime_type VARCHAR(100) NOT NULL
- file_size INT UNSIGNED NOT NULL
- original_filename VARCHAR(255)
- media_type ENUM('image','video','document','audio') NOT NULL
- created_at DATETIME
- last_accessed_at DATETIME
- INDEX on (account_id)
- INDEX on (created_at) for cleanup jobs

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
        $excludedTables = ['accounts', 'ci_sessions', 'job_queue', 'media_files'];
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
AccountModel, ProfileModel, ContactModel, TagModel, ContactTagModel, CustomFieldModel, ContactCustomValueModel, ContactNoteModel, ConversationModel, MessageModel, MessageReactionModel, WhatsAppConfigModel, MessageTemplateModel, PipelineModel, PipelineStageModel, DealModel, BroadcastModel, BroadcastRecipientModel, AutomationModel, AutomationStepModel, AutomationLogModel, FlowModel, FlowNodeModel, FlowRunModel, FlowRunEventModel, AccountInvitationModel, JobQueueModel, MediaFileModel.

For JobQueueModel: set $useAutoIncrement = true, override hasAccountId to return false, $useTimestamps = false
For AccountModel: override hasAccountId to return false
For MediaFileModel: do NOT exclude from account scoping (it has account_id)

Also create helper files:
- app/Helpers/auth_helper.php: current_user_id(), current_account_id(), current_profile() — all read from session
- app/Helpers/format_helper.php: format_phone(), format_currency(), format_relative_time(), generate_uuid()
- app/Helpers/role_helper.php: role_rank($role), has_min_role($required), can_send_messages(), can_manage_members(), can_edit_settings(), can_view_only(), can_delete_account(), can_transfer_ownership()

Role hierarchy: owner=4, admin=3, agent=2, viewer=1
```

### Testing Phase 1

Run these commands in sequence:

```bash
# 1. Verify project created
cd C:\xampp\htdocs\rovix-ai-leads-tool
ls -la

# 2. Check .env file exists and has all required keys
cat .env | grep -E "database|whatsapp|rovix"

# 3. Run migrations
php spark migrate

# 4. Verify all 26 migrations ran
php spark migrate:status

# 5. Check MySQL database
mysql -u root -e "USE rovix_crm; SHOW TABLES;"

# 6. Verify BaseModel exists
cat app/Models/BaseModel.php | head -20

# 7. Test rollback (optional)
php spark migrate:rollback
php spark migrate

# 8. Verify helpers loaded
php spark list | grep env:check
```

**Pass Criteria:**
- ✅ All 26 migrations show "Y" in migrate:status
- ✅ Database has 25 tables (26 migrations, some create multiple tables)
- ✅ BaseModel has bypassAccountScope and tenant scoping logic
- ✅ All models extend BaseModel
- ✅ Rollback works without errors
- ✅ Helper functions exist and are autoloaded

**Common Issues:**
- Migration fails: Check MySQL version is 8.0+, check user has CREATE permission
- UUID generation fails: Check openssl extension is loaded
- Session table missing: Verify migration 25 used CI4's session migration template

---
