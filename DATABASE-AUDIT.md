# MySQL Database Audit Report — RovixAI CRM

**Date:** 2026-07-06  
**Auditor:** Claude (Opus 4.8)  
**Database:** rovix_crm

---

## Summary

✅ **Overall Health:** Good  
🟡 **Issues Found:** 4 (3 data integrity, 1 optimization)  
✅ **Schema:** Consistent, well-indexed  
✅ **Character Set:** UTF8MB4 across all tables  
✅ **Foreign Keys:** Properly defined

---

## Critical Issues (Fix Now)

### 1. **ORPHANED CONVERSATIONS** 🔴
**Impact:** Data integrity violation, breaks foreign key logic

**Details:**
- 2 conversations with NULL `contact_id`
- IDs: `0f9f30d7-2d46-4a65-a387-97c22af6937b`, `da1e1dca-cc48-44fc-b698-31f885df6595`
- Account: `52a7341a-812f-4ba4-8b1c-6fdd74a6fb05`
- Created: June 30, 2026

**Impact:**
- Conversations can't be displayed (no contact to show)
- Breaks inbox queries that JOIN contacts
- Foreign key constraint should have prevented this

**Root Cause:**
- `contact_id` column is nullable (should be NOT NULL)
- No foreign key cascade rule enforced
- Likely created before contact was set

**Fix:**
```sql
-- Option A: Delete orphaned conversations (recommended if no messages)
DELETE FROM conversations 
WHERE contact_id IS NULL;

-- Option B: If they have messages, investigate and manually link to correct contact
SELECT c.id, COUNT(m.id) as message_count
FROM conversations c
LEFT JOIN messages m ON m.conversation_id = c.id
WHERE c.contact_id IS NULL
GROUP BY c.id;

-- Then create migration to prevent future occurrences:
-- ALTER TABLE conversations MODIFY contact_id CHAR(36) NOT NULL;
```

---

### 2. **ORPHANED DEAL** 🔴
**Impact:** Data integrity violation

**Details:**
- 1 deal with NULL `contact_id`
- ID: `f4791939-bb2a-4ed7-9e8b-d0fd8a5847a2`
- Title: "jrn"
- Created: June 22, 2026

**Fix:**
```sql
-- Check if deal has any activity
SELECT d.*, COUNT(al.id) as activity_count
FROM deals d
LEFT JOIN activity_logs al ON al.entity_id = d.id AND al.entity_type = 'deal'
WHERE d.contact_id IS NULL
GROUP BY d.id;

-- If no activity, delete
DELETE FROM deals WHERE contact_id IS NULL;

-- If has activity, manually link to correct contact or mark as closed-lost
```

---

### 3. **PENDING JOBS STUCK** 🟡
**Impact:** Background tasks not processing

**Details:**
- 2 `check_flow` jobs stuck in `pending` status
- 2 `run_automation` jobs stuck in `pending` status
- No `created_at` timestamp (migration issue)

**Root Cause:**
- Job worker not running OR
- Jobs created before `created_at` column added

**Fix:**
```sql
-- Check if jobs are genuinely stuck
SELECT id, job_type, status, payload, created_at, updated_at, attempts
FROM job_queue 
WHERE status = 'pending'
ORDER BY id;

-- If stuck, mark as failed (they're likely stale test data)
UPDATE job_queue SET status = 'failed', error_message = 'Stuck job - manually failed during audit' WHERE status = 'pending';

-- Ensure job worker is running
php spark queue:work
```

---

## Medium Priority Issues

### 4. **NO CREATED_AT ON OLD JOBS** 🟡
**Impact:** Can't track job age, hard to debug

**Details:**
- `job_queue` table has jobs with NULL `created_at`
- Likely created before migration added the column

**Fix:**
```sql
-- Backfill created_at for old jobs (use id as proxy for time)
UPDATE job_queue 
SET created_at = NOW() - INTERVAL (SELECT MAX(id) - id FROM job_queue) SECOND
WHERE created_at IS NULL;
```

---

## Recommendations (Low Priority)

### 5. **WEBHOOK LOGS RETENTION POLICY**
**Current State:** 432 webhook logs dating back to June 30 (7 days)

**Recommendation:**
- Implement automatic cleanup for logs older than 30 days
- Keep only failed logs longer (90 days for debugging)

**Implementation:**
```sql
-- Create cleanup command: app/Commands/CleanupWebhookLogs.php

-- Add to cron (daily at 2 AM):
-- php spark webhooks:cleanup

-- SQL for cleanup:
DELETE FROM webhook_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
AND status = 'success';

DELETE FROM webhook_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
AND status = 'failed';
```

---

### 6. **STALE SESSION CLEANUP**
**Current State:** No stale sessions (good)

**Recommendation:**
- Ensure CI's session GC is configured properly
- Consider reducing session lifetime from default (7200s = 2 hours)

**Config Check:**
```php
// app/Config/Session.php
public int $expiration = 7200; // 2 hours - OK
```

---

### 7. **ADD MISSING INDEXES**
**Current State:** Major tables well-indexed

**Recommendation (if performance becomes an issue):**
```sql
-- Index for filtering broadcasts by status
CREATE INDEX idx_broadcasts_status ON broadcasts(account_id, status, created_at);

-- Index for filtering deals by stage
CREATE INDEX idx_deals_stage_status ON deals(account_id, stage_id, status);

-- Index for searching contacts by name
CREATE INDEX idx_contacts_name ON contacts(account_id, name);

-- Index for flow runs by status
CREATE INDEX idx_flow_runs_status ON flow_runs(contact_id, status, created_at);
```

---

## Schema Health Report

### ✅ Good Practices Found

1. **UUID Primary Keys** — All tables use CHAR(36) UUIDs (good for distributed systems)
2. **Consistent Collation** — All tables use `utf8mb4_unicode_ci` (supports emojis)
3. **Foreign Keys** — 37 foreign key constraints properly defined
4. **Unique Constraints** — Proper uniqueness on:
   - `profiles.email`
   - `contacts.account_id + phone_normalized`
   - `tags.account_id + name`
   - `message_templates.account_id + name + language`

5. **Composite Indexes** — Smart indexing on:
   - `conversations.account_id + status`
   - `conversations.account_id + last_message_at`
   - `messages.conversation_id + created_at`

6. **InnoDB Storage Engine** — All tables use InnoDB (ACID compliance, foreign keys)

---

### 🟡 Schema Improvements (Optional)

1. **Add `created_at` defaults:**
```sql
-- Many tables have created_at TIMESTAMP NULL
-- Should be: TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
```

2. **Enforce NOT NULL on foreign keys:**
```sql
-- conversations.contact_id should be NOT NULL
-- deals.contact_id should be NOT NULL  
-- (after cleaning orphaned records)
```

3. **Add soft deletes:**
```sql
-- Add deleted_at to critical tables (contacts, conversations, deals)
-- Allows recovery instead of hard deletion
ALTER TABLE contacts ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE conversations ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE deals ADD COLUMN deleted_at TIMESTAMP NULL;
```

---

## Performance Metrics

**Total Database Size:** 1.71 MB (very small, good)

**Largest Tables:**
1. `webhook_logs` — 0.39 MB (432 rows)
2. `messages` — 0.23 MB (205 rows)
3. `job_queue` — 0.09 MB (73 rows)

**Query Performance:** Indexes well-optimized for current load. No slow queries expected.

---

## Data Integrity Summary

| Check | Status | Count |
|-------|--------|-------|
| Orphaned contacts | ✅ | 0 |
| Orphaned conversations | 🔴 | 2 |
| Orphaned messages | ✅ | 0 |
| Orphaned deals | 🔴 | 1 |
| Orphaned broadcast recipients | ✅ | 0 |
| Profiles without account | ✅ | 0 |
| Duplicate emails | ✅ | 0 |
| Missing foreign key indexes | ✅ | 0 |

---

## SQL Fix Script

Run this to fix all critical issues:

```sql
-- Fix orphaned conversations
DELETE FROM conversations WHERE contact_id IS NULL;

-- Fix orphaned deals  
DELETE FROM deals WHERE contact_id IS NULL;

-- Fix stuck jobs
UPDATE job_queue 
SET status = 'failed', 
    error_message = 'Stuck job - cleaned during maintenance',
    updated_at = NOW()
WHERE status = 'pending' AND created_at IS NULL;

-- Backfill created_at for old jobs
UPDATE job_queue 
SET created_at = NOW() - INTERVAL (
    (SELECT MAX(id) FROM job_queue) - id
) SECOND
WHERE created_at IS NULL;

-- Verify fixes
SELECT 'Orphaned conversations' AS check_name, COUNT(*) FROM conversations WHERE contact_id IS NULL
UNION ALL
SELECT 'Orphaned deals', COUNT(*) FROM deals WHERE contact_id IS NULL
UNION ALL
SELECT 'Stuck pending jobs', COUNT(*) FROM job_queue WHERE status = 'pending';
```

---

## Migration Recommendations

Create these migrations to prevent future issues:

### Migration: Make contact_id NOT NULL

```php
// app/Database/Migrations/2026-07-06-000001_MakeContactIdNotNull.php
public function up()
{
    // Conversations
    $this->db->query("ALTER TABLE conversations MODIFY contact_id CHAR(36) NOT NULL");
    
    // Deals
    $this->db->query("ALTER TABLE deals MODIFY contact_id CHAR(36) NOT NULL");
}

public function down()
{
    $this->db->query("ALTER TABLE conversations MODIFY contact_id CHAR(36) NULL");
    $this->db->query("ALTER TABLE deals MODIFY contact_id CHAR(36) NULL");
}
```

### Migration: Add Soft Deletes

```php
// app/Database/Migrations/2026-07-06-000002_AddSoftDeletes.php
public function up()
{
    $this->db->query("ALTER TABLE contacts ADD COLUMN deleted_at TIMESTAMP NULL");
    $this->db->query("ALTER TABLE conversations ADD COLUMN deleted_at TIMESTAMP NULL");
    $this->db->query("ALTER TABLE deals ADD COLUMN deleted_at TIMESTAMP NULL");
}
```

---

## Maintenance Checklist

Weekly:
- [ ] Check for stuck jobs: `SELECT * FROM job_queue WHERE status = 'pending' AND created_at < NOW() - INTERVAL 1 HOUR;`
- [ ] Monitor failed webhooks: `SELECT COUNT(*) FROM webhook_logs WHERE status = 'failed' AND created_at > NOW() - INTERVAL 7 DAY;`

Monthly:
- [ ] Clean old webhook logs (30+ days, successful only)
- [ ] Review orphaned records
- [ ] Check table sizes: `SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rovix_crm';`
- [ ] Optimize tables: `OPTIMIZE TABLE messages, webhook_logs, conversations;`

Quarterly:
- [ ] Review and add indexes based on slow query log
- [ ] Archive old data (messages/logs older than 1 year)
- [ ] Full database backup and restore test

---

## Backup & Recovery

**Current State:** Unknown (no backup evidence found)

**Critical Recommendations:**
1. **Enable automated daily backups:**
```bash
# MySQL dump with compression
mysqldump -u root -p rovix_crm --single-transaction --quick --lock-tables=false | gzip > rovix_crm_$(date +%Y%m%d).sql.gz

# Cron: daily at 3 AM
0 3 * * * /path/to/backup-script.sh
```

2. **Test restore monthly** — a backup you can't restore is useless

3. **Backup encryption key** — stored in `.env`, without it encrypted `access_token` in `whatsapp_config` is unrecoverable

4. **Enable binary logging** (for point-in-time recovery):
```ini
# my.cnf / my.ini
[mysqld]
log-bin=mysql-bin
expire_logs_days=7
```

---

## Production Readiness

✅ Schema design  
✅ Indexes  
✅ Foreign keys  
✅ Character encoding  
🔴 Fix orphaned records (2 conversations, 1 deal)  
🟡 Fix stuck jobs  
🟡 Set up automated backups  
🟡 Implement log cleanup  
🟡 Add NOT NULL constraints after cleanup

**Estimated fix time:** 30 minutes

---

**END OF AUDIT**
