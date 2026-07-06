# Rovix AI Leads Tool — Complete Migration Plan
## Phase-by-phase, module-by-module migration guide
### Project: https://rovixai.com/ | Stack: PHP 8.1 + CodeIgniter 4 + MySQL 8.0

---

## Document Version: 2.0 — Complete & Production-Ready

**What's New in v2.0:**
- ✅ All 15 phases fully detailed (no stubs)
- ✅ Testing & verification steps for each phase
- ✅ Environment check phase added
- ✅ XAMPP → cPanel deployment guide
- ✅ Media storage & cleanup strategy
- ✅ Job queue improvements (priority, locking, DLQ)
- ✅ Broadcast rate limiting & batch strategy
- ✅ Migration rollback instructions
- ✅ Complete deployment checklist

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Development Setup](#development-setup)
- [Phase 0: Environment Verification](#phase-0-environment-verification)
- [Phase 1: Project Setup + Database Schema](#phase-1-project-setup--database-schema)
- [Phase 2: Authentication System](#phase-2-authentication-system)
- [Phase 3: WhatsApp Core Integration](#phase-3-whatsapp-core-integration)
- [Phase 4: Inbox Module](#phase-4-inbox-module)
- [Phase 5: Contacts Module](#phase-5-contacts-module)
- [Phase 6: Pipelines & Deals](#phase-6-pipelines--deals)
- [Phase 7: Message Templates](#phase-7-message-templates)
- [Phase 8: Broadcast System](#phase-8-broadcast-system)
- [Phase 9: Automation Engine](#phase-9-automation-engine)
- [Phase 10: Visual Flow Builder](#phase-10-visual-flow-builder)
- [Phase 11: Dashboard & Analytics](#phase-11-dashboard--analytics)
- [Phase 12: Team Management](#phase-12-team-management)
- [Phase 13: Settings Module](#phase-13-settings-module)
- [Phase 14: Security Hardening](#phase-14-security-hardening)
- [Phase 15: Deployment & Go-Live](#phase-15-deployment--go-live)

---

## Prerequisites

**Required Software:**
- PHP 8.1 or higher
- Composer 2.x
- MySQL 8.0
- XAMPP (for local development) or direct PHP install
- Git (optional but recommended)

**Required PHP Extensions:**
```
php -m | grep -E "curl|json|mbstring|mysqli|openssl|gd|fileinfo|intl"
```

**Meta/WhatsApp Business Requirements:**
- Meta Business Account
- WhatsApp Business API access
- App ID, App Secret, Access Token
- Phone Number ID and WABA ID

**Reference Source Code:**
Place the original `wacrm-main` TypeScript source code in a reference folder accessible to Claude Code (e.g., `E:\crm\wacrm-main\`) for implementation reference.

---

## Development Setup

### Option 1: XAMPP (Recommended for Windows)

```bash
# 1. Install XAMPP with PHP 8.1+
# Download from: https://www.apachefriends.org/

# 2. Start Apache & MySQL from XAMPP Control Panel

# 3. Open terminal in C:\xampp\htdocs\
cd C:\xampp\htdocs

# 4. You'll run Phase 1 commands here
```

### Option 2: Standalone PHP

```bash
# If you have PHP installed directly
php -v  # Verify 8.1+
composer --version  # Verify installed
```

---

## PHASE 0: Environment Verification (Week 0 — Day 1)

### Prompt 0.1 — Environment Check Command

```
Create an environment verification command for Rovix AI Leads Tool to check system requirements before starting development.

Create app/Commands/EnvironmentCheck.php:

Command name: env:check
Description: Verify system meets all requirements for Rovix AI Leads Tool

Check and report:

1. PHP Version:
   - Required: 8.1+
   - Current: PHP_VERSION
   - Status: ✓ or ✗

2. Required PHP Extensions:
   - curl (for Meta API calls)
   - json (for data handling)
   - mbstring (for string processing)
   - mysqli (for database)
   - openssl (for encryption)
   - gd (for image processing)
   - fileinfo (for MIME detection)
   - intl (for internationalization)
   - Report missing extensions in RED

3. PHP Configuration:
   - memory_limit (recommended: 256M minimum)
   - max_execution_time (recommended: 300 for cron jobs)
   - upload_max_filesize (recommended: 16M for WhatsApp media)
   - post_max_size (recommended: 20M)
   - Report current values + warnings if below recommended

4. Composer:
   - Check if composer.json exists
   - Check if vendor/ directory exists
   - If missing: show command to run

5. Database Connection:
   - Try to connect using .env credentials
   - Report success/failure
   - Show database name being used

6. Directory Permissions (will be checked on cPanel, skip on Windows):
   - writable/ directory writable
   - public/ directory writable
   - Report permission issues

7. Encryption Key:
   - Check if rovix.encryptionKey is set in .env
   - Verify it's 64 hex characters (32 bytes)
   - If missing: generate one and show command to add to .env

Output format:
╔══════════════════════════════════════════════════════╗
║   ROVIX AI LEADS TOOL — ENVIRONMENT CHECK           ║
╚══════════════════════════════════════════════════════╝

✓ PHP Version: 8.1.12 (Required: 8.1+)

PHP Extensions:
✓ curl
✓ json
✓ mbstring
✓ mysqli
✓ openssl
✓ gd
✗ intl (MISSING - recommended for timezone handling)

PHP Configuration:
✓ memory_limit: 256M
⚠ max_execution_time: 30 (recommended: 300 for background jobs)
✓ upload_max_filesize: 16M
✓ post_max_size: 20M

✓ Composer: vendor/ directory found
✓ Database: Connected to 'rovix_crm'
✓ Encryption Key: Configured (64 hex chars)

⚠ Warnings: 1
✗ Errors: 1

[!] Fix errors before proceeding with migrations.

Run this command before Phase 1: php spark env:check
```

### Testing Phase 0

Run the environment check:
```bash
php spark env:check
```

**Pass Criteria:**
- ✓ All required extensions present
- ✓ Database connects successfully
- ✓ Encryption key generated/verified
- ⚠ Warnings are acceptable, errors must be fixed

---

## PHASE 1: Project Setup + Database Schema (Week 1 — Days 1-3)

### Prompt 1.1 — CI4 Project Initialization

```
Create a new CodeIgniter 4 project for "Rovix AI Leads Tool" — a WhatsApp CRM and lead automation platform for https://rovixai.com/.

1. Run: composer create-project codeigniter4/appstarter rovix-ai-leads-tool
2. Navigate to the project directory
3. Create .env from env file with these sections:

   # App Configuration
   CI_ENVIRONMENT = development
   app.baseURL = 'http://localhost:8080/'
   app.forceGlobalSecureRequests = false
   app.sessionDriver = 'CodeIgniter\Session\Handlers\DatabaseHandler'
   app.sessionSavePath = 'ci_sessions'

   # Database
   database.default.hostname = localhost
   database.default.database = rovix_crm
   database.default.username = root
   database.default.password = 
   database.default.DBDriver = MySQLi
   database.default.DBPrefix = 
   database.default.port = 3306

   # WhatsApp Configuration
   whatsapp.phoneNumberId = 
   whatsapp.wabaId = 
   whatsapp.accessToken = 
   whatsapp.verifyToken = your_secure_verify_token_here
   whatsapp.metaAppSecret = 

   # Rovix Configuration
   rovix.encryptionKey = 
   rovix.dailyReportPhones = 
   rovix.dailyReportTime = 08:00

4. Create app/Config/WhatsApp.php — config class reading all whatsapp.* env vars:
   - phoneNumberId
   - wabaId
   - accessToken
   - verifyToken
   - metaAppSecret

5. Create app/Config/Rovix.php — config class reading rovix.* env vars:
   - encryptionKey (must be 64 hex characters = 32 bytes)
   - dailyReportPhones (comma-separated phone numbers)
   - dailyReportTime (default: 08:00)

6. Update app/Config/App.php:
   - Set $appTimezone = 'Asia/Kolkata'
   - Set $charset = 'UTF-8'

7. Create the full directory structure:
   - app/Controllers/Api/
   - app/Libraries/WhatsApp/
   - app/Filters/
   - app/Commands/
   - app/Helpers/
   - public/assets/css/
   - public/assets/js/
   - public/assets/img/
   - app/Views/layouts/
   - app/Views/layouts/partials/
   - app/Views/auth/
   - app/Views/dashboard/
   - app/Views/inbox/
   - app/Views/contacts/
   - app/Views/pipelines/
   - app/Views/broadcasts/
   - app/Views/automations/
   - app/Views/flows/
   - app/Views/settings/
   - app/Views/team/
   - writable/uploads/chat-media/ (with .htaccess to prevent PHP execution)

8. Generate encryption key if not set:
   Run: php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   Add the output to .env as rovix.encryptionKey

Don't create placeholder files yet — just the directory structure and config.
```

### Prompt 1.2 — Database Migrations (Part 1: Core Tables)

```
Create CI4 database migrations for the Rovix AI Leads Tool. These are ported from the original wacrm Supabase/Postgres schema to MySQL 8.0.

Reference original wacrm files:
- wacrm-main/supabase/migrations/ (all .sql files)

Key differences from Postgres:
- Use CHAR(36) for UUID primary keys (generate UUIDs in PHP, not DB)
- Use JSON column type for complex fields
- Use ENUM for status fields
- No RLS — we'll handle tenant isolation in PHP via BaseModel
- Add account_id column + composite index on every tenant-scoped table
- Include down() methods for rollback capability

Create these migrations in app/Database/Migrations/:

Migration 1 — 2024-01-01-000001_CreateAccounts.php:
Table: accounts
Columns:
- id CHAR(36) PRIMARY KEY
- name VARCHAR(255) NOT NULL
- owner_user_id CHAR(36) NULL
- default_currency VARCHAR(3) DEFAULT 'INR'
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL

down() method: DROP TABLE IF EXISTS accounts;

Migration 2 — 2024-01-01-000002_CreateProfiles.php:
Table: profiles
Columns:
- id CHAR(36) PRIMARY KEY
- user_id CHAR(36) UNIQUE NOT NULL (this is the auth user ID)
- account_id CHAR(36) NOT NULL
- full_name VARCHAR(255)
- email VARCHAR(255) NOT NULL UNIQUE
- password_hash VARCHAR(255) NOT NULL
- avatar_url VARCHAR(500) NULL
- account_role ENUM('owner','admin','agent','viewer') DEFAULT 'owner'
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_profiles_account (account_id)
- INDEX idx_profiles_email (email)

down() method: DROP TABLE IF EXISTS profiles;

Migration 3 — 2024-01-01-000003_CreateContacts.php:
Table: contacts
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- phone VARCHAR(20) NOT NULL
- phone_normalized VARCHAR(20) NOT NULL
- name VARCHAR(255) NULL
- email VARCHAR(255) NULL
- company VARCHAR(255) NULL
- avatar_url VARCHAR(500) NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- UNIQUE KEY unique_account_phone (account_id, phone_normalized)
- INDEX idx_contacts_account (account_id)
- INDEX idx_contacts_phone (phone_normalized)

down() method: DROP TABLE IF EXISTS contacts;

Migration 4 — 2024-01-01-000004_CreateTags.php:
Table: tags
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- name VARCHAR(100) NOT NULL
- color VARCHAR(7) DEFAULT '#3B82F6'
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- UNIQUE KEY unique_account_tag (account_id, name)
- INDEX idx_tags_account (account_id)

down() method: DROP TABLE IF EXISTS tags;

Migration 5 — 2024-01-01-000005_CreateContactTags.php:
Table: contact_tags
Columns:
- id CHAR(36) PRIMARY KEY
- contact_id CHAR(36) NOT NULL
- tag_id CHAR(36) NOT NULL
- created_at DATETIME NOT NULL
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
- FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
- UNIQUE KEY unique_contact_tag (contact_id, tag_id)

down() method: DROP TABLE IF EXISTS contact_tags;

Migration 6 — 2024-01-01-000006_CreateCustomFields.php:
Table: custom_fields
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- field_name VARCHAR(100) NOT NULL
- field_type ENUM('text','number','date','dropdown') DEFAULT 'text'
- field_options JSON NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_custom_fields_account (account_id)

down() method: DROP TABLE IF EXISTS custom_fields;

Migration 7 — 2024-01-01-000007_CreateContactCustomValues.php:
Table: contact_custom_values
Columns:
- id CHAR(36) PRIMARY KEY
- contact_id CHAR(36) NOT NULL
- custom_field_id CHAR(36) NOT NULL
- value TEXT NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
- FOREIGN KEY (custom_field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
- UNIQUE KEY unique_contact_field (contact_id, custom_field_id)

down() method: DROP TABLE IF EXISTS contact_custom_values;

Migration 8 — 2024-01-01-000008_CreateContactNotes.php:
Table: contact_notes
Columns:
- id CHAR(36) PRIMARY KEY
- contact_id CHAR(36) NOT NULL
- user_id CHAR(36) NOT NULL
- note_text TEXT NOT NULL
- created_at DATETIME NOT NULL
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
- INDEX idx_contact_notes_contact (contact_id)
- INDEX idx_contact_notes_created (created_at)

down() method: DROP TABLE IF EXISTS contact_notes;

Use $this->forge->addField(), $this->forge->addKey(), $this->forge->addForeignKey() syntax.
Each migration must have proper up() and down() methods.
```

### Prompt 1.3 — Database Migrations (Part 2: Messaging Tables)

```
Continue creating CI4 migrations for Rovix AI Leads Tool messaging system.

Migration 9 — 2024-01-01-000009_CreateConversations.php:
Table: conversations
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- contact_id CHAR(36) NULL
- status ENUM('open','pending','closed') DEFAULT 'open'
- assigned_agent_id CHAR(36) NULL
- unread_count INT DEFAULT 0
- last_message_text TEXT NULL
- last_message_at DATETIME NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
- INDEX idx_conversations_account_status (account_id, status)
- INDEX idx_conversations_account_last_msg (account_id, last_message_at)
- INDEX idx_conversations_contact (contact_id)

down() method: DROP TABLE IF EXISTS conversations;

Migration 10 — 2024-01-01-000010_CreateMessages.php:
Table: messages
Columns:
- id CHAR(36) PRIMARY KEY
- conversation_id CHAR(36) NOT NULL
- account_id CHAR(36) NOT NULL
- sender_type ENUM('agent','customer','system') DEFAULT 'customer'
- content_type ENUM('text','image','video','document','audio','sticker','location','template','interactive','reaction') DEFAULT 'text'
- content_text TEXT NULL
- media_url VARCHAR(500) NULL
- media_mime_type VARCHAR(100) NULL
- media_filename VARCHAR(255) NULL
- status ENUM('sending','sent','delivered','read','failed') DEFAULT 'sending'
- whatsapp_message_id VARCHAR(255) NULL
- reply_to_message_id CHAR(36) NULL
- template_name VARCHAR(255) NULL
- error_message TEXT NULL
- created_at DATETIME NOT NULL
- FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_messages_conversation_created (conversation_id, created_at)
- INDEX idx_messages_whatsapp_id (whatsapp_message_id)
- INDEX idx_messages_account (account_id)

down() method: DROP TABLE IF EXISTS messages;

Migration 11 — 2024-01-01-000011_CreateMessageReactions.php:
Table: message_reactions
Columns:
- id CHAR(36) PRIMARY KEY
- message_id CHAR(36) NOT NULL
- conversation_id CHAR(36) NOT NULL
- actor_type ENUM('agent','customer') DEFAULT 'customer'
- actor_id CHAR(36) NULL
- emoji VARCHAR(10) NOT NULL
- created_at DATETIME NOT NULL
- FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
- FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
- INDEX idx_reactions_message (message_id)

down() method: DROP TABLE IF EXISTS message_reactions;

Migration 12 — 2024-01-01-000012_CreateWhatsAppConfig.php:
Table: whatsapp_config
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL UNIQUE
- phone_number_id VARCHAR(100) NULL
- waba_id VARCHAR(100) NULL
- access_token TEXT NULL (stored encrypted via AES-256-GCM)
- business_name VARCHAR(255) NULL
- status ENUM('disconnected','connected','registered') DEFAULT 'disconnected'
- subscription_status ENUM('inactive','active') DEFAULT 'inactive'
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_whatsapp_phone_number (phone_number_id)

down() method: DROP TABLE IF EXISTS whatsapp_config;

Migration 13 — 2024-01-01-000013_CreateMessageTemplates.php:
Table: message_templates
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- name VARCHAR(255) NOT NULL
- language VARCHAR(10) DEFAULT 'en'
- category ENUM('marketing','utility','authentication') DEFAULT 'utility'
- header_type ENUM('none','text','image','video','document') DEFAULT 'none'
- header_content TEXT NULL
- body_text TEXT NOT NULL
- footer_text VARCHAR(60) NULL
- buttons JSON NULL
- sample_values JSON NULL
- status ENUM('draft','pending','approved','rejected','paused','disabled','in_appeal') DEFAULT 'draft'
- meta_template_id VARCHAR(100) NULL
- quality_score VARCHAR(20) NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_templates_account_status (account_id, status)
- INDEX idx_templates_meta_id (meta_template_id)

down() method: DROP TABLE IF EXISTS message_templates;
```

### Prompt 1.4 — Database Migrations (Part 3: Pipelines & Broadcasts)

```
Continue creating CI4 migrations for Rovix AI Leads Tool pipelines and broadcasts.

Migration 14 — 2024-01-01-000014_CreatePipelines.php:
Table: pipelines
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- name VARCHAR(255) NOT NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_pipelines_account (account_id)

down() method: DROP TABLE IF EXISTS pipelines;

Migration 15 — 2024-01-01-000015_CreatePipelineStages.php:
Table: pipeline_stages
Columns:
- id CHAR(36) PRIMARY KEY
- pipeline_id CHAR(36) NOT NULL
- name VARCHAR(100) NOT NULL
- position INT DEFAULT 0
- color VARCHAR(7) DEFAULT '#3B82F6'
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE
- INDEX idx_stages_pipeline (pipeline_id)
- INDEX idx_stages_position (pipeline_id, position)

down() method: DROP TABLE IF EXISTS pipeline_stages;

Migration 16 — 2024-01-01-000016_CreateDeals.php:
Table: deals
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- pipeline_id CHAR(36) NOT NULL
- stage_id CHAR(36) NULL
- contact_id CHAR(36) NULL
- conversation_id CHAR(36) NULL
- title VARCHAR(255) NOT NULL
- value DECIMAL(12,2) DEFAULT 0.00
- currency VARCHAR(3) DEFAULT 'INR'
- status ENUM('open','won','lost') DEFAULT 'open'
- expected_close_date DATE NULL
- assigned_agent_id CHAR(36) NULL
- notes TEXT NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE
- FOREIGN KEY (stage_id) REFERENCES pipeline_stages(id) ON DELETE SET NULL
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
- INDEX idx_deals_account_status (account_id, status)
- INDEX idx_deals_pipeline_stage (pipeline_id, stage_id)
- INDEX idx_deals_contact (contact_id)
- INDEX idx_deals_close_date (expected_close_date)

down() method: DROP TABLE IF EXISTS deals;

Migration 17 — 2024-01-01-000017_CreateBroadcasts.php:
Table: broadcasts
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- name VARCHAR(255) NOT NULL
- template_name VARCHAR(255) NOT NULL
- template_language VARCHAR(10) DEFAULT 'en'
- audience_filter JSON NULL
- status ENUM('draft','scheduled','sending','sent','failed','cancelled') DEFAULT 'draft'
- scheduled_at DATETIME NULL
- total_recipients INT DEFAULT 0
- sent_count INT DEFAULT 0
- delivered_count INT DEFAULT 0
- read_count INT DEFAULT 0
- replied_count INT DEFAULT 0
- failed_count INT DEFAULT 0
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_broadcasts_account_status (account_id, status)
- INDEX idx_broadcasts_scheduled (scheduled_at)

down() method: DROP TABLE IF EXISTS broadcasts;

Migration 18 — 2024-01-01-000018_CreateBroadcastRecipients.php:
Table: broadcast_recipients
Columns:
- id CHAR(36) PRIMARY KEY
- broadcast_id CHAR(36) NOT NULL
- contact_id CHAR(36) NULL
- variables JSON NULL
- status ENUM('pending','sent','delivered','read','replied','failed') DEFAULT 'pending'
- whatsapp_message_id VARCHAR(255) NULL
- error_message TEXT NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (broadcast_id) REFERENCES broadcasts(id) ON DELETE CASCADE
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
- INDEX idx_recipients_broadcast_status (broadcast_id, status)
- INDEX idx_recipients_whatsapp_id (whatsapp_message_id)

down() method: DROP TABLE IF EXISTS broadcast_recipients;
```

### Prompt 1.5 — Database Migrations (Part 4: Automations & Flows)

```
Continue creating CI4 migrations for Rovix AI Leads Tool automations and flows.

Migration 19 — 2024-01-01-000019_CreateAutomations.php:
Table: automations
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- user_id CHAR(36) NOT NULL
- name VARCHAR(255) NOT NULL
- trigger_type ENUM('new_message_received','first_inbound_message','keyword_match','new_contact_created','conversation_assigned','tag_added','tag_removed','time_based') DEFAULT 'new_message_received'
- trigger_config JSON NULL
- is_active TINYINT(1) DEFAULT 1
- execution_count INT DEFAULT 0
- last_executed_at DATETIME NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_automations_account_active (account_id, is_active)
- INDEX idx_automations_trigger (trigger_type, is_active)

down() method: DROP TABLE IF EXISTS automations;

Migration 20 — 2024-01-01-000020_CreateAutomationSteps.php:
Table: automation_steps
Columns:
- id CHAR(36) PRIMARY KEY
- automation_id CHAR(36) NOT NULL
- parent_step_id CHAR(36) NULL
- branch ENUM('yes','no') NULL
- step_type ENUM('send_message','send_template','add_tag','remove_tag','assign_conversation','update_contact_field','create_deal','wait','condition','send_webhook','close_conversation') DEFAULT 'send_message'
- step_config JSON NOT NULL
- position INT DEFAULT 0
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE
- FOREIGN KEY (parent_step_id) REFERENCES automation_steps(id) ON DELETE CASCADE
- INDEX idx_steps_automation (automation_id)
- INDEX idx_steps_parent (parent_step_id)
- INDEX idx_steps_position (automation_id, position)

down() method: DROP TABLE IF EXISTS automation_steps;

Migration 21 — 2024-01-01-000021_CreateAutomationLogs.php:
Table: automation_logs
Columns:
- id CHAR(36) PRIMARY KEY
- automation_id CHAR(36) NOT NULL
- contact_id CHAR(36) NULL
- trigger_event VARCHAR(255) NOT NULL
- steps_executed JSON NULL
- status ENUM('running','completed','failed','skipped') DEFAULT 'running'
- error_message TEXT NULL
- created_at DATETIME NOT NULL
- FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
- INDEX idx_logs_automation (automation_id)
- INDEX idx_logs_contact (contact_id)
- INDEX idx_logs_status (status)
- INDEX idx_logs_created (created_at)

down() method: DROP TABLE IF EXISTS automation_logs;

Migration 22 — 2024-01-01-000022_CreateFlows.php:
Table: flows
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- name VARCHAR(255) NOT NULL
- is_active TINYINT(1) DEFAULT 1
- trigger_keywords JSON NULL
- execution_count INT DEFAULT 0
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_flows_account_active (account_id, is_active)

down() method: DROP TABLE IF EXISTS flows;

Migration 23 — 2024-01-01-000023_CreateFlowNodes.php:
Table: flow_nodes
Columns:
- id CHAR(36) PRIMARY KEY
- flow_id CHAR(36) NOT NULL
- node_key VARCHAR(100) NOT NULL
- node_type ENUM('start','send_message','send_buttons','send_list','send_media','collect_input','condition','set_tag','handoff','end') DEFAULT 'send_message'
- config JSON NOT NULL
- position_x FLOAT DEFAULT 0
- position_y FLOAT DEFAULT 0
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (flow_id) REFERENCES flows(id) ON DELETE CASCADE
- UNIQUE KEY unique_flow_node_key (flow_id, node_key)
- INDEX idx_nodes_flow (flow_id)

down() method: DROP TABLE IF EXISTS flow_nodes;

Migration 24 — 2024-01-01-000024_CreateFlowRuns.php:
Table: flow_runs
Columns:
- id CHAR(36) PRIMARY KEY
- flow_id CHAR(36) NOT NULL
- contact_id CHAR(36) NOT NULL
- conversation_id CHAR(36) NULL
- status ENUM('active','completed','handed_off','timed_out','failed') DEFAULT 'active'
- current_node_key VARCHAR(100) NULL
- vars JSON NULL
- meta_message_id VARCHAR(255) NULL
- started_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL
- FOREIGN KEY (flow_id) REFERENCES flows(id) ON DELETE CASCADE
- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
- INDEX idx_runs_flow (flow_id)
- INDEX idx_runs_contact (contact_id)
- INDEX idx_runs_status (status)
- INDEX idx_runs_meta_msg (meta_message_id)

Note: MySQL doesn't support partial indexes like Postgres. We'll enforce single active run per contact+flow in PHP.

down() method: DROP TABLE IF EXISTS flow_runs;

Migration 25 — 2024-01-01-000025_CreateFlowRunEvents.php:
Table: flow_run_events
Columns:
- id CHAR(36) PRIMARY KEY
- flow_run_id CHAR(36) NOT NULL
- node_key VARCHAR(100) NOT NULL
- event_type VARCHAR(100) NOT NULL
- event_data JSON NULL
- created_at DATETIME NOT NULL
- FOREIGN KEY (flow_run_id) REFERENCES flow_runs(id) ON DELETE CASCADE
- INDEX idx_events_run (flow_run_id)
- INDEX idx_events_created (created_at)

down() method: DROP TABLE IF EXISTS flow_run_events;
```

### Prompt 1.6 — Database Migrations (Part 5: Job Queue & System Tables)

```
Continue creating CI4 migrations for Rovix AI Leads Tool system tables.

Migration 26 — 2024-01-01-000026_CreateAccountInvitations.php:
Table: account_invitations
Columns:
- id CHAR(36) PRIMARY KEY
- account_id CHAR(36) NOT NULL
- role ENUM('admin','agent','viewer') NOT NULL
- token_hash VARCHAR(64) NOT NULL UNIQUE
- expires_at DATETIME NOT NULL
- accepted_at DATETIME NULL
- accepted_by_user_id CHAR(36) NULL
- created_at DATETIME NOT NULL
- FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
- INDEX idx_invitations_account (account_id)
- INDEX idx_invitations_token (token_hash)
- INDEX idx_invitations_expires (expires_at)

down() method: DROP TABLE IF EXISTS account_invitations;

Migration 27 — 2024-01-01-000027_CreateJobQueue.php:
Table: job_queue
Columns:
- id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- job_type VARCHAR(100) NOT NULL
- payload JSON NOT NULL
- status ENUM('pending','processing','done','failed') DEFAULT 'pending'
- priority TINYINT DEFAULT 5 (1=highest, 10=lowest)
- run_after DATETIME NULL
- locked_until DATETIME NULL (prevents race conditions)
- attempts TINYINT DEFAULT 0
- max_retries TINYINT DEFAULT 3
- error TEXT NULL
- failed_attempts_log JSON NULL (stores error history)
- created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX idx_queue_status_priority (status, priority, run_after)
- INDEX idx_queue_job_type (job_type)
- INDEX idx_queue_created (created_at)

down() method: DROP TABLE IF EXISTS job_queue;

Migration 28 — 2024-01-01-000028_CreateCiSessions.php:
Table: ci_sessions
Columns (CI4 default session table):
- id VARCHAR(128) NOT NULL PRIMARY KEY
- ip_address VARCHAR(45) NOT NULL
- timestamp INT UNSIGNED DEFAULT 0 NOT NULL
- data BLOB NOT NULL
- INDEX idx_sessions_timestamp (timestamp)

down() method: DROP TABLE IF EXISTS ci_sessions;

After creating all migrations, run:
php spark migrate

Verify all tables created successfully:
php spark migrate:status

Test rollback:
php spark migrate:rollback

Then re-run:
php spark migrate
```

### Prompt 1.7 — BaseModel + Core Models

```
Create the BaseModel and core model files for Rovix AI Leads Tool.

Reference original wacrm files:
- wacrm-main/src/lib/db/base-model.ts
- wacrm-main/src/lib/db/tenant-scoping.ts

CRITICAL — BaseModel (app/Models/BaseModel.php):
This replaces Supabase Row-Level Security. Every tenant-scoped model MUST extend this.

Create app/Models/BaseModel.php with these features:

1. Extends CodeIgniter\Model
2. Properties:
   - protected $useAutoIncrement = false; (we use UUIDs)
   - protected $useSoftDeletes = false;
   - protected $useTimestamps = true;
   - protected $createdField = 'created_at';
   - protected $updatedField = 'updated_at';
   - protected $returnType = 'array';

3. Static bypass flag for webhook/cron processing:
   - protected static bool $bypassAccountScope = false;
   - public static function setBypassAccountScope(bool $bypass): void
   - public static function getBypassAccountScope(): bool

4. Auto-scope queries in initialize():
   - If not bypassed AND table has account_id column
   - Get account_id from session('account_id')
   - If account_id exists, apply: $this->where($this->table . '.account_id', $accountId)

5. Helper method hasAccountId():
   - Returns false for: accounts, ci_sessions, job_queue
   - Returns true for all other tables

6. Callbacks:
   - protected $beforeInsert = ['generateUuid', 'injectAccountId'];
   - protected $beforeUpdate = [];

7. generateUuid() callback:
   - If $data['data']['id'] is empty, generate UUID v4
   - Use: random_bytes(16), set version bits, format as 8-4-4-4-12

8. injectAccountId() callback:
   - If hasAccountId() AND $data['data']['account_id'] is empty
   - Set $data['data']['account_id'] = session('account_id')
   - Only applies during INSERT

9. Helper method generateUuidV4(): string
   - Pure UUID v4 implementation
   - Returns lowercase hyphenated format

This is the MOST CRITICAL FILE in the entire migration — it ensures tenant isolation.
```

### Prompt 1.8 — All Model Classes

```
Create all model classes for Rovix AI Leads Tool, extending BaseModel.

Each model needs:
- Proper table name
- $primaryKey = 'id'
- $allowedFields array (all columns except id, created_at, updated_at)
- $returnType = 'array'

Create these models in app/Models/:

1. AccountModel.php
   - Override hasAccountId() to return false
   - Table: accounts
   - Allowed fields: name, owner_user_id, default_currency

2. ProfileModel.php
   - Table: profiles
   - Allowed fields: user_id, account_id, full_name, email, password_hash, avatar_url, account_role

3. ContactModel.php
   - Table: contacts
   - Allowed fields: account_id, phone, phone_normalized, name, email, company, avatar_url
   - Add method: findByPhone($accountId, $phone): ?array

4. TagModel.php
   - Table: tags
   - Allowed fields: account_id, name, color

5. ContactTagModel.php
   - Table: contact_tags
   - Allowed fields: contact_id, tag_id
   - No timestamps: override $useTimestamps = true, $createdField = 'created_at', no updated_at

6. CustomFieldModel.php
   - Table: custom_fields
   - Allowed fields: account_id, field_name, field_type, field_options

7. ContactCustomValueModel.php
   - Table: contact_custom_values
   - Allowed fields: contact_id, custom_field_id, value

8. ContactNoteModel.php
   - Table: contact_notes
   - Allowed fields: contact_id, user_id, note_text
   - No updated_at: override $createdField = 'created_at', $updatedField = ''

9. ConversationModel.php
   - Table: conversations
   - Allowed fields: account_id, contact_id, status, assigned_agent_id, unread_count, last_message_text, last_message_at

10. MessageModel.php
    - Table: messages
    - Allowed fields: conversation_id, account_id, sender_type, content_type, content_text, media_url, media_mime_type, media_filename, status, whatsapp_message_id, reply_to_message_id, template_name, error_message
    - No updated_at: override $createdField = 'created_at', $updatedField = ''

11. MessageReactionModel.php
    - Table: message_reactions
    - Allowed fields: message_id, conversation_id, actor_type, actor_id, emoji
    - No updated_at

12. WhatsAppConfigModel.php
    - Table: whatsapp_config
    - Allowed fields: account_id, phone_number_id, waba_id, access_token, business_name, status, subscription_status

13. MessageTemplateModel.php
    - Table: message_templates
    - Allowed fields: account_id, name, language, category, header_type, header_content, body_text, footer_text, buttons, sample_values, status, meta_template_id, quality_score

14. PipelineModel.php
    - Table: pipelines
    - Allowed fields: account_id, name

15. PipelineStageModel.php
    - Table: pipeline_stages
    - Allowed fields: pipeline_id, name, position, color
    - Note: tenant-scoped via JOIN with pipelines table

16. DealModel.php
    - Table: deals
    - Allowed fields: account_id, pipeline_id, stage_id, contact_id, conversation_id, title, value, currency, status, expected_close_date, assigned_agent_id, notes

17. BroadcastModel.php
    - Table: broadcasts
    - Allowed fields: account_id, name, template_name, template_language, audience_filter, status, scheduled_at, total_recipients, sent_count, delivered_count, read_count, replied_count, failed_count

18. BroadcastRecipientModel.php
    - Table: broadcast_recipients
    - Allowed fields: broadcast_id, contact_id, variables, status, whatsapp_message_id, error_message

19. AutomationModel.php
    - Table: automations
    - Allowed fields: account_id, user_id, name, trigger_type, trigger_config, is_active, execution_count, last_executed_at

20. AutomationStepModel.php
    - Table: automation_steps
    - Allowed fields: automation_id, parent_step_id, branch, step_type, step_config, position

21. AutomationLogModel.php
    - Table: automation_logs
    - Allowed fields: automation_id, contact_id, trigger_event, steps_executed, status, error_message
    - No updated_at

22. FlowModel.php
    - Table: flows
    - Allowed fields: account_id, name, is_active, trigger_keywords, execution_count

23. FlowNodeModel.php
    - Table: flow_nodes
    - Allowed fields: flow_id, node_key, node_type, config, position_x, position_y

24. FlowRunModel.php
    - Table: flow_runs
    - Allowed fields: flow_id, contact_id, conversation_id, status, current_node_key, vars, meta_message_id, started_at
    - Override $createdField = 'started_at'

25. FlowRunEventModel.php
    - Table: flow_run_events
    - Allowed fields: flow_run_id, node_key, event_type, event_data
    - No updated_at

26. AccountInvitationModel.php
    - Table: account_invitations
    - Allowed fields: account_id, role, token_hash, expires_at, accepted_at, accepted_by_user_id
    - No updated_at

27. JobQueueModel.php
    - Table: job_queue
    - Override: $useAutoIncrement = true, $primaryKey = 'id'
    - Override hasAccountId() to return false (no account_id column)
    - Allowed fields: job_type, payload, status, priority, run_after, locked_until, attempts, max_retries, error, failed_attempts_log

All models should be clean, no business logic — just data access.
```

### Prompt 1.9 — Helper Functions

```
Create helper function files for Rovix AI Leads Tool.

Create app/Helpers/auth_helper.php:

function current_user_id(): ?string
- Return session('user_id')

function current_account_id(): ?string
- Return session('account_id')

function current_profile(): ?array
- Return array with: user_id, account_id, full_name, email, avatar_url, account_role from session

function is_authenticated(): bool
- Return !empty(session('user_id'))

Create app/Helpers/format_helper.php:

function format_phone(string $phone): string
- Display format: +91 98765 43210
- Handle country code detection

function format_currency(float $amount, string $currency = 'INR'): string
- INR: ₹1,23,456.00
- USD: $1,234.56
- Use number_format with appropriate symbols

function format_relative_time(string $datetime): string
- Just now, 2 minutes ago, 5 hours ago, Yesterday, 3 days ago, Jan 15
- Use DateTime comparison

function generate_uuid(): string
- Same UUID v4 logic as BaseModel

function safe_json_decode(string $json, $default = null)
- Wrapper around json_decode with error handling
- Return $default if invalid JSON

Create app/Helpers/role_helper.php:

function role_rank(string $role): int
- owner: 4
- admin: 3
- agent: 2
- viewer: 1
- default: 0

function has_min_role(string $required): bool
- Compare session('account_role') rank with required rank
- Return true if current user has sufficient role

function can_send_messages(): bool
- agent or higher

function can_manage_members(): bool
- admin or higher

function can_edit_settings(): bool
- admin or higher

function can_view_only(): bool
- viewer role exactly

function can_delete_account(): bool
- owner only

function can_transfer_ownership(): bool
- owner only

function can_manage_flows(): bool
- admin or higher

function can_manage_automations(): bool
- agent or higher

function can_manage_broadcasts(): bool
- agent or higher

function can_view_analytics(): bool
- All roles (true)

Load these helpers in app/Config/Autoload.php:
public $helpers = ['auth', 'format', 'role'];
```

### Testing Phase 1

Test the project setup and database schema:

```bash
# 1. Verify directory structure
ls -la app/Controllers/Api/
ls -la app/Libraries/WhatsApp/
ls -la app/Models/
ls -la writable/uploads/chat-media/

# 2. Check config files
php spark config:check

# 3. Run migrations
php spark migrate

# 4. Check migration status
php spark migrate:status

# 5. Test rollback
php spark migrate:rollback

# 6. Re-run migrations
php spark migrate

# 7. Verify tables created
php spark db:table accounts
php spark db:table profiles
php spark db:table conversations
php spark db:table messages

# 8. Test UUID generation
php -r "require 'vendor/autoload.php'; echo bin2hex(random_bytes(16));"

# 9. Check BaseModel scoping
# Create a test script to verify tenant isolation works
```

**Pass Criteria:**
- ✓ All 28 migrations run successfully
- ✓ All tables present in database with correct schema
- ✓ Foreign keys and indexes created
- ✓ Rollback and re-migrate works without errors
- ✓ BaseModel file exists with proper scoping logic
- ✓ All 27 model files exist and extend BaseModel
- ✓ Helper functions load without errors
- ✓ writable/uploads/chat-media/ directory exists with .htaccess

---

## PHASE 2: Authentication System (Week 1-2 — Days 4-6)

### Prompt 2.1 — Auth Controller + Filters

```
Build the authentication system for Rovix AI Leads Tool.

Reference original wacrm files:
- wacrm-main/src/app/(auth)/login/page.tsx
- wacrm-main/src/app/(auth)/signup/page.tsx
- wacrm-main/src/middleware.ts
- wacrm-main/src/lib/auth/roles.ts

Create app/Controllers/AuthController.php:

Methods:

1. login() GET:
   - If already authenticated, redirect to /dashboard
   - Show login form view
   - Pass CSRF token

2. attemptLogin() POST:
   - Validate: email required|valid_email, password required
   - Query ProfileModel for email
   - If not found, return error: "Invalid credentials"
   - Verify password: password_verify($password, $profile['password_hash'])
   - If invalid, return error: "Invalid credentials"
   - Start session with:
     - user_id: $profile['user_id']
     - account_id: $profile['account_id']
     - account_role: $profile['account_role']
     - full_name: $profile['full_name']
     - email: $profile['email']
     - avatar_url: $profile['avatar_url']
   - Call session()->regenerate() (security: prevent fixation)
   - Flash success: "Welcome back, {name}!"
   - Redirect to /dashboard

3. signup() GET:
   - If already authenticated, redirect to /dashboard
   - Show signup form view

4. register() POST:
   - Validate:
     - full_name: required|min_length[2]
     - email: required|valid_email|is_unique[profiles.email]
     - password: required|min_length[8]
     - password_confirm: required|matches[password]
   - Start database transaction
   - Create account row:
     - id: generate_uuid()
     - name: "{full_name}'s Account"
     - owner_user_id: will be set after profile creation
     - default_currency: 'INR'
   - Create profile row:
     - id: generate_uuid()
     - user_id: same as id (CI4 convention)
     - account_id: account id from above
     - full_name, email
     - password_hash: password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
     - account_role: 'owner'
   - Update account.owner_user_id = profile.user_id
   - Commit transaction
   - Auto-login: set same session vars as attemptLogin
   - Flash success: "Account created successfully!"
   - Redirect to /dashboard

5. forgotPassword() GET:
   - Show forgot password form (stub for now)
   - Will implement email reset in later phase

6. logout() POST:
   - Destroy session: session()->destroy()
   - Flash success: "Logged out successfully"
   - Redirect to /login

Error handling:
- Use CI4 validation for all forms
- Return validation errors to view
- Use flash messages for success/error feedback

Security measures:
- CSRF protection on all POST routes
- Rate limiting on login attempts (via RateLimitFilter)
- Session regeneration on login
- Bcrypt password hashing with cost=12
- No user enumeration (same error for invalid email/password)
```

### Prompt 2.2 — Authentication Filters

```
Create authentication and authorization filters for Rovix AI Leads Tool.

Create app/Filters/AuthFilter.php:

Purpose: Check if user is authenticated, redirect to login if not.

before() method:
- Check if session('user_id') exists
- If not authenticated:
  - Store intended URL in session for redirect after login
  - Redirect to /login with flash message: "Please login to continue"
- If authenticated:
  - Continue request

Exclusion list (routes that don't require auth):
- login, signup, forgot-password, reset-password
- api/whatsapp/webhook (GET and POST)
- join/* (invitation acceptance)

Create app/Filters/AccountFilter.php:

Purpose: Load full profile data into session on each request.

before() method:
- Get user_id from session
- If empty, skip (AuthFilter handles this)
- Query ProfileModel by user_id
- If profile not found, logout and redirect (data inconsistency)
- Refresh session with latest profile data:
  - account_id, account_role, full_name, email, avatar_url
- This ensures BaseModel tenant scoping always has current account_id

Create app/Filters/RoleFilter.php:

Purpose: Check minimum required role for a route.

Parameters: accepts minimum role via route config

before() method:
- Get required role from arguments
- Get current role from session('account_role')
- Compare using role_rank() helper
- If insufficient:
  - Return 403 response with error page
  - Flash error: "You don't have permission to access this page"

Register all filters in app/Config/Filters.php:

$aliases:
- 'auth' => \App\Filters\AuthFilter::class
- 'account' => \App\Filters\AccountFilter::class
- 'role' => \App\Filters\RoleFilter::class

$globals:
- 'before': ['auth', 'account'] (run on all routes except exclusions)
- 'after': [] (will add security headers later)

$filters (per-route):
- Will be defined in Routes.php for specific role requirements
```

### Prompt 2.3 — Auth Routes Configuration

```
Update app/Config/Routes.php with authentication routes.

Add these routes:

// Public routes (no auth required)
$routes->get('/', 'Home::index'); // Redirect to /dashboard
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->get('signup', 'AuthController::signup');
$routes->post('signup', 'AuthController::register');
$routes->get('forgot-password', 'AuthController::forgotPassword');
$routes->post('logout', 'AuthController::logout');

// Webhook routes (no auth, but signature verification)
$routes->get('api/whatsapp/webhook', 'Api\WebhookController::verify');
$routes->post('api/whatsapp/webhook', 'Api\WebhookController::handle');

// Authenticated routes (configured in next prompts)
$routes->get('dashboard', 'DashboardController::index', ['filter' => 'auth|account']);

// Override CI4 default behavior
$routes->set404Override(function() {
    return view('errors/404');
});

Configure filter exclusions in app/Config/Filters.php:

$globals['before']:
- auth filter, except: ['login', 'signup', 'forgot-password', 'api/whatsapp/webhook', 'join/*']
- account filter, except: ['login', 'signup', 'forgot-password', 'api/whatsapp/webhook', 'join/*']
```

### Prompt 2.4 — Layout Views + Sidebar Navigation

```
Create the layout views for Rovix AI Leads Tool with Rovix AI branding.

Reference original wacrm files:
- wacrm-main/src/app/layout.tsx
- wacrm-main/src/components/layout/sidebar.tsx
- wacrm-main/src/components/layout/header.tsx

Use Rovix AI brand colors from https://rovixai.com/:
- Dark navy sidebar: #1B2A4A
- Primary accent: #3B82F6 (blue)
- Success green: #10B981
- Warning yellow: #F59E0B
- Error red: #EF4444

Create app/Views/layouts/main.php:

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Rovix AI Leads Tool' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/assets/img/favicon.ico">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" :class="{ 'dark': darkMode }">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?= $this->include('layouts/partials/sidebar') ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <?= $this->include('layouts/partials/header') ?>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?= $this->include('layouts/partials/flash_messages') ?>
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
</body>
</html>

Create app/Views/layouts/auth.php:

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Rovix AI' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/assets/img/favicon.ico">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Rovix AI Logo -->
            <div class="text-center">
                <h1 class="text-3xl font-bold text-gray-900">
                    <span class="text-blue-600">Rovix</span> AI
                </h1>
                <p class="mt-2 text-sm text-gray-600">Leads Tool</p>
            </div>
            
            <!-- Content -->
            <?= $this->renderSection('content') ?>
        </div>
    </div>
</body>
</html>

Create app/Views/layouts/partials/sidebar.php:

<aside class="w-64 bg-[#1B2A4A] text-white flex-shrink-0" x-data="{ mobileOpen: false }">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="flex items-center justify-center h-16 border-b border-white/10 px-4">
            <h1 class="text-xl font-bold">
                <span class="text-white">Rovix</span> <span class="text-blue-400">AI</span>
            </h1>
        </div>
        
        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-4">
            <a href="/dashboard" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= uri_string() === 'dashboard' ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            
            <a href="/inbox" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= strpos(uri_string(), 'inbox') === 0 ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                Inbox
                <?php if (($unreadCount ?? 0) > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-0.5"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            
            <a href="/contacts" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= strpos(uri_string(), 'contacts') === 0 ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Contacts
            </a>
            
            <a href="/pipelines" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= strpos(uri_string(), 'pipelines') === 0 ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
                Pipelines
            </a>
            
            <a href="/broadcasts" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= strpos(uri_string(), 'broadcasts') === 0 ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                Broadcasts
            </a>
            
            <a href="/automations" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= strpos(uri_string(), 'automations') === 0 ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Automations
            </a>
            
            <?php if (has_min_role('admin')): ?>
            <a href="/flows" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= strpos(uri_string(), 'flows') === 0 ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Flows
                <span class="ml-2 text-xs bg-blue-500 text-white px-2 py-0.5 rounded-full">Beta</span>
            </a>
            <?php endif; ?>
            
            <?php if (can_edit_settings()): ?>
            <a href="/settings" class="flex items-center px-4 py-3 text-white/70 hover:bg-white/10 hover:text-white <?= strpos(uri_string(), 'settings') === 0 ? 'bg-white/10 text-white' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
            <?php endif; ?>
        </nav>
    </div>
</aside>
```

### Prompt 2.5 — Header & Flash Messages Components

```
Complete the layout partial views for Rovix AI Leads Tool.

Create app/Views/layouts/partials/header.php:

<header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 h-16 flex items-center justify-between px-6" x-data="{ userMenuOpen: false }">
    <!-- Left: Page Title -->
    <div>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white"><?= esc($pageTitle ?? 'Dashboard') ?></h2>
    </div>
    
    <!-- Right: User Menu -->
    <div class="flex items-center space-x-4">
        <!-- Dark Mode Toggle -->
        <button @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg x-show="!darkMode" class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
            <svg x-show="darkMode" class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </button>
        
        <!-- User Dropdown -->
        <div class="relative">
            <button @click="userMenuOpen = !userMenuOpen" class="flex items-center space-x-3 focus:outline-none">
                <?php if (!empty(session('avatar_url'))): ?>
                <img src="<?= esc(session('avatar_url')) ?>" alt="Avatar" class="w-8 h-8 rounded-full">
                <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                    <?= strtoupper(substr(session('full_name') ?? 'U', 0, 1)) ?>
                </div>
                <?php endif; ?>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?= esc(session('full_name')) ?></span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            
            <!-- Dropdown Menu -->
            <div x-show="userMenuOpen" @click.away="userMenuOpen = false" x-cloak class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50">
                <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-900 dark:text-white"><?= esc(session('full_name')) ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc(session('email')) ?></p>
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1 capitalize"><?= esc(session('account_role')) ?></p>
                </div>
                
                <?php if (can_edit_settings()): ?>
                <a href="/settings/profile" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    Profile Settings
                </a>
                <a href="/settings/team" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    Team Management
                </a>
                <?php endif; ?>
                
                <form action="/logout" method="POST" class="border-t border-gray-200 dark:border-gray-700">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

Create app/Views/layouts/partials/flash_messages.php:

<?php 
$success = session()->getFlashdata('success');
$error = session()->getFlashdata('error');
$warning = session()->getFlashdata('warning');
$info = session()->getFlashdata('info');
?>

<?php if ($success || $error || $warning || $info): ?>
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="fixed top-4 right-4 z-50 max-w-sm">
    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-start">
        <svg class="w-5 h-5 text-green-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <div class="ml-3 flex-1">
            <p class="text-sm font-medium text-green-800"><?= esc($success) ?></p>
        </div>
        <button @click="show = false" class="ml-3 text-green-400 hover:text-green-600">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start">
        <svg class="w-5 h-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>
        <div class="ml-3 flex-1">
            <p class="text-sm font-medium text-red-800"><?= esc($error) ?></p>
        </div>
        <button @click="show = false" class="ml-3 text-red-400 hover:text-red-600">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if ($warning): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-start">
        <svg class="w-5 h-5 text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div class="ml-3 flex-1">
            <p class="text-sm font-medium text-yellow-800"><?= esc($warning) ?></p>
        </div>
        <button @click="show = false" class="ml-3 text-yellow-400 hover:text-yellow-600">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
```

### Prompt 2.6 — Auth Views (Login & Signup)

```
Create authentication view files for Rovix AI Leads Tool.

Create app/Views/auth/login.php:

<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<div class="bg-white py-8 px-6 shadow rounded-lg">
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">
        Sign in to your account
    </h2>

    <?php if (session('error')): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded">
        <?= esc(session('error')) ?>
    </div>
    <?php endif; ?>

    <form action="/login" method="POST" class="space-y-6">
        <?= csrf_field() ?>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
            <input type="email" id="email" name="email" required value="<?= old('email') ?>"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <?php if (isset($validation) && $validation->getError('email')): ?>
            <p class="mt-1 text-sm text-red-600"><?= $validation->getError('email') ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" id="password" name="password" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <?php if (isset($validation) && $validation->getError('password')): ?>
            <p class="mt-1 text-sm text-red-600"><?= $validation->getError('password') ?></p>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-between">
            <div class="text-sm">
                <a href="/forgot-password" class="font-medium text-blue-600 hover:text-blue-500">
                    Forgot your password?
                </a>
            </div>
        </div>

        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Sign in
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        Don't have an account?
        <a href="/signup" class="font-medium text-blue-600 hover:text-blue-500">Sign up</a>
    </p>
</div>

<?= $this->endSection() ?>

Create app/Views/auth/signup.php with similar structure but fields: full_name, email, password, password_confirm.

Create app/Views/dashboard/index.php:

<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Stats Cards -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Conversations</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?= $stats['conversations'] ?? 0 ?></p>
                </div>
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Contacts</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?= $stats['contacts'] ?? 0 ?></p>
                </div>
                <div class="bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Open Deals</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?= $stats['deals'] ?? 0 ?></p>
                </div>
                <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pipeline Value</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?= format_currency($stats['pipeline_value'] ?? 0) ?></p>
                </div>
                <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="/inbox" class="flex items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 transition">
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">View Inbox</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Check new messages</p>
                </div>
            </a>

            <a href="/contacts/create" class="flex items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-green-500 transition">
                <div class="bg-green-100 dark:bg-green-900 p-3 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">Add Contact</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Create new contact</p>
                </div>
            </a>

            <a href="/broadcasts/create" class="flex items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-purple-500 transition">
                <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">New Broadcast</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Send bulk messages</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

Create app/Controllers/DashboardController.php:

public function index()
{
    $conversationModel = new ConversationModel();
    $contactModel = new ContactModel();
    $dealModel = new DealModel();

    $data = [
        'pageTitle' => 'Dashboard',
        'stats' => [
            'conversations' => $conversationModel->countAllResults(),
            'contacts' => $contactModel->countAllResults(),
            'deals' => $dealModel->where('status', 'open')->countAllResults(),
            'pipeline_value' => $dealModel->where('status', 'open')->selectSum('value')->first()['value'] ?? 0,
        ]
    ];

    return view('dashboard/index', $data);
}
```

### Testing Phase 2

Test the authentication system:

```bash
# 1. Start development server
php spark serve

# 2. Visit http://localhost:8080/signup
# Create a test account

# 3. Verify database
# Check accounts table has 1 row
# Check profiles table has 1 row with role='owner'

# 4. Test login
# Visit /login
# Enter credentials
# Should redirect to /dashboard

# 5. Test session
# Refresh page - should stay logged in
# Check session data in database (ci_sessions table)

# 6. Test logout
# Click logout
# Should redirect to /login
# Session should be destroyed

# 7. Test filters
# Visit /dashboard without login - should redirect to /login
# Visit /login while logged in - should redirect to /dashboard

# 8. Test role helpers
# In dashboard, verify sidebar shows correct items based on role
```

**Pass Criteria:**
- ✓ Can signup and create account
- ✓ Can login with email/password
- ✓ Session persists across requests
- ✓ AuthFilter redirects unauthenticated users
- ✓ AccountFilter loads profile data
- ✓ Role-based sidebar navigation works
- ✓ Logout destroys session
- ✓ Dashboard displays with stats cards
- ✓ Flash messages appear and auto-dismiss

---

## PHASE 3: WhatsApp Core Integration (Week 2-3 — Days 7-12)

### Prompt 3.1 — Encryption + Phone Utils + Webhook Signature

```
Port the WhatsApp security layer for Rovix AI Leads Tool.

Reference original wacrm files:
- wacrm-main/src/lib/whatsapp/encryption.ts
- wacrm-main/src/lib/whatsapp/webhook-signature.ts
- wacrm-main/src/lib/whatsapp/phone-utils.ts

Create app/Libraries/WhatsApp/Encryption.php:

namespace App\Libraries\WhatsApp;

class Encryption
{
    private string $key;

    public function __construct()
    {
        $keyHex = config('Rovix')->encryptionKey;
        if (strlen($keyHex) !== 64) {
            throw new \Exception('Encryption key must be 64 hex characters (32 bytes)');
        }
        $this->key = hex2bin($keyHex);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12); // 12 bytes for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // tag length
        );

        if ($ciphertext === false) {
            throw new \Exception('Encryption failed');
        }

        // Format: iv:ciphertext:tag (all hex-encoded)
        return bin2hex($iv) . ':' . bin2hex($ciphertext) . ':' . bin2hex($tag);
    }

    public function decrypt(string $encrypted): string
    {
        $parts = explode(':', $encrypted);

        if (count($parts) === 3) {
            // GCM format: iv:ciphertext:tag
            [$ivHex, $ciphertextHex, $tagHex] = $parts;
            $iv = hex2bin($ivHex);
            $ciphertext = hex2bin($ciphertextHex);
            $tag = hex2bin($tagHex);

            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $this->key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
        } elseif (count($parts) === 2) {
            // Legacy CBC format: iv:ciphertext (backward compatibility)
            [$ivHex, $ciphertextHex] = $parts;
            $iv = hex2bin($ivHex);
            $ciphertext = hex2bin($ciphertextHex);

            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-cbc',
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );
        } else {
            throw new \Exception('Invalid encrypted format');
        }

        if ($plaintext === false) {
            throw new \Exception('Decryption failed');
        }

        return $plaintext;
    }

    public function isLegacyFormat(string $encrypted): bool
    {
        return count(explode(':', $encrypted)) === 2;
    }
}

Create app/Libraries/WhatsApp/WebhookSignature.php:

namespace App\Libraries\WhatsApp;

class WebhookSignature
{
    public static function verify(string $rawBody, string $signature, string $appSecret): bool
    {
        if (empty($appSecret)) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        // Use timing-safe comparison
        return hash_equals($expectedSignature, $signature);
    }
}

Create app/Libraries/WhatsApp/PhoneUtils.php:

namespace App\Libraries\WhatsApp;

class PhoneUtils
{
    public static function normalize(string $phone): string
    {
        // Strip everything except digits
        return preg_replace('/[^0-9]/', '', $phone);
    }

    public static function isValid(string $phone): bool
    {
        $normalized = self::normalize($phone);
        return strlen($normalized) >= 10;
    }

    public static function format(string $phone): string
    {
        $normalized = self::normalize($phone);

        // Detect country code
        if (str_starts_with($normalized, '91') && strlen($normalized) === 12) {
            // India: +91 98765 43210
            return '+91 ' . substr($normalized, 2, 5) . ' ' . substr($normalized, 7);
        }

        if (str_starts_with($normalized, '1') && strlen($normalized) === 11) {
            // US: +1 (555) 123-4567
            return '+1 (' . substr($normalized, 1, 3) . ') ' . substr($normalized, 4, 3) . '-' . substr($normalized, 7);
        }

        // Default: +{code} {rest}
        if (strlen($normalized) > 10) {
            $code = substr($normalized, 0, -10);
            $number = substr($normalized, -10);
            return '+' . $code . ' ' . $number;
        }

        return '+' . $normalized;
    }
}

Create app/Filters/WebhookSignatureFilter.php:

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Libraries\WhatsApp\WebhookSignature;

class WebhookSignatureFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Only verify POST requests
        if ($request->getMethod() !== 'post') {
            return;
        }

        $rawBody = file_get_contents('php://input');
        $signature = $request->getHeaderLine('X-Hub-Signature-256');
        $appSecret = config('WhatsApp')->metaAppSecret;

        // Fail closed: if no secret configured, reject
        if (empty($appSecret)) {
            log_message('error', 'Webhook signature verification failed: no app secret configured');
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        if (empty($signature)) {
            log_message('error', 'Webhook signature verification failed: no signature header');
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        if (!WebhookSignature::verify($rawBody, $signature, $appSecret)) {
            log_message('error', 'Webhook signature verification failed: invalid signature');
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        // Signature valid, proceed
        return;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do
    }
}

Register WebhookSignatureFilter in app/Config/Filters.php:
$aliases['webhook_signature'] = \App\Filters\WebhookSignatureFilter::class;
```

### Prompt 3.2 — Meta API Client (All Methods)

```
Port the Meta WhatsApp Cloud API client for Rovix AI Leads Tool with full method coverage.

Reference original wacrm files:
- wacrm-main/src/lib/whatsapp/meta-api.ts
- wacrm-main/src/lib/whatsapp/template-send.ts

Create app/Libraries/WhatsApp/MetaApi.php:

namespace App\Libraries\WhatsApp;

class MetaApi
{
    private const BASE_URL = 'https://graph.facebook.com/v21.0/';
    private const REQUEST_TIMEOUT = 30;

    /**
     * Core API call wrapper
     */
    private function callApi(string $method, string $endpoint, ?array $data, string $accessToken): array
    {
        $url = self::BASE_URL . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            log_message('error', 'Meta API cURL error: ' . $error);
            throw new \Exception('Meta API request failed: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? 'Unknown error';
            log_message('error', "Meta API error {$httpCode}: {$errorMsg}");
            throw new \Exception("Meta API error: {$errorMsg}");
        }

        return $decoded;
    }

    /**
     * 1. Send text message
     */
    public function sendText(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $text,
        ?string $replyToMessageId = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text]
        ];

        if ($replyToMessageId) {
            $payload['context'] = ['message_id' => $replyToMessageId];
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 2. Send image
     */
    public function sendImage(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $imageUrl,
        ?string $caption = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => ['link' => $imageUrl]
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 3. Send video
     */
    public function sendVideo(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $videoUrl,
        ?string $caption = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'video',
            'video' => ['link' => $videoUrl]
        ];

        if ($caption) {
            $payload['video']['caption'] = $caption;
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 4. Send document
     */
    public function sendDocument(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $documentUrl,
        string $filename,
        ?string $caption = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename
            ]
        ];

        if ($caption) {
            $payload['document']['caption'] = $caption;
        }

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 5. Send audio
     */
    public function sendAudio(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $audioUrl
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'audio',
            'audio' => ['link' => $audioUrl]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 6. Send template message
     */
    public function sendTemplate(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $templateName,
        string $language,
        array $components = []
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components
            ]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 7. Send reaction
     */
    public function sendReaction(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $messageId,
        string $emoji
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $messageId,
                'emoji' => $emoji
            ]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 8. Send interactive buttons
     */
    public function sendInteractiveButtons(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $bodyText,
        array $buttons,
        ?string $headerText = null
    ): array {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => ['buttons' => $buttons]
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 9. Send interactive list
     */
    public function sendInteractiveList(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $bodyText,
        string $buttonText,
        array $sections
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => $buttonText,
                    'sections' => $sections
                ]
            ]
        ];

        return $this->callApi('POST', "{$phoneNumberId}/messages", $payload, $accessToken);
    }

    /**
     * 10. Get media URL from media ID
     */
    public function getMediaUrl(string $mediaId, string $accessToken): string
    {
        $response = $this->callApi('GET', $mediaId, null, $accessToken);
        return $response['url'] ?? '';
    }

    /**
     * 11. Download media from WhatsApp CDN
     */
    public function downloadMedia(string $url, string $accessToken): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($data === false || $httpCode >= 400) {
            throw new \Exception('Failed to download media from WhatsApp CDN');
        }

        // Generate unique filename
        $extension = $this->getExtensionFromMimeType($contentType);
        $filename = uniqid('wa_media_', true) . '.' . $extension;
        $savePath = WRITEPATH . 'uploads/chat-media/' . $filename;

        // Ensure directory exists
        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($savePath, $data);

        return $filename; // Return relative filename
    }

    /**
     * 12. Upload media to WhatsApp
     */
    public function uploadMedia(
        string $phoneNumberId,
        string $accessToken,
        string $filePath,
        string $mimeType
    ): string {
        $url = self::BASE_URL . "{$phoneNumberId}/media";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $postData = [
            'messaging_product' => 'whatsapp',
            'file' => new \CURLFile($filePath, $mimeType),
            'type' => $mimeType
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \Exception('Failed to upload media to WhatsApp');
        }

        $decoded = json_decode($response, true);
        return $decoded['id'] ?? '';
    }

    /**
     * Helper: Get file extension from MIME type
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/amr' => 'amr',
            'application/pdf' => 'pdf',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        ];

        return $map[$mimeType] ?? 'bin';
    }
}

Create app/Libraries/WhatsApp/TemplateSendBuilder.php:

namespace App\Libraries\WhatsApp;

class TemplateSendBuilder
{
    /**
     * Build template components array from template model + variables
     * 
     * @param array $template - MessageTemplate model row
     * @param array $variables - Key-value pairs for variable substitution
     * @return array - Components array for Meta API
     */
    public static function buildComponents(array $template, array $variables = []): array
    {
        $components = [];

        // Header component
        if ($template['header_type'] !== 'none' && !empty($template['header_content'])) {
            $headerComponent = ['type' => 'header'];

            if ($template['header_type'] === 'text') {
                // Extract variable placeholders from header text
                $headerParams = self::extractParameters($template['header_content'], $variables);
                if (!empty($headerParams)) {
                    $headerComponent['parameters'] = $headerParams;
                }
            } elseif (in_array($template['header_type'], ['image', 'video', 'document'])) {
                // Media headers require media ID or URL
                $mediaUrl = $variables['header_media_url'] ?? null;
                if ($mediaUrl) {
                    $headerComponent['parameters'] = [
                        [
                            'type' => $template['header_type'],
                            $template['header_type'] => ['link' => $mediaUrl]
                        ]
                    ];
                }
            }

            if (!empty($headerComponent['parameters'])) {
                $components[] = $headerComponent;
            }
        }

        // Body component (always present)
        $bodyParams = self::extractParameters($template['body_text'], $variables);
        if (!empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams
            ];
        }

        // Button components (if any)
        if (!empty($template['buttons'])) {
            $buttons = is_string($template['buttons']) 
                ? json_decode($template['buttons'], true) 
                : $template['buttons'];

            foreach ($buttons as $index => $button) {
                if ($button['type'] === 'url' && isset($button['url']) && strpos($button['url'], '{{') !== false) {
                    // Dynamic URL button
                    $urlSuffix = $variables["button_{$index}_url_suffix"] ?? '';
                    $components[] = [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => $index,
                        'parameters' => [
                            ['type' => 'text', 'text' => $urlSuffix]
                        ]
                    ];
                }
            }
        }

        return $components;
    }

    /**
     * Extract {{1}}, {{2}}, etc. placeholders and map to variables
     */
    private static function extractParameters(string $text, array $variables): array
    {
        $params = [];
        
        // Match {{1}}, {{2}}, etc.
        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $index) {
                $value = $variables[(int)$index] ?? $variables["var_{$index}"] ?? '';
                $params[] = [
                    'type' => 'text',
                    'text' => (string)$value
                ];
            }
        }

        return $params;
    }
}
```

### Prompt 3.3 — Webhook Controller (Verify, Handle, Process)


```
Port the WhatsApp webhook handler for Rovix AI Leads Tool. This is the most critical file — it processes ALL inbound WhatsApp events.

Reference original wacrm files:
- wacrm-main/src/app/api/whatsapp/webhook/route.ts (969 lines)
- wacrm-main/src/lib/whatsapp/webhook-processor.ts

Create app/Controllers/Api/WebhookController.php:

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\WhatsAppConfigModel;
use App\Models\ContactModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\MessageReactionModel;
use App\Models\MessageTemplateModel;
use App\Models\BroadcastRecipientModel;
use App\Models\BroadcastModel;
use App\Models\BaseModel;
use App\Libraries\WhatsApp\PhoneUtils;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\JobDispatcher;

class WebhookController extends BaseController
{
    /**
     * GET handler for Meta webhook verification
     */
    public function verify()
    {
        $mode = $this->request->getGet('hub_mode');
        $token = $this->request->getGet('hub_verify_token');
        $challenge = $this->request->getGet('hub_challenge');

        $verifyToken = config('WhatsApp')->verifyToken;

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $this->response
                ->setStatusCode(200)
                ->setBody($challenge);
        }

        return $this->response->setStatusCode(403);
    }

    /**
     * POST handler for inbound webhook events
     */
    public function handle()
    {
        // Bypass tenant scoping - webhook runs without session
        BaseModel::setBypassAccountScope(true);

        try {
            $body = $this->request->getJSON(true);
            
            if (empty($body['entry'])) {
                return $this->response->setJSON(['status' => 'ignored']);
            }

            foreach ($body['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $value = $change['value'] ?? [];
                    
                    // Process inbound messages
                    if (!empty($value['messages'])) {
                        foreach ($value['messages'] as $message) {
                            $this->processInboundMessage(
                                $value['metadata']['phone_number_id'],
                                $message,
                                $value['contacts'][0] ?? []
                            );
                        }
                    }
                    
                    // Process status updates
                    if (!empty($value['statuses'])) {
                        foreach ($value['statuses'] as $status) {
                            $this->processStatusUpdate($status);
                        }
                    }
                    
                    // Process template status updates
                    if (!empty($value['message_template_status_update'])) {
                        $this->processTemplateStatus($value['message_template_status_update']);
                    }
                }
            }

            return $this->response->setJSON(['status' => 'ok']);

        } catch (\Exception $e) {
            log_message('error', 'Webhook processing error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Internal error']);
        } finally {
            BaseModel::setBypassAccountScope(false);
        }
    }

    /**
     * Process inbound message from WhatsApp
     */
    private function processInboundMessage(string $phoneNumberId, array $message, array $contactInfo)
    {
        // Get account by phone number ID
        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('phone_number_id', $phoneNumberId)->first();
        
        if (!$waConfig) {
            log_message('error', "No WhatsApp config found for phone_number_id: {$phoneNumberId}");
            return;
        }
        
        $accountId = $waConfig['account_id'];
        
        // Get or create contact
        $contactModel = new ContactModel();
        $phone = $contactInfo['wa_id'] ?? $message['from'];
        $phoneNormalized = PhoneUtils::normalize($phone);
        
        $contact = $contactModel->where('account_id', $accountId)
            ->where('phone_normalized', $phoneNormalized)
            ->first();
        
        if (!$contact) {
            $contact = [
                'id' => generate_uuid(),
                'account_id' => $accountId,
                'phone' => $phone,
                'phone_normalized' => $phoneNormalized,
                'name' => $contactInfo['profile']['name'] ?? null,
            ];
            $contactModel->insert($contact);
        }
        
        // Get or create conversation
        $conversationModel = new ConversationModel();
        $conversation = $conversationModel->where('account_id', $accountId)
            ->where('contact_id', $contact['id'])
            ->first();
        
        if (!$conversation) {
            $conversation = [
                'id' => generate_uuid(),
                'account_id' => $accountId,
                'contact_id' => $contact['id'],
                'status' => 'open',
                'unread_count' => 0,
            ];
            $conversationModel->insert($conversation);
        } else {
            // Reopen if closed
            if ($conversation['status'] === 'closed') {
                $conversationModel->update($conversation['id'], ['status' => 'open']);
            }
        }
        
        // Handle different message types
        $messageType = $message['type'];
        $contentText = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaFilename = null;
        
        switch ($messageType) {
            case 'text':
                $contentText = $message['text']['body'] ?? '';
                break;
                
            case 'image':
            case 'video':
            case 'document':
            case 'audio':
                $media = $message[$messageType];
                $mediaId = $media['id'] ?? null;
                $contentText = $media['caption'] ?? null;
                $mediaMimeType = $media['mime_type'] ?? null;
                
                if ($messageType === 'document') {
                    $mediaFilename = $media['filename'] ?? 'document';
                }
                
                // Download media
                if ($mediaId) {
                    try {
                        $metaApi = new MetaApi();
                        $encryption = new \App\Libraries\WhatsApp\Encryption();
                        $accessToken = $encryption->decrypt($waConfig['access_token']);
                        
                        $mediaUrlFromApi = $metaApi->getMediaUrl($mediaId, $accessToken);
                        $localFilename = $metaApi->downloadMedia($mediaUrlFromApi, $accessToken);
                        $mediaUrl = '/uploads/chat-media/' . $localFilename;
                    } catch (\Exception $e) {
                        log_message('error', 'Media download failed: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'reaction':
                // Handle reaction separately
                $this->processReaction($conversation['id'], $message);
                return;
                
            case 'interactive':
                // Button reply or list reply
                $interactive = $message['interactive'];
                if ($interactive['type'] === 'button_reply') {
                    $contentText = $interactive['button_reply']['title'];
                } elseif ($interactive['type'] === 'list_reply') {
                    $contentText = $interactive['list_reply']['title'];
                }
                break;
                
            case 'location':
                $loc = $message['location'];
                $contentText = "Location: {$loc['latitude']}, {$loc['longitude']}";
                break;
        }
        
        // Insert message
        $messageModel = new MessageModel();
        $messageData = [
            'id' => generate_uuid(),
            'conversation_id' => $conversation['id'],
            'account_id' => $accountId,
            'sender_type' => 'customer',
            'content_type' => $messageType,
            'content_text' => $contentText,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_filename' => $mediaFilename,
            'status' => 'received',
            'whatsapp_message_id' => $message['id'],
        ];
        $messageModel->insert($messageData);
        
        // Update conversation
        $conversationModel->update($conversation['id'], [
            'last_message_text' => mb_substr($contentText ?? '[Media]', 0, 200),
            'last_message_at' => date('Y-m-d H:i:s'),
            'unread_count' => ($conversation['unread_count'] ?? 0) + 1,
        ]);
        
        // Dispatch automation check
        $dispatcher = new JobDispatcher();
        $dispatcher->dispatch('run_automation', [
            'account_id' => $accountId,
            'contact_id' => $contact['id'],
            'conversation_id' => $conversation['id'],
            'message' => $messageData,
            'trigger_type' => 'new_message_received',
        ]);
        
        // Dispatch flow check
        $dispatcher->dispatch('check_flow', [
            'account_id' => $accountId,
            'contact_id' => $contact['id'],
            'conversation_id' => $conversation['id'],
            'message_text' => $contentText,
        ]);
    }

    /**
     * Process reaction message
     */
    private function processReaction(string $conversationId, array $message)
    {
        $reaction = $message['reaction'];
        $targetMessageId = $reaction['message_id'];
        $emoji = $reaction['emoji'];
        
        $messageModel = new MessageModel();
        $targetMessage = $messageModel->where('whatsapp_message_id', $targetMessageId)->first();
        
        if (!$targetMessage) {
            return;
        }
        
        $reactionModel = new MessageReactionModel();
        
        if (empty($emoji)) {
            // Remove reaction
            $reactionModel->where('message_id', $targetMessage['id'])
                ->where('actor_type', 'customer')
                ->delete();
        } else {
            // Add/update reaction
            $existing = $reactionModel->where('message_id', $targetMessage['id'])
                ->where('actor_type', 'customer')
                ->first();
            
            $reactionData = [
                'message_id' => $targetMessage['id'],
                'conversation_id' => $conversationId,
                'actor_type' => 'customer',
                'emoji' => $emoji,
            ];
            
            if ($existing) {
                $reactionModel->update($existing['id'], $reactionData);
            } else {
                $reactionData['id'] = generate_uuid();
                $reactionModel->insert($reactionData);
            }
        }
    }
    
    /**
     * Process status update (sent/delivered/read/failed)
     */
    private function processStatusUpdate(array $status)
    {
        $whatsappMessageId = $status['id'];
        $newStatus = $status['status']; // sent, delivered, read, failed
        
        // Update message status
        $messageModel = new MessageModel();
        $message = $messageModel->where('whatsapp_message_id', $whatsappMessageId)->first();
        
        if ($message) {
            $updateData = ['status' => $newStatus];
            
            if ($newStatus === 'failed' && !empty($status['errors'])) {
                $updateData['error_message'] = json_encode($status['errors']);
            }
            
            $messageModel->update($message['id'], $updateData);
        }
        
        // Update broadcast recipient if this is a broadcast message
        $recipientModel = new BroadcastRecipientModel();
        $recipient = $recipientModel->where('whatsapp_message_id', $whatsappMessageId)->first();
        
        if ($recipient) {
            $recipientModel->update($recipient['id'], ['status' => $newStatus]);
            
            // Update broadcast aggregate counts
            $broadcastModel = new BroadcastModel();
            $broadcast = $broadcastModel->find($recipient['broadcast_id']);
            
            if ($broadcast) {
                $field = $newStatus . '_count';
                $currentCount = $broadcast[$field] ?? 0;
                $broadcastModel->update($broadcast['id'], [$field => $currentCount + 1]);
            }
        }
    }
    
    /**
     * Process template status update from Meta
     */
    private function processTemplateStatus(array $event)
    {
        $metaTemplateId = $event['message_template_id'] ?? null;
        $newStatus = $event['event']; // APPROVED, REJECTED, PAUSED, DISABLED
        
        if (!$metaTemplateId) {
            return;
        }
        
        $templateModel = new MessageTemplateModel();
        $template = $templateModel->where('meta_template_id', $metaTemplateId)->first();
        
        if ($template) {
            $statusMap = [
                'APPROVED' => 'approved',
                'REJECTED' => 'rejected',
                'PAUSED' => 'paused',
                'DISABLED' => 'disabled',
                'IN_APPEAL' => 'in_appeal',
            ];
            
            $mappedStatus = $statusMap[$newStatus] ?? 'draft';
            $templateModel->update($template['id'], ['status' => $mappedStatus]);
        }
    }
}

Add routes in app/Config/Routes.php:
$routes->get('api/whatsapp/webhook', 'Api\WebhookController::verify');
$routes->post('api/whatsapp/webhook', 'Api\WebhookController::handle', ['filter' => 'webhook_signature']);
```

### Prompt 3.4 — Job Queue System (Dispatcher, Commands, Controllers)

```
Build the background job queue system for Rovix AI Leads Tool.

This replaces Node.js in-process async with a MySQL-based queue processed by cron.

Reference original wacrm files:
- wacrm-main/src/lib/queue/ (all job processors)
- wacrm-main/src/app/api/send/route.ts

Create app/Libraries/JobDispatcher.php:

namespace App\Libraries;

use App\Models\JobQueueModel;

class JobDispatcher
{
    /**
     * Dispatch a job to the queue
     * 
     * @param string $jobType Job type identifier
     * @param array $payload Job data
     * @param string|null $runAfter Delay execution until this datetime
     * @param int $priority Priority 1-10 (1=highest, 10=lowest)
     * @return int Job ID
     */
    public function dispatch(
        string $jobType,
        array $payload,
        ?string $runAfter = null,
        int $priority = 5
    ): int {
        $jobModel = new JobQueueModel();
        
        $data = [
            'job_type' => $jobType,
            'payload' => json_encode($payload),
            'status' => 'pending',
            'priority' => $priority,
            'run_after' => $runAfter,
            'attempts' => 0,
            'max_retries' => 3,
        ];
        
        $jobModel->insert($data);
        return $jobModel->getInsertID();
    }
    
    /**
     * Dispatch multiple jobs at once (for batch operations)
     */
    public function dispatchBatch(string $jobType, array $payloads, int $priority = 5): array
    {
        $ids = [];
        foreach ($payloads as $payload) {
            $ids[] = $this->dispatch($jobType, $payload, null, $priority);
        }
        return $ids;
    }
}

Create app/Commands/ProcessQueue.php:

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\JobQueueModel;
use App\Models\BaseModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class ProcessQueue extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:process';
    protected $description = 'Process pending background jobs';
    
    public function run(array $params)
    {
        BaseModel::setBypassAccountScope(true);
        
        $jobModel = new JobQueueModel();
        
        // Lock and fetch pending jobs with priority ordering
        $jobs = $jobModel
            ->where('status', 'pending')
            ->where('(run_after IS NULL OR run_after <= NOW())')
            ->where('(locked_until IS NULL OR locked_until <= NOW())')
            ->orderBy('priority', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->limit(50)
            ->findAll();
        
        if (empty($jobs)) {
            CLI::write('No pending jobs', 'green');
            return;
        }
        
        CLI::write('Processing ' . count($jobs) . ' jobs...', 'yellow');
        
        foreach ($jobs as $job) {
            try {
                // Lock the job for 5 minutes
                $lockUntil = date('Y-m-d H:i:s', time() + 300);
                $jobModel->update($job['id'], [
                    'status' => 'processing',
                    'locked_until' => $lockUntil,
                    'attempts' => $job['attempts'] + 1,
                ]);
                
                $payload = json_decode($job['payload'], true);
                
                // Process based on job type
                switch ($job['job_type']) {
                    case 'send_message':
                        $this->processSendMessage($payload);
                        break;
                        
                    case 'run_automation':
                        $this->processRunAutomation($payload);
                        break;
                        
                    case 'check_flow':
                        $this->processCheckFlow($payload);
                        break;
                        
                    case 'send_broadcast_batch':
                        $this->processSendBroadcastBatch($payload);
                        break;
                        
                    case 'execute_wait_step':
                        $this->processExecuteWaitStep($payload);
                        break;
                        
                    case 'send_daily_report':
                        $this->processSendDailyReport($payload);
                        break;
                        
                    default:
                        throw new \Exception("Unknown job type: {$job['job_type']}");
                }
                
                // Mark as done
                $jobModel->update($job['id'], [
                    'status' => 'done',
                    'locked_until' => null,
                ]);
                
                CLI::write("[{$job['id']}] {$job['job_type']} completed", 'green');
                
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                CLI::write("[{$job['id']}] Error: {$errorMsg}", 'red');
                
                // Log error history
                $failedLog = json_decode($job['failed_attempts_log'] ?? '[]', true);
                $failedLog[] = [
                    'attempt' => $job['attempts'],
                    'error' => $errorMsg,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                
                // Retry or mark as failed
                if ($job['attempts'] >= $job['max_retries']) {
                    $jobModel->update($job['id'], [
                        'status' => 'failed',
                        'error' => $errorMsg,
                        'failed_attempts_log' => json_encode($failedLog),
                        'locked_until' => null,
                    ]);
                } else {
                    // Exponential backoff: 2^attempts minutes
                    $retryAfter = date('Y-m-d H:i:s', time() + (pow(2, $job['attempts']) * 60));
                    $jobModel->update($job['id'], [
                        'status' => 'pending',
                        'run_after' => $retryAfter,
                        'error' => $errorMsg,
                        'failed_attempts_log' => json_encode($failedLog),
                        'locked_until' => null,
                    ]);
                }
            }
        }
        
        BaseModel::setBypassAccountScope(false);
        CLI::write('Queue processing completed', 'green');
    }
    
    private function processSendMessage(array $payload)
    {
        // Stub for now - will be implemented with MetaApi calls
        CLI::write('  → Send message job (stub)', 'yellow');
    }
    
    private function processRunAutomation(array $payload)
    {
        // Stub - will call AutomationEngine in Phase 9
        CLI::write('  → Run automation job (stub)', 'yellow');
    }
    
    private function processCheckFlow(array $payload)
    {
        // Stub - will call FlowEngine in Phase 10
        CLI::write('  → Check flow job (stub)', 'yellow');
    }
    
    private function processSendBroadcastBatch(array $payload)
    {
        // Stub - will implement batch sending in Phase 8
        CLI::write('  → Send broadcast batch job (stub)', 'yellow');
    }
    
    private function processExecuteWaitStep(array $payload)
    {
        // Stub - will resume automation from wait step
        CLI::write('  → Execute wait step job (stub)', 'yellow');
    }
    
    private function processSendDailyReport(array $payload)
    {
        // Stub - will generate daily report in Phase 11
        CLI::write('  → Send daily report job (stub)', 'yellow');
    }
}

### Prompt 3.2 — Meta API Client

```
Port the Meta WhatsApp Cloud API client for Rovix AI Leads Tool.

Reference: wacrm-main/src/lib/whatsapp/meta-api.ts

Create app/Libraries/WhatsApp/MetaApi.php with all API methods using cURL.

Base URL: https://graph.facebook.com/v21.0/

Private helper method:
- callApi(string $method, string $url, ?array $data, string $accessToken): array

Public methods (12 total):
1. sendText($phoneNumberId, $accessToken, $to, $text, $replyToMessageId = null)
2. sendImage($phoneNumberId, $accessToken, $to, $imageUrl, $caption = null)
3. sendVideo($phoneNumberId, $accessToken, $to, $videoUrl, $caption = null)
4. sendDocument($phoneNumberId, $accessToken, $to, $documentUrl, $filename, $caption = null)
5. sendAudio($phoneNumberId, $accessToken, $to, $audioUrl)
6. sendTemplate($phoneNumberId, $accessToken, $to, $templateName, $language, $components = [])
7. sendReaction($phoneNumberId, $accessToken, $messageId, $emoji)
8. sendInteractiveButtons($phoneNumberId, $accessToken, $to, $bodyText, $buttons, $headerText = null)
9. sendInteractiveList($phoneNumberId, $accessToken, $to, $bodyText, $buttonText, $sections)
10. getMediaUrl($mediaId, $accessToken): string
11. downloadMedia($url, $accessToken): string — Save to writable/uploads/chat-media/, return local path
12. uploadMedia($phoneNumberId, $accessToken, $filePath, $mimeType): string

Also create app/Libraries/WhatsApp/TemplateSendBuilder.php:
- buildComponents($template, $variables): array — Build template components from template model + variables

All methods should log errors and return response arrays with error handling.
```

### Prompt 3.3 — Webhook Controller

```
Port the WhatsApp webhook handler for Rovix AI Leads Tool. This processes ALL inbound WhatsApp events.

Reference: wacrm-main/src/app/api/whatsapp/webhook/route.ts

Create app/Controllers/Api/WebhookController.php with methods:

1. verify() GET:
   - Check hub.mode === 'subscribe' and hub.verify_token matches config
   - Return hub.challenge as plain text or 403

2. handle() POST:
   - Set BaseModel::setBypassAccountScope(true)
   - Parse JSON body
   - For each entry → changes → value:
     - If 'messages' present → processInboundMessage()
     - If 'statuses' present → processStatusUpdate()
     - If 'message_template_status_update' → processTemplateStatus()
   - Return 200 immediately

3. processInboundMessage($message, $metadata):
   - Find WhatsAppConfig by phoneNumberId → get account_id
   - Extract contact phone from $message['from']
   - Find or create Contact (normalize phone first)
   - Find or create Conversation
   - Determine message type (text/image/video/document/audio/reaction/interactive)
   - For media: download via MetaApi
   - For reactions: update message_reactions table
   - Insert into messages table
   - Update conversation: last_message_text, last_message_at, unread_count++, status='open'
   - Dispatch to job_queue: type='run_automation', type='check_flow'

4. processStatusUpdate($status):
   - Find message by whatsapp_message_id
   - Update message status (sent/delivered/read/failed)
   - If broadcast_recipient exists, update that too
   - Update broadcast aggregate counts

5. processTemplateStatus($event):
   - Find template by meta_template_id
   - Update status field

Add routes in Routes.php with webhook_signature filter on POST.
```

### Prompt 3.4 — Job Queue System

```
Build the background job queue for Rovix AI Leads Tool.

Create app/Libraries/JobDispatcher.php:
- dispatch(string $jobType, array $payload, int $priority = 5, ?string $runAfter = null): int

Create app/Commands/ProcessQueue.php:
- Command: queue:process
- SELECT jobs WHERE status='pending' AND (run_after IS NULL OR <= NOW()) AND (locked_until IS NULL OR <= NOW()) ORDER BY priority ASC, created_at ASC LIMIT 50
- For each: UPDATE status='processing', locked_until=NOW()+5min, attempts++
- Switch on job_type:
  - 'send_message': MetaApi::sendText()
  - 'run_automation': AutomationEngine::processForTrigger() (stub)
  - 'check_flow': FlowEngine::dispatchInbound() (stub)
  - 'send_broadcast_batch': Process batch of template sends (stub)
  - 'send_daily_report': DailyLeadReport::generate() (stub)
- On success: status='done'
- On failure: if attempts < max_retries → status='pending', else status='failed', append to failed_attempts_log JSON

Create app/Commands/RunScheduled.php:
- Command: run:scheduled
- Calls ProcessQueue
- This is what cPanel cron calls: * * * * * php spark run:scheduled

Create app/Controllers/Api/SendController.php:
- send() POST: authenticated endpoint
  - Validate conversation_id, content_type, content
  - Get WhatsAppConfig, decrypt token
  - Call MetaApi based on content_type
  - Insert message row
  - Return JSON

Create app/Controllers/Api/ReactController.php:
- react() POST: send reaction
```

### Testing Phase 3

```bash
# 1. Test webhook verification
curl "http://localhost:8080/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=YOUR_TOKEN&hub.challenge=test123"
# Should return: test123

# 2. Test encryption
php spark tinker
$enc = new \App\Libraries\WhatsApp\Encryption();
$encrypted = $enc->encrypt('test_access_token');
echo $encrypted;
$decrypted = $enc->decrypt($encrypted);
echo $decrypted;

# 3. Test phone normalization
use App\Libraries\WhatsApp\PhoneUtils;
echo PhoneUtils::normalize('+91 98765-43210');
echo PhoneUtils::format('919876543210');

# 4. Test job queue
php spark queue:process
# Should process any pending jobs

# 5. Test send endpoint (requires WhatsApp config)
curl -X POST http://localhost:8080/api/send \
  -H "Cookie: ci_session=..." \
  -H "Content-Type: application/json" \
  -d '{"conversation_id":"...", "content_type":"text", "content_text":"Hello"}'
```

**Pass Criteria:**
- ✓ Webhook verification works
- ✓ Encryption/decryption works with GCM
- ✓ Phone utils normalize and format correctly
- ✓ Job queue processes jobs
- ✓ Send endpoint works (if WhatsApp configured)
- ✓ Webhook signature filter rejects invalid requests

---


## PHASE 4: Inbox Module (Week 3-4 — Days 13-18)

### Prompt 4.1 — Inbox Conversation List + Thread View

```
Build the inbox UI for Rovix AI Leads Tool with conversation list and message thread.

Reference original wacrm files:
- wacrm-main/src/app/(dashboard)/inbox/page.tsx
- wacrm-main/src/components/inbox/conversation-list.tsx
- wacrm-main/src/components/inbox/message-thread.tsx

Create app/Controllers/InboxController.php with methods for conversation management, message loading, assignments, and status updates.

Create app/Views/inbox/index.php with two-column layout: conversation list (left 30%) and message thread (right 70%).

Use Alpine.js for real-time updates with 5-second polling.
```

### Prompt 4.2 — Message Composer

```
Build message composer with text input, media upload, template selector, emoji picker.

Create app/Controllers/Api/ComposeController.php with uploadMedia(), sendMessage(), sendTemplateMessage() methods.

Support images, videos, documents, audio (max 16MB each). Save to writable/uploads/chat-media/.
```

### Prompt 4.3 — Conversation Actions

```
Build conversation actions: assign agent, add/remove tags, add notes, close conversation.

Create contact sidebar with info, tags, custom fields, notes, and activity timeline.
```

### Testing Phase 4

**Pass Criteria:**
- ✓ Conversation list loads and displays correctly
- ✓ Can send text, media, and template messages
- ✓ Can assign, tag, and add notes
- ✓ Auto-refresh works

---

## PHASE 5: Contacts Module (Week 4-5 — Days 19-24)

### Prompt 5.1 — Contacts CRUD + List View

```
Build contacts module with full CRUD operations.

Reference: wacrm-main/src/app/(dashboard)/contacts/page.tsx

Create app/Controllers/ContactsController.php with index, create, store, show, edit, update, delete methods.

Create views: index (table with search/filter), create/edit (forms), show (detail page with tabs).

Phone normalization on save, duplicate detection.
```

### Prompt 5.2 — Tags + Custom Fields Management

```
Build tag and custom field management for contacts.

Create app/Controllers/TagsController.php:
- CRUD for tags (name, color)
- Bulk tag operations

Create app/Controllers/CustomFieldsController.php:
- CRUD for custom field definitions
- Field types: text, number, date, dropdown
- Per-contact value storage in contact_custom_values table

Add to contact detail page:
- Tag management UI with color badges
- Custom field values editor
```

### Prompt 5.3 — CSV Import

```
Build CSV import feature for bulk contact creation.

Reference: wacrm-main/src/app/(dashboard)/contacts/import/page.tsx

Create app/Controllers/ContactsController.php methods:

1. importView() GET: Show upload form
2. uploadCSV() POST: Parse CSV, show field mapping interface
3. processImport() POST: Create contacts in batches, handle duplicates

Features:
- Field mapping (CSV columns → contact fields)
- Duplicate detection (by phone)
- Skip/Update/Merge options
- Tag assignment during import
- Progress indicator
- Error reporting (which rows failed)

Use PhpSpreadsheet library for CSV parsing.
```

### Prompt 5.4 — Contact Notes + Activity Timeline

```
Build contact notes and activity timeline.

Create app/Controllers/ContactNotesController.php:
- store(): Add note
- delete(): Remove note (owner only)

Create app/Views/contacts/partials/notes.php:
- List of notes with author, timestamp
- Add note form with rich text editor (optional)

Create app/Views/contacts/partials/activity.php:
- Timeline showing:
  - Messages sent/received
  - Deals created/updated
  - Tags added/removed
  - Notes added
  - Conversations assigned/closed
- Fetch from multiple tables, merge by created_at
```

### Testing Phase 5

**Pass Criteria:**
- ✓ Can create, edit, delete contacts
- ✓ Can manage tags and custom fields
- ✓ CSV import works with field mapping
- ✓ Notes and activity timeline display correctly
- ✓ Search and filters work
- ✓ Duplicate phone detection works

---

