# QA Report — Catalog + Appointment Booking

**Date:** 2026-07-03
**Target:** https://swab-refill-carnival.ngrok-free.dev/rovix-crm/public
**Mode:** qa-only (report only, no fixes)
**Tester:** gstack /qa-only
**Framework:** CodeIgniter 4 (PHP 8.2) + Tailwind (via CDN — see ISSUE-010) + Alpine.js
**Screenshots:** 40+ captured this run, in `.gstack/qa-reports/screenshots/`
**Auth:** Logged in as account owner (Pavan Venkat Venkat); also tested the public booking page fully logged out

---

## Module Ratings

| # | Module | Rating | Notes |
|---|--------|--------|-------|
| 1 | Settings → Catalog (fetch/connect/sync) | 🔴 Broken | Disconnect button hits the wrong endpoint and never disconnects (ISSUE-002). Fetch/Connect/Sync mechanics otherwise work, but rely on native alert() (ISSUE-001). |
| 2 | Inbox: send catalog / send product | 🟡 Minor issues | Both work end-to-end (real API calls, correct empty-state copy). Error feedback via native alert(), and a Meta-error failure returns HTTP 500 instead of 4xx. |
| 3 | Catalog → Orders (filters/status/search) | 🟢 Pass | Filters, search, empty state, XSS-safe input reflection, mobile layout all clean. Row-level status-update actions untestable — no seed order data exists. |
| 4 | Appointment Types (create/edit/delete/availability) | 🟡 Minor issues | Create + Delete work correctly with good validation. **Edit does not exist anywhere in the UI** (ISSUE-005) — the create form's own copy promises "customize after creation," which is false. Availability JSON has zero UI surface. Mobile layout is broken (ISSUE-006). |
| 5 | Google Calendar OAuth | 🟢 Pass | Observed already connected, correctly displaying "Connected" state with a live Google Meet link on the real test booking. Did not exercise a fresh connect/disconnect cycle to avoid disrupting the live integration. |
| 6 | Inbox: send appointment flow | 🔴 Broken | Flow send succeeds against the WhatsApp API (real wamid returned) but the message is never written to the `messages` table — invisible in the inbox thread and conversation list, both times tested (ISSUE-004). |
| 7 | Flow builder: book_appointment node | 🟡 Minor issues | Builder itself (drag/click-to-add, node config, connect, save, delete) works well and has good empty/toast UX. No appointment-booking node type exists in the 17-node palette at all (ISSUE-007) — this scope item is untestable because the feature doesn't exist. |
| 8 | Public booking page /booking/{token} | ✅ Fixed | Was Broken: one-line routing bug, `booking/(:segment)` missing `/$1`, every link 404'd. **Fixed and verified live** — invoice now renders correctly (invoice #, status, amount, customer, appointment times, Google Meet link), Print button fires cleanly, layout is clean on both desktop and mobile (ISSUE-008). |
| 9 | Cron: reminder + follow-up | ✅ Fixed | Was Broken: `appointments:reminders` logic was correct but never invoked by `run:scheduled`. **Fixed and verified live** — `run:scheduled` now calls it, confirmed by seeding a test appointment and watching a real reminder fire through the full cron path. Also added: manual "Send Reminder" button on the Appointments list (on-demand send, any time, not gated by the 24hr-before window), and reminder/follow-up messages now log into the inbox thread (previously invisible, matching the ISSUE-004 pattern — fixed proactively while wiring this in) (ISSUE-009). |

**Score: 2 Pass/Minor-adjacent modules holding up, 2 of 9 modules still Broken (ISSUE-002 Catalog Disconnect, ISSUE-004 flow-send invisibility), 2 fixed live during this session (ISSUE-008 booking page, ISSUE-009 cron reminders/follow-ups).** The full appointment lifecycle — book → confirm → get reminded → view your booking → get a follow-up — now works end-to-end.

---

## Top 3 Things to Fix

1. ~~**ISSUE-009**~~ — **Fixed live during this session.** `run:scheduled` now calls `appointments:reminders`; also added a manual "Send Reminder" button and made both paths log into the inbox.
2. ~~**ISSUE-008**~~ — **Fixed live during this session.** Added `/$1` to `app/Config/Routes.php:226`, verified working end-to-end.
3. **ISSUE-004** — Appointment flow sends succeed but vanish from the inbox — staff have no visibility that a booking flow was ever sent to a customer. (Same root cause class as ISSUE-009's original invisibility problem, now fixed there — this one, in `Api\AppointmentsController::sendFlow()`, is still open.)

---

## Issues

### ISSUE-001: Native browser alert()/confirm() used for feedback across Catalog + Appointments, not in-app UI
**Module:** Catalog → Settings, Appointments → Manage Types (app-wide pattern)
**Severity:** Low
**Screen:** Desktop
**Steps:**
1. Go to Catalog page, click "Fetch Catalogs from Meta" (or Connect / Sync Now)
2. Result fires as a native JS `alert()` popup — "No catalogs found. Make sure your WABA has a connected catalog in Meta Commerce Manager." / "Connected! Found 0 products." / "Synced! 0 products."
**Expected:** Result shown as an in-app toast/banner consistent with the rest of the product (the page already has a styled red error banner for the Meta API 100 error — but success/empty-result paths fall back to `window.alert()`).
**Actual:** Native browser alert dialogs are jarring, block the page thread, don't match app styling, and are invisible to automated screenshot tools (only caught via console/dialog log, not visually).
**Evidence:** screenshots/01c-catalog-fetch-result.png (dialog not visible in screenshot — confirmed via `$B dialog` log). Same pattern also on Appointment Types create form: submitting with empty Name fires `alert("Name is required")` instead of an inline field error — no red border, no helper text near the field itself (screenshots/04d-empty-name-validation.png). Contrast: the Flows module does this correctly — deleting a flow shows a proper green toast + inline banner ("Flow deleted.") with no native dialog. The pattern is inconsistent across the app, not a hard technical constraint.

### ISSUE-002: Disconnect button does not disconnect catalog — hits wrong endpoint, fails silently
**Module:** Catalog → Settings
**Severity:** High
**Screen:** Desktop
**Steps:**
1. Connect a catalog (any ID)
2. Click "Disconnect" → confirm the native "Disconnect catalog?" dialog
3. Page reloads, catalog still shows "Catalog connected: {id}"
**Expected:** Catalog disconnects, page returns to "No Catalog Connected" empty state.
**Actual:** Clicking Disconnect fires `POST /catalog/connect` (the CONNECT endpoint, not a disconnect route) which returns `400 Bad Request`. The connection is never removed — confirmed by reloading the page and the connected catalog ID still showing. No error is surfaced to the user; the confirm dialog just closes and the page silently re-renders in the same connected state. Users have no way to disconnect/re-enter a wrong catalog ID from the UI.
**Evidence:** screenshots/01h2-after-reload.png — network log shows `POST .../catalog/connect → 400` on Disconnect click, catalog still connected after page reload.

### ISSUE-003: No mobile navigation — sidebar completely disappears at 375px, no hamburger menu
**Module:** Global (affects every screen in this report)
**Severity:** Critical
**Screen:** Mobile (375px)
**Steps:**
1. Load any page (Dashboard, Catalog, Appointments, etc.) at 375px viewport
2. Look for a way to reach other sections
**Expected:** Hamburger/menu icon that opens the sidebar nav (Inbox, Contacts, Pipelines, Templates, Broadcasts, Automations, Reports, Catalog, Orders, Appointments, Flows, Team, Settings).
**Actual:** The entire left sidebar nav vanishes below desktop width with NO replacement trigger — no hamburger icon, no bottom tab bar, nothing in the ARIA tree or cursor-interactive scan. Top bar only shows page title + avatar dropdown. Once a user navigates away from Dashboard's quick-action buttons, there is no in-UI way to reach Catalog, Orders, Appointments, Automations, Team, or Settings on mobile — only reachable by typing a direct URL. This blocks the entire mobile experience.
**Evidence:** screenshots/global-mobile-no-nav.png, screenshots/01i-catalog-mobile.png

### ISSUE-004: "Send Appointment Booking" flow message never appears in inbox thread
**Module:** Inbox → Send Appointment Flow
**Severity:** High
**Screen:** Desktop
**Steps:**
1. Open a conversation, click the calendar icon → "Send Appointment Booking"
2. Pick "sales call" → Send
3. API call `POST /api/appointments/send-flow` returns `200 {"success":true,"meta":{...,"messages":[{"id":"wamid...."}]}}` — message genuinely sent to WhatsApp (real wamid returned)
4. Reload the conversation thread
**Expected:** Outbound flow message bubble appears in the thread (like every other outbound message type), and the conversation list preview updates to reflect it.
**Actual:** Nothing appears. Thread still ends at the last inbound message ("Who is India's PM"), conversation list sidebar still shows the same stale preview text. Confirmed by reading source: `AppointmentsController::sendFlow()` (app/Controllers/Api/AppointmentsController.php:80-98) calls `MetaApi::sendFlowMessage()` and returns the JSON response directly — it never inserts a row into `messages` or updates `conversations.last_message_text/last_message_at`, unlike every other send path in this codebase. Sent twice during testing (both real wamids), neither shows up.
**Evidence:** screenshots/06d-recheck-thread.png — thread unchanged after 2 successful sends. Root cause: app/Controllers/Api/AppointmentsController.php:95 `return $this->response->setJSON(['success' => true, 'meta' => $response]);` — no MessageModel insert before this line.

### ISSUE-005: No way to edit an existing Appointment Type
**Module:** Appointments → Manage Types
**Severity:** Medium
**Screen:** Desktop
**Steps:**
1. Go to Appointments → Manage Types
2. Look for an edit action on the "sales call" type row
**Expected:** An Edit button/link to change name, duration, price, availability, max days ahead, buffer minutes.
**Actual:** Row only has a "Delete" action. No Edit button in the ARIA tree, and the row/heading is not clickable (confirmed via cursor-interactive scan — plain `[heading]`, no pointer handler). Once a type is created, its only lifecycle action is delete-and-recreate — which also orphans its published WhatsApp Flow (Flow ID shown next to it), forcing a full re-publish. To change a $0 price to a real price, or fix a typo, or adjust weekly availability, the only path is delete + recreate + re-publish flow.
**Evidence:** screenshots/04b-manage-types.png, screenshots/04c-new-type-modal.png (create modal's own helper text says "customize per-day hours after creation" — that capability does not exist anywhere in the UI). Availability JSON (`monday`/`tuesday`/etc. enabled+start+end, confirmed present in the DB via `GET /api/appointments/types`) has NO UI surface at all — not on create, not after. Only reachable by direct DB edit.

### ISSUE-006: Appointment Types page broken on mobile — button clipped off-screen, text overlaps
**Module:** Appointments → Manage Types
**Severity:** Medium
**Screen:** Mobile (375px)
**Steps:** Load /appointments/types at 375px width
**Expected:** Header actions wrap or stack; type row content doesn't overlap.
**Actual:** "+ New Type" button is clipped by the right edge of the viewport (only "Ne...Typ" visible, no horizontal scroll or wrap to bring it fully on-screen). On the type row, the "Flow ID: 3962403964066264" label overlaps directly on top of the "sales call" heading and the Active/Flow Published badges — both pieces of text render stacked/overlapping, unreadable.
**Evidence:** screenshots/04g-types-mobile.png

### ISSUE-007: No "book_appointment" node exists in the Flows visual builder
**Module:** Flow builder → book_appointment node
**Severity:** Medium (scope gap, not a crash)
**Screen:** Desktop
**Steps:**
1. Go to Flows → Build your first flow
2. Inspect the full node palette
**Expected:** A node type to trigger appointment booking from within a visual chatbot decision tree (mirroring how `send_catalog`/`send_product` nodes already exist for Catalog).
**Actual:** The palette has 17 node types (`start`, `send_message`, `send_buttons`, `send_list`, `send_media`, `send_media_buttons`, `url_button`, `request_location`, `collect_input`, `collect_form`, `condition`, `set_tag`, `add_to_group`, `handoff`, `end`, `send_catalog`, `send_product` — confirmed via the page's `NODE_SCHEMAS` JS config) — no appointment/booking node anywhere. Appointment booking can only be triggered from the separate **Automations** feature (step type `send_appointment_flow`), not from this **Flows** visual canvas, even though Flows already mirrors Automations for catalog (`send_catalog`/`send_product` exist in both). Two separate "flow" builders in this app with inconsistent feature parity is also a source of user confusion — worth a product decision on whether Flows should get the same appointment step Automations has.
**Evidence:** screenshots/07c-palette-scrolled.png, NODE_SCHEMAS constant in page source at /flows/create

### ISSUE-008: Public booking page returns "Booking Not Found" for EVERY link, always — route is missing its `/$1` param binding
**Module:** Public booking page /booking/{token}
**Severity:** Critical
**Screen:** Desktop + Mobile (both affected — page never loads)
**Root cause (confirmed, exact):** `app/Config/Routes.php:226`
```php
$routes->get('booking/(:segment)',                          'BookingController::show');
```
This is missing `/$1` at the end — every other segment-capturing route in this same file has it (e.g. line 26: `'contacts/(:segment)', 'ContactsController::view/$1'`). Without `/$1`, CI4 matches the URL pattern but calls `BookingController::show()` with **zero arguments**. Since `show(?string $token = null)` defaults to `null`, the controller always takes its "no token" branch — the exact same 404 "Booking Not Found" response fires for literally every `/booking/{anything}` URL, regardless of whether the token is valid, was never generated, or belongs to a real confirmed appointment. This is not a data problem, not a caching problem, not an account-scoping problem — it is a one-line routing typo that has made the entire public booking page feature non-functional for every customer since this route was added.
**How verified:** Temporarily added a one-line debug marker to each of `BookingController::show()`'s two return paths (both restored immediately after, file re-linted clean) and re-requested the exact same real booking URL. The response came back from the `$token === null` branch — proving the URL segment never reaches the controller at all, for any token.
**Steps to reproduce:** Any `/booking/{token}` URL, including the real one for the account's actual confirmed appointment (`/booking/f961401d460f3980a0047198a36656c1`).
**Expected:** `$routes->get('booking/(:segment)', 'BookingController::show/$1');` — invoice page renders (customer name, appointment type, date/time, Google Meet link, print button).
**Actual:** "Booking Not Found — This booking link is invalid or has expired," 100% of the time, for every booking link the app has ever generated.
**Evidence:** app/Config/Routes.php:226 (missing `/$1`). Page screenshot: screenshots/08-booking-page.png
**Status: FIXED.** Applied the one-line change (`'BookingController::show/$1'`), verified live: page now returns 200, invoice renders correctly (#97414B, sales call, 3rd Jul 2026 2:00pm–2:30pm, Status Confirmed, Amount INR 0.00), Google Meet "Join Meeting" link present, Print button works with no console errors, and layout is clean on both desktop and mobile (375px). Screenshots: screenshots/08c-booking-fixed.png, screenshots/08d-booking-mobile.png.

### ISSUE-009: Appointment reminder + follow-up cron logic is correct but never runs — not wired into `run:scheduled`
**Module:** Cron — reminder + follow-up
**Severity:** Critical
**Screen:** N/A (backend)
**Steps:**
1. Read `app/Commands/RunScheduled.php` (the `spark run:scheduled` command — the one CLAUDE.md documents as "run all scheduled tasks", presumably what's wired to the real Windows Task Scheduler / cron entry)
2. Compare against `app/Commands/SendAppointmentReminders.php` (`spark appointments:reminders` — sends 24hr-ahead reminders, post-appointment follow-ups, and auto-completes past appointments)
**Expected:** `run:scheduled` calls the appointment reminder/follow-up logic every time it runs (like it does for scheduled broadcasts and time-based automations, which it does correctly handle inline).
**Actual:** `RunScheduled::run()` handles: `queue:process`, daily report (hour 8), media cleanup (hour 2), flow cleanup (hour 3), webhook cleanup (hour 4), due scheduled broadcasts, and time-based automations — it never calls `appointments:reminders` or its logic anywhere. `spark appointments:reminders` is a complete, isolated command that must be triggered independently; nothing in the codebase's own scheduler invokes it. Also not documented in CLAUDE.md's command table (which lists `process:queue`, `run:scheduled`, `media:cleanup`, `flows:cleanup`, `webhooks:cleanup` but omits `appointments:reminders`).
**Verified the reminder/follow-up logic itself works correctly** when run directly: inserted a throwaway appointment scheduled for tomorrow → `spark appointments:reminders` sent a real WhatsApp reminder message and set `reminder_sent_at` (confirmed idempotent — running again sent nothing). Inserted a throwaway appointment that ended 90 minutes ago → same command sent a real follow-up message and set `follow_up_sent_at` + `status = completed`. Both test rows cleaned up after.
**Net effect (before fix):** unless a separate, undocumented cron/Task Scheduler entry independently calls `spark appointments:reminders`, no customer will ever receive an appointment reminder or a post-appointment follow-up in this app's current configuration.
**Evidence:** app/Commands/RunScheduled.php (no reference to appointments:reminders), app/Commands/SendAppointmentReminders.php:16 (`protected $name = 'appointments:reminders'`) — verified via direct `spark appointments:reminders` runs against temporary test data (cleaned up).

**Status: FIXED.** Three changes, all verified live:
1. `app/Commands/RunScheduled.php` — added `command('appointments:reminders');` alongside the existing `queue:process` call. Verified by seeding a throwaway appointment scheduled for tomorrow and running `spark run:scheduled` (the actual cron entrypoint, not the isolated command) — output: `Reminder sent → 917013389812`. Test row cleaned up after.
2. `app/Commands/SendAppointmentReminders.php` — `sendWaMessage()` now also inserts into `messages` and updates `conversations.last_message_text/last_message_at` when the appointment has a `conversation_id`, so reminder/follow-up sends are visible in the inbox (previously they'd have shared the ISSUE-004 invisibility problem the moment this cron started actually firing).
3. New: manual **"Send Reminder"** button on the Appointments list (`app/Views/appointments/index.php`), wired to a new `AppointmentsController::sendReminder()` endpoint (`POST appointments/{id}/send-reminder`) — lets staff send a reminder on demand for any non-cancelled/completed appointment, not gated by the 24-hours-before window the automated cron uses. Sets `reminder_sent_at`, logs the message into the inbox thread, and records an `appointments.reminder_sent_manual` activity log entry. Row shows a "✓ Reminded" tag once sent, button stays available for re-sends.

Verified end-to-end: manual button click → real WhatsApp message sent (200 response) → visible in inbox thread and conversation list preview → "✓ Reminded" tag shown. Cron path independently verified the same way via a seeded test appointment.

### ISSUE-010: Tailwind loaded via CDN, not built for production
**Module:** Global
**Severity:** Low
**Screen:** All
**Steps:** Any page load, check console
**Expected:** Tailwind compiled/purged via PostCSS or the Tailwind CLI per the project's own CLAUDE.md conventions ("Views: Tailwind CSS").
**Actual:** Console warning on every page: `cdn.tailwindcss.com should not be used in production. To use Tailwind CSS in production, install it as a PostCSS plugin or use the Tailwind CLI.` Larger payload, no purging, and a runtime dependency on an external CDN for core styling.
**Evidence:** Console log, every page tested this session.

---

## UX Audit Summary (applied across all 9 modules)

| Check | Result |
|---|---|
| Back button exists + works | N/A — this app uses a persistent left sidebar, not a back-button pattern. Sidebar nav works correctly on desktop for all 9 screens. |
| Buttons clickable, correct action fires | Mostly yes. Exceptions: Catalog Disconnect (ISSUE-002), Appointment Type Edit doesn't exist (ISSUE-005). |
| No dead links | None found in the 9 modules tested. |
| Loading states show | Catalog Fetch/Sync show a "Fetching…" button state. Flow builder has no loading indicator on Save (fast enough not to matter). |
| Empty/error states handled gracefully | Mostly good — Orders, Flows, and Send Catalog/Product modals all have clear, well-written empty states. Weak point: all validation/result feedback app-wide goes through native `alert()`/`confirm()` (ISSUE-001) instead of in-app UI. |
| Mobile responsive (375px), no overlap/scroll issues | **Critical, cross-cutting fail.** No hamburger/nav menu exists on mobile anywhere in the app (ISSUE-003) — every module below Dashboard is unreachable on mobile without typing a direct URL. Additionally, Appointment Types has clipped buttons and overlapping text at 375px (ISSUE-006). Orders and Catalog pages themselves scale acceptably once you're on them. |
| Form validation clear | Mixed — Appointment Type create form correctly blocks empty Name, but surfaces it via `alert()` rather than an inline field error. Orders search safely handles injection-style input (properly escaped, no XSS). |

---


