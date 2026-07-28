# ROVIX CRM

A multi-tenant WhatsApp-first CRM. Each account manages its own contacts, conversations, sales pipeline, and marketing — all driven through the WhatsApp Business (Meta Cloud) API. Built on CodeIgniter 4 / PHP 8.2 / MySQL, designed to run on standard cPanel shared hosting (no Docker).

## What it does

- **Shared WhatsApp Inbox** — receive and reply to customer messages in real time (text, media, voice notes, reactions, quoted replies). Inbound messages arrive instantly via the Meta webhook; outbound sends go through a background queue.
- **Contacts** — per-account contact book keyed by phone number, with custom fields, tags, notes, lead status, and CSV import.
- **Deals & Pipelines** — drag-and-drop Kanban pipelines, stages, and deal tracking with values and won/lost status.
- **Broadcasts** — send an approved WhatsApp template to many contacts in batches, with per-recipient status and unsubscribe handling.
- **Flows** — a visual (Drawflow) builder for interactive WhatsApp conversations: buttons, lists, media, conditions, forms, catalog/product sends, handoff, AI nodes, and sub-flow triggers.
- **Automations** — trigger-based rules (new message, keyword, tag added, time-based, etc.) that send messages/templates, tag contacts, create deals, call webhooks, and more.
- **Appointments** — booking pages, appointment types, 24h reminders, post-appointment follow-ups, and Google Calendar sync.
- **Catalog & Orders** — WhatsApp catalog/product messages and order capture.
- **Templates** — create, submit, and manage Meta message templates (incl. media headers).
- **AI assist** — optional AI (Claude/OpenAI) for reply suggestions and flow AI nodes, per-account keys.
- **Team & roles** — invite members, role-based permissions (admin/agent), activity log, agent time logs.
- **Reports & Dashboard** — sending history, lead/deal metrics, daily digest reports over WhatsApp.

## Stack

- **CodeIgniter 4**, **PHP 8.2** (ext: intl, mbstring, mysqli, curl, gd, fileinfo, sodium)
- **MySQL / MariaDB** (utf8mb4)
- **Meta WhatsApp Cloud API** (Graph v21) for all messaging
- File-based sessions; background jobs via a DB-backed queue + cron

## Architecture

Multi-tenant: every table is scoped by `account_id` (see `app/Models/BaseModel.php`). Inbound WhatsApp messages hit `POST /api/whatsapp/webhook`, are saved synchronously, then enqueue `run_automation` / `check_flow` jobs. All outbound sends (messages, templates, broadcasts, reports) run through the `job_queue` table, drained by the `queue:process` command. Time-based work (reminders, scheduled broadcasts, daily reports, cleanups) is orchestrated by `run:scheduled`.

```
app/
  Controllers/         web + Api/ controllers
  Libraries/           MetaApi, MessageSender, FlowEngine, AutomationEngine, WhatsApp/*
  Models/              one per table, all extend BaseModel (account scoping)
  Commands/            spark CLI: queue:process, run:scheduled, cleanups, reminders
  Database/Migrations/ full schema (run with `php spark migrate`)
  Config/              App, Database, WhatsApp, Rovix, Routes, Filters, ...
public/                web root (index.php lives here)
writable/              logs, cache, sessions, uploads
```

## Installation (cPanel)

Full step-by-step guide: **[DEPLOY-CPANEL.txt](DEPLOY-CPANEL.txt)**. In short:

1. Set the domain to **PHP 8.2** and enable the required extensions.
2. Create a MySQL database + user.
3. Upload the package; point the document root at `public/` (or use the fallback `.htaccess` layout).
4. Copy the env template to `.env` and fill in every value (see below).
5. Generate keys and build the schema:
   ```bash
   php spark key:generate      # writes encryption.key
   php spark migrate           # creates all tables
   ```
   No SSH? Import the provided `.sql` schema via phpMyAdmin instead of `migrate`.
6. Add the cron jobs (below).
7. Run AutoSSL, then register the webhook in Meta.

### Configuration (`.env`)

Fill in at minimum:

| Key | Purpose |
|-----|---------|
| `app.baseURL` | Your https domain (trailing slash) |
| `database.default.*` | DB host / name / user / password |
| `encryption.key` | Framework key — `php spark key:generate` |
| `rovix.encryptionKey` | 64 hex chars — encrypts stored WhatsApp tokens |
| `whatsapp.metaAppSecret` | Meta app secret (verifies inbound webhooks) |
| `whatsapp.verifyToken` | Any secret string; must match Meta's webhook config |
| `META_APP_ID` | Your Meta App ID |
| `email.fromEmail` | Sender address (password-reset emails) |
| `session.savePath` | Absolute path to `writable/session` |

> `.env` is gitignored — never commit real secrets. Each account's own WhatsApp Phone Number ID / token are entered in-app (Settings → WhatsApp) and stored encrypted.

### Cron jobs (required)

Both must run **every minute** — without them, no outbound messages, automations, flows, or reports run:

```
* * * * * /usr/local/bin/php /home/<user>/rovix-crm/spark queue:process
* * * * * /usr/local/bin/php /home/<user>/rovix-crm/spark run:scheduled
```

Adjust the PHP binary and app path to your server. `run:scheduled` invokes the reminders and cleanups internally, so no other cron entries are needed.

### Webhook (Meta)

- **Callback URL:** `https://<your-domain>/api/whatsapp/webhook`
- **Verify token:** the same value as `whatsapp.verifyToken` in `.env`

## License

See [LICENSE](LICENSE). Built on [CodeIgniter 4](https://codeigniter.com).
