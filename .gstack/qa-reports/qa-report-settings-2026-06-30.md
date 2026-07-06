# QA Report — Settings Module
**Date:** 2026-06-30  
**URL:** http://localhost:8000/rovix-crm/public/settings  
**Tester:** gstack /qa-only  
**Scope:** Settings page — all 5 tabs (Account, WhatsApp, Notifications, API Keys, Webhooks)  
**Mode:** Full  
**Pages visited:** 6  
**Screenshots:** 11  

---

## Health Score: 81/100

| Category | Score | Weight | Contribution |
|----------|-------|--------|-------------|
| Console | 85 | 15% | 12.75 |
| Links | 100 | 10% | 10.00 |
| Visual | 90 | 10% | 9.00 |
| Functional | 70 | 20% | 14.00 |
| UX | 72 | 15% | 10.80 |
| Performance | 80 | 10% | 8.00 |
| Content | 90 | 5% | 4.50 |
| Accessibility | 78 | 15% | 11.70 |
| **Total** | | | **80.75 → 81** |

---

## Summary

Settings module is largely functional. All 5 tabs load, form saves work, and feedback messages display correctly. Two issues need attention before shipping: a destructive API key regeneration with no confirmation guard (HIGH), and the Webhooks tab disappearing off-screen on mobile (MEDIUM).

---

## Top 3 Things to Fix

1. **[HIGH] API key Regenerate has no confirmation** — one click permanently invalidates the current key with no undo and no "Are you sure?" prompt.
2. **[MEDIUM] Webhooks tab cut off on mobile** — on 375px viewport the tab bar overflows and the Webhooks tab is unreachable.
3. **[LOW] API key shown in plain text** — the key is fully visible in a standard text input; should be masked with an optional reveal toggle.

---

## Issues

### ISSUE-001 — No confirmation before API key regeneration [HIGH]

**What:** Clicking "Regenerate" on the API Keys tab immediately generates a new key and invalidates the old one. No confirmation dialog, no warning modal, no undo.

**Impact:** An accidental click destroys a live API key. Any integrations using the old key break silently.

**Repro:**
1. Go to Settings → API Keys
2. Click "Regenerate"
3. Old key `823bd6...` is immediately replaced — no confirmation asked

**Evidence:** Screenshot `09-apikey-regenerate.png` — key changed from `823bd65b...` to `7cf9452e...` in one click.

**Fix:** Add a `window.confirm()` or a modal: "Regenerating will immediately invalidate your current API key. Any apps using it will stop working. Continue?"

---

### ISSUE-002 — Webhooks tab cut off on mobile [MEDIUM]

**What:** On a 375×812 viewport (iPhone SE size), the Settings tab bar only shows "Account", "WhatsApp", "Notifications", "API Keys". The "Webhooks" tab is clipped outside the visible area with no scroll affordance.

**Impact:** Mobile users cannot access the Webhooks tab at all.

**Repro:**
1. Open Settings on a 375px screen
2. Tab row shows 4 tabs — Webhooks is absent, no horizontal scroll indicator

**Evidence:** Screenshot `11-settings-mobile.png` — Webhooks tab not visible.

**Fix:** Add `overflow-x: auto` and `white-space: nowrap` to the tab nav container, or collapse to a `<select>` dropdown on mobile.

---

### ISSUE-003 — API key displayed in plain text [LOW]

**What:** The API key field (`<input type="text">`) shows the full 64-character key in cleartext.

**Impact:** Shoulder-surfing risk. Anyone looking at the screen while the user is in Settings can read the key.

**Repro:** Settings → API Keys — key `7cf9452ea3eaa85cb9e2040e6ed827c00d31a2f1f0d20ce998facb9895339773` visible.

**Evidence:** Screenshot `07-settings-apikeys.png`.

**Fix:** Use `type="password"` or mask as `••••••••••••••••••••••••••••••••...bc570` with a "Show" toggle button.

---

### ISSUE-004 — No success feedback after API key regeneration [LOW]

**What:** After clicking "Regenerate", the key changes silently. No success toast, no "Key regenerated" message. Compare with Account save ("Settings saved." in green) and Notifications save ("Preferences saved.") — those have feedback; Regenerate does not.

**Impact:** User cannot tell if regeneration succeeded or failed.

**Fix:** Show a green inline message "API key regenerated." after a successful regeneration, consistent with other tabs.

---

### ISSUE-005 — Tailwind CSS loaded from CDN [LOW / Pre-production]

**What:** Console shows `cdn.tailwindcss.com should not be used in production` on every page load.

**Impact:** Not a user-facing bug in development. In production: slower page loads (CDN adds ~300ms), potential CORS/CSP issues, Tailwind purging won't work so CSS bundle will be ~3MB instead of ~10KB.

**Fix:** Before deploying, compile Tailwind with `npx tailwindcss -o public/css/tailwind.min.css --minify` and replace the CDN `<script>` tag with a `<link>` to the compiled file.

---

## Passed Checks

| Test | Result |
|------|--------|
| Account tab loads | ✅ PASS |
| Account Save Changes | ✅ PASS — "Settings saved." shown |
| WhatsApp tab loads with all fields | ✅ PASS |
| Webhook URL shown correctly | ✅ PASS |
| Notifications tab loads with checkboxes | ✅ PASS |
| Notifications Save Preferences | ✅ PASS — "Preferences saved." shown |
| API Keys tab loads with key | ✅ PASS |
| API key Copy button present | ✅ PASS |
| Webhooks tab loads | ✅ PASS |
| Webhooks empty state | ✅ PASS — "No webhook events logged yet." |
| No JS errors in console | ✅ PASS |
| Desktop layout (1280px) | ✅ PASS |
| Tab navigation routing | ✅ PASS — each tab routes to correct URL |

---

## Console Summary

**Errors:** 0  
**Warnings:** 9 (all identical: Tailwind CDN warning — expected in development)  
**Console health:** Clean — no functional errors.

---

## Screenshots

| File | Description |
|------|-------------|
| `01-login.png` | Login page |
| `02-settings-general.png` | Account tab initial state |
| `03-account-save.png` | Account saved — green feedback |
| `04-settings-whatsapp.png` | WhatsApp tab |
| `05-settings-notifications.png` | Notifications tab |
| `06-notifications-save.png` | Notifications saved feedback |
| `07-settings-apikeys.png` | API Keys tab |
| `08-settings-webhooks.png` | Webhooks empty state |
| `09-apikey-regenerate.png` | After Regenerate — key changed silently |
| `10-account-empty-name.png` | Account save (name unchanged) |
| `11-settings-mobile.png` | Mobile 375px — Webhooks tab cut off |
