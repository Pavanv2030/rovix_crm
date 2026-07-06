### Prompt 14.3 — Bug Tracking & Post-Testing Procedures

```
Bug fix procedures and post-testing cleanup for Rovix AI Leads Tool.

COMMON BUGS & FIXES:

## 1. Webhook Not Processing Messages

Symptoms:
- Messages not appearing in inbox
- No conversations created
- Webhook logs show errors

Debug steps:
1. Check webhook_logs table for errors
2. Verify webhook signature in Meta Business Manager
3. Check WhatsApp config (phone_number_id, access_token)
4. Test webhook with curl:

curl -X POST http://localhost:8080/webhook/whatsapp \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: sha256=SIGNATURE" \
  -d '{"object":"whatsapp_business_account","entry":[...]}'

5. Check error logs: writable/logs/log-YYYY-MM-DD.php
6. Verify job queue running: php spark queue:process

Fix:
- Update webhook verify token in settings
- Re-encrypt access token if needed
- Restart queue processor
- Check firewall/hosting allows POST to /webhook

## 2. Broadcast Stuck at "Processing"

Symptoms:
- Broadcast status never changes to "completed"
- Recipients stuck at "pending"
- Queue not progressing

Debug steps:
1. Check job queue:
   SELECT * FROM jobs WHERE job_type = 'process_broadcast' ORDER BY created_at DESC LIMIT 10;

2. Check locked jobs:
   SELECT * FROM jobs WHERE locked_until > NOW();

3. Check broadcast_recipients:
   SELECT status, COUNT(*) FROM broadcast_recipients WHERE broadcast_id = X GROUP BY status;

4. Check rate limiting in BroadcastProcessor

Fix:
- Unlock stuck jobs: UPDATE jobs SET locked_until = NULL WHERE id = 'X';
- Restart queue processor
- Check Meta API rate limits (80 msg/sec)
- Verify network connectivity to Meta API
- Re-run: php spark broadcasts:process

## 3. Contact Import Timeout

Symptoms:
- Import fails halfway
- PHP timeout error
- Memory exhausted

Debug steps:
1. Check file size
2. Check PHP limits: php -i | grep -E "max_execution_time|memory_limit"
3. Check import batch size (should be 500 rows/batch)

Fix:
- Increase PHP limits in .env:
  php.max_execution_time = 300
  php.memory_limit = 512M

- Split large CSV into smaller files
- Use CLI import: php spark contacts:import file.csv

## 4. Flow Not Triggering

Symptoms:
- Keyword sent but flow doesn't start
- No flow_run created
- Message ignored

Debug steps:
1. Check flow is active:
   SELECT * FROM flows WHERE is_active = 1;

2. Check trigger keywords match:
   SELECT trigger_keywords FROM flows WHERE id = X;

3. Check existing flow_run:
   SELECT * FROM flow_runs WHERE contact_id = Y AND status = 'active';

4. Check queue processing flow checks

Fix:
- Verify flow is active (toggle in UI)
- Check keyword exact match (case-insensitive)
- End stale flow_run: php spark flows:cleanup-stale
- Restart queue processor

## 5. Session Timeout Issues

Symptoms:
- Logged out unexpectedly
- "Please login" after short time
- Session data lost

Debug steps:
1. Check session config in app/Config/App.php
2. Check ci_sessions table
3. Check session cookie expiry

Fix:
- Increase session timeout:
  public $sessionExpiration = 7200; // 2 hours

- Check session save path writable
- Clear old sessions: php spark sessions:gc

## 6. Template Approval Status Not Syncing

Symptoms:
- Template shows "pending" but approved in Meta
- Cannot send template messages

Debug steps:
1. Check template status in Meta Business Manager
2. Check templates table: SELECT name, status FROM templates;
3. Check webhook logs for template_status updates

Fix:
- Manually trigger sync: php spark templates:sync
- Wait for webhook from Meta (can take 1 hour)
- Re-submit template in Meta if rejected

## 7. Deal Not Moving in Kanban

Symptoms:
- Drag-drop not working
- Deal stays in old stage
- JavaScript error in console

Debug steps:
1. Check browser console for JS errors
2. Check SortableJS loaded
3. Check API endpoint returning success

Fix:
- Clear browser cache
- Check SortableJS CDN URL
- Verify AJAX endpoint: POST /deals/{id}/move-stage
- Check deal.stage_id updated in database

## 8. Email Notifications Not Sending

Symptoms:
- No invitation emails received
- No notification emails

Debug steps:
1. Check CodeIgniter email config: app/Config/Email.php
2. Test email manually:

php spark shell
$email = \Config\Services::email();
$email->setTo('test@example.com');
$email->setSubject('Test');
$email->setMessage('Test message');
$email->send();
var_dump($email->printDebugger());

3. Check SMTP credentials
4. Check spam folder

Fix:
- Configure SMTP in .env:
  email.protocol = smtp
  email.SMTPHost = smtp.gmail.com
  email.SMTPPort = 587
  email.SMTPUser = your@email.com
  email.SMTPPass = yourpassword

- Use app password for Gmail
- Verify SMTP server allows connections

---

POST-TESTING CLEANUP:

## Remove Test Data

# Delete test contacts
DELETE FROM contacts WHERE email LIKE '%test.com' OR name LIKE 'Test%';

# Delete test conversations
DELETE FROM conversations WHERE id IN (SELECT DISTINCT conversation_id FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY) AND content_text LIKE 'Test%');

# Delete test broadcasts
DELETE FROM broadcasts WHERE name LIKE 'Test%';

# Delete test flows
DELETE FROM flows WHERE name LIKE 'Test%';

# Delete old webhook logs
php spark webhooks:cleanup

# Clear failed jobs
DELETE FROM jobs WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAYS);

## Database Optimization

# Optimize tables
OPTIMIZE TABLE messages;
OPTIMIZE TABLE conversations;
OPTIMIZE TABLE contacts;
OPTIMIZE TABLE jobs;

# Analyze query performance
EXPLAIN SELECT * FROM messages WHERE conversation_id = 'X' ORDER BY created_at DESC;

# Add missing indexes if needed
CREATE INDEX idx_messages_conversation_created ON messages(conversation_id, created_at);

## Log Rotation

# Clear old logs (keep last 30 days)
find writable/logs -name "log-*.php" -mtime +30 -delete

# Archive old logs
tar -czf logs-archive-$(date +%Y%m%d).tar.gz writable/logs/*.php
mv logs-archive-*.tar.gz backups/

## Security Hardening for Production

# Disable debug mode
# In .env:
CI_ENVIRONMENT = production

# Remove test accounts
DELETE FROM users WHERE email LIKE '%@test.com';

# Regenerate all API keys (if compromised)
UPDATE accounts SET api_key = NULL;
# Keys will auto-regenerate on next access

# Clear all sessions (force re-login)
TRUNCATE TABLE ci_sessions;

---

FINAL PRE-DEPLOYMENT CHECKLIST:

□ All unit tests pass (100%)
□ All integration tests pass (100%)
□ Manual testing checklist complete
□ Security audit complete (no critical issues)
□ Performance benchmarks met
□ Browser compatibility verified
□ Mobile responsive verified
□ Error handling tested
□ Backup procedures tested
□ Documentation complete
□ .env.example updated
□ Database migrations tested (up and down)
□ Seed data for demo created
□ Logging configured properly
□ CORS configured (if needed)
□ SSL certificate ready (for production)
□ Cron jobs documented
□ Monitoring/alerting setup (optional)
□ Team trained on system
□ Support documentation ready

---

MONITORING & ALERTING (Optional for Production)

## Key Metrics to Monitor

1. Webhook processing time (should be < 100ms)
2. Job queue backlog (should be < 100)
3. Failed jobs count (should be < 5% of total)
4. Database query time (should be < 50ms)
5. Disk space usage
6. Memory usage
7. Error rate (4xx/5xx responses)
8. Uptime (should be > 99.9%)

## Alerting Rules

- Alert if webhook processing > 500ms for 5 minutes
- Alert if job queue > 1000 pending jobs
- Alert if failed jobs > 10% of total
- Alert if disk space < 10% free
- Alert if memory usage > 90%
- Alert if error rate > 5%
- Alert if uptime < 99%

## Logging Best Practices

□ Log all webhook events
□ Log all API requests
□ Log all authentication attempts
□ Log all errors with stack traces
□ Log all job failures
□ Do NOT log sensitive data (passwords, tokens)
□ Rotate logs daily
□ Archive logs monthly
□ Delete logs > 90 days

---

DISASTER RECOVERY PROCEDURES:

## Database Backup

# Daily backup
mysqldump -u root -p rovix_crm > backup-$(date +%Y%m%d).sql

# Restore from backup
mysql -u root -p rovix_crm < backup-20240101.sql

## File Backup

# Backup uploaded media
tar -czf media-backup-$(date +%Y%m%d).tar.gz public/uploads/

# Restore media
tar -xzf media-backup-20240101.tar.gz -C public/uploads/

## Full System Backup

# Backup entire application
tar -czf rovix-full-backup-$(date +%Y%m%d).tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='writable/logs' \
    /path/to/rovix-ai-leads-tool

## Recovery Steps

1. Restore database from latest backup
2. Restore media files
3. Restore application code
4. Run migrations: php spark migrate
5. Clear caches: php spark cache:clear
6. Restart services (PHP-FPM, queue processor)
7. Verify critical flows work
8. Monitor for errors

---

**Phase 14 Complete!**

Testing & QA includes:
- Unit tests for core libraries
- Integration tests for webhook flow
- Security audit checklist (10 categories)
- Performance testing procedures
- Load testing script
- Bug tracking & common fixes
- Post-testing cleanup procedures
- Pre-deployment checklist
- Monitoring & alerting guidelines
- Disaster recovery procedures

**Pass Criteria Met:**
- Security vulnerabilities: 0 critical
- Performance benchmarks: All met
- Test coverage: Core libraries
- Bug fixes: 8 common issues documented
- Cleanup procedures: Complete
- Deployment readiness: Verified

