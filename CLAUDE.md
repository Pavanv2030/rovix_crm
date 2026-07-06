# Rovix AI Leads Tool — CRM

## Project overview

Multi-tenant WhatsApp CRM built on **CodeIgniter 4** (PHP 8.2), **MySQL**, and **Tailwind CSS**.
Each tenant is identified by an `account_id` (UUID). All business data is scoped to an account.

Primary features: inbox, contacts, broadcasts, automations, flows, pipelines, deals, team management, settings.

## Environment

- **Stack:** XAMPP on Windows (Apache + MySQL + PHP 8.2)
- **URL:** `http://localhost/rovix-crm/public`
- **PHP:** `C:\xampp\php\php.exe`
- **Config:** copy `env` → `.env`, set `CI_ENVIRONMENT = development` and DB credentials

## Commands

```bash
# Run from C:\xampp\htdocs\rovix-crm

# Migrations
C:\xampp\php\php.exe spark migrate

# Rollback
C:\xampp\php\php.exe spark migrate:rollback

# Run a custom spark command
C:\xampp\php\php.exe spark <command>

# Spark commands available
C:\xampp\php\php.exe spark process:queue       # process job queue
C:\xampp\php\php.exe spark run:scheduled       # run all scheduled tasks
C:\xampp\php\php.exe spark media:cleanup       # delete unused media files
C:\xampp\php\php.exe spark flows:cleanup       # clean up stale flow runs
C:\xampp\php\php.exe spark webhooks:cleanup    # delete webhook_logs older than 30 days

# Install dev dependencies (required before running PHPUnit)
C:\xampp\php\php.exe composer.phar install

# Unit tests
vendor\bin\phpunit tests\Libraries\

# Load test (requires a running server + valid session cookie)
C:\xampp\php\php.exe tests\LoadTest.php http://localhost/rovix-crm/public <ci_session>
```

## Multi-tenancy

- Every model extends `App\Models\BaseModel`, which automatically scopes all queries to the current `account_id` from session.
- Primary keys are UUIDs generated via the `generate_uuid()` helper.
- Never query any model without considering account isolation — `BaseModel` handles it, but custom queries need manual `where('account_id', ...)`.

## Role system

Roles in ascending privilege order: `viewer` → `agent` → `admin` → `owner`.

```php
// Numeric rank for comparison
role_rank('admin')  // returns int

// Guard helpers (check session role against required level)
can_edit_settings()   // admin or higher
can_manage_members()  // admin or higher
```

Session key is `account_role` (not `role`). Filters use `RoleFilter` for route-level guards.

## Authentication & sessions

- `AuthFilter` — enforces login on all protected routes; updates `last_seen_at` (throttled to 5 min)
- `RoleFilter` — compares `role_rank()` for minimum required role
- `WebhookSignatureFilter` — validates `X-Hub-Signature-256` on inbound Meta webhook routes
- OTP verification is handled via `OtpVerificationModel`

## Key libraries

| Class | Purpose |
|---|---|
| `WhatsApp\MetaApi` | Calls Meta Cloud API. Use `sendText()`, not `sendTextMessage()`. |
| `WhatsApp\Encryption` | AES-256-GCM encrypt/decrypt for WhatsApp access tokens |
| `WhatsApp\WebhookSignature` | HMAC-SHA256 signature verify for webhook payloads |
| `WhatsApp\PhoneUtils` | `normalize()`, `isValid()`, `format()` — no `formatForDisplay()` |
| `JobDispatcher` | `dispatch($type, $payload, $accountId, $priority)` — inserts to `job_queue` |
| `BroadcastProcessor` | Rate-limited send at 70 msg/sec |
| `AutomationEngine` | Runs automation step chains |
| `FlowEngine` | Executes visual flow graphs |

## Logging

```php
ActivityLogModel::record($accountId, $userId, $action, $details);
```

Use this for every significant user action (settings save, role change, invitation, etc.).

## WhatsApp webhook flow

1. `POST /webhook/{token}` → `Api\WebhookController::handle()`
2. Resolves `account_id` from `phone_number_id` via `WhatsAppConfigModel`
3. Processes message, writes to `webhook_logs` with timing
4. `webhook_logs` is purged by `CleanupWebhookLogs` spark command (runs at hour 4)

## Database migrations

Migrations live in `app/Database/Migrations/` numbered `000001`–`000030`.
Always run `php spark migrate` after pulling new migrations.
The test suite runs its own migration pass (`$migrate = true` in `DatabaseTestTrait`).

## Tests

PHPUnit config: `phpunit.dist.xml`. Bootstrap: `system/Test/bootstrap.php`.

```
tests/Libraries/          ← unit tests (no DB except JobQueueTest)
tests/LoadTest.php        ← standalone CLI response-time check
```

`JobQueueTest` uses `DatabaseTestTrait` and hits a real test DB — requires dev deps installed.

## gstack Skills

When a request matches one of these skills, invoke it via the Skill tool.

| Skill | When to use |
|---|---|
| `/office-hours` | Product ideas, feature brainstorming, startup strategy |
| `/plan-ceo-review` | High-level scope and strategy review before building |
| `/plan-eng-review` | Architecture and technical design review |
| `/autoplan` | Full review pipeline (CEO → design → eng) |
| `/review` | Code review / diff check before committing |
| `/ship` | Create PR, finalize and ship a feature branch |
| `/qa` | QA a feature with browser interaction + fixes |
| `/qa-only` | QA report only — no code changes |
| `/design-review` | Visual polish and UI/UX audit + fixes |
| `/investigate` | Systematic root-cause debugging for bugs/errors |
| `/cso` | Security audit (OWASP Top 10 + STRIDE) |
| `/retro` | Retrospective — what worked, what didn't |
| `/learn` | Explain a concept or codebase area in depth |

## Conventions

- Models: extend `BaseModel`, list all columns in `$allowedFields`
- Controllers: extend `BaseController`, use `can_*()` helpers before writes
- Views: Tailwind CSS, tabbed layouts use inline `<div id="tab-*">` + JS toggle
- No `session('role')` — always use `session('account_role')`
- Activity logging: call `ActivityLogModel::record()` in every controller write action
- Encryption: always use `App\Libraries\WhatsApp\Encryption` for access tokens, never store plaintext
