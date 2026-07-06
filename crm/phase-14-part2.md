### Prompt 14.2 — Security Audit & Performance Testing

```
Comprehensive security audit checklist and performance testing for Rovix AI Leads Tool.

SECURITY AUDIT CHECKLIST:

## 1. Authentication & Authorization

□ Password hashing uses PASSWORD_DEFAULT (bcrypt/argon2)
□ Session regeneration after login
□ Session timeout implemented (30 min idle)
□ CSRF protection enabled on all forms
□ AuthFilter blocks unauthenticated access
□ RoleFilter enforces role-based permissions
□ No hardcoded credentials in code
□ .env file in .gitignore
□ Database credentials not committed

Test commands:
# Check password hashing
grep -r "password_hash" app/Controllers/AuthController.php

# Check session security
grep -r "session()->regenerate" app/Controllers/AuthController.php

# Check CSRF protection
grep -r "csrf_field" app/Views/

## 2. SQL Injection Prevention

□ All queries use query builder or prepared statements
□ No raw SQL with user input concatenation
□ Input validation on all POST/GET parameters
□ BaseModel auto-scopes by account_id

Test:
# Check for dangerous SQL patterns
grep -rn "query(" app/ | grep -v "->query\(" | grep "\$"
grep -rn "db->query" app/ | grep "\".*\$"

## 3. XSS Prevention

□ All output uses esc() helper
□ No <?= without esc() on user data
□ JSON responses use setJSON()
□ No eval() or similar dynamic execution

Test:
# Check for unescaped output
grep -rn "<?=" app/Views/ | grep -v "esc("

## 4. Encryption & Data Security

□ Access tokens encrypted with AES-256-GCM
□ Encryption key in .env (not committed)
□ Webhook signatures verified (HMAC SHA-256)
□ API keys stored securely
□ No sensitive data in logs

Test:
# Check encryption usage
grep -r "access_token" app/Models/WhatsAppConfigModel.php

# Check .env is ignored
cat .gitignore | grep ".env"

## 5. File Upload Security

□ File type validation (MIME check)
□ File size limits enforced
□ Uploaded files stored outside webroot or with random names
□ No execution of uploaded files

Test uploads:
# Try uploading PHP file as image
curl -F "file=@test.php" http://localhost:8080/inbox/upload

# Try uploading oversized file (>10MB)

## 6. Rate Limiting & DoS Prevention

□ Webhook processing has timeout
□ Job queue prevents infinite loops
□ Rate limiting on API endpoints (optional)
□ Max file upload size configured
□ Database query limits (LIMIT clause)

Test:
# Check job timeout
grep -r "timeout" app/Commands/ProcessQueue.php

# Check pagination limits
grep -rn "->paginate" app/Controllers/

## 7. Information Disclosure

□ Error messages don't reveal system details
□ Debug mode OFF in production (.env)
□ Database errors caught and logged
□ Stack traces not shown to users
□ Version headers removed

Test:
# Check error handling
grep -r "CI_ENVIRONMENT" .env
grep -r "catch" app/Controllers/ | head -20

## 8. Tenant Isolation

□ All models use BaseModel with account_id scoping
□ No cross-account data leaks
□ Session stores account_id
□ All queries filtered by account_id

Test:
# Login as Account A, try to access Account B's data
# Check if unauthorized access blocked

## 9. API Security

□ API key required for API routes
□ API key transmitted via header (not URL)
□ API rate limiting (optional)
□ API returns proper error codes

Test:
curl http://localhost:8080/api/v1/contacts
# Should return 401 Unauthorized

curl -H "Authorization: Bearer VALID_KEY" \
     http://localhost:8080/api/v1/contacts
# Should return 200 OK

## 10. Third-Party Dependencies

□ Composer dependencies up to date
□ No known vulnerabilities in packages
□ CDN resources use SRI (optional)

Test:
composer audit
composer outdated

---

PERFORMANCE TESTING:

## 1. Database Query Optimization

□ Indexes on frequently queried columns
□ No N+1 query problems
□ Joins used instead of multiple queries
□ Pagination on large datasets

Test:
# Enable query logging in development
# Check slow query log

## 2. Response Time Testing

Test critical endpoints:

# Dashboard load time
time curl http://localhost:8080/dashboard

# Should be < 1 second

# Inbox with 1000 conversations
# Should be < 2 seconds

# Contact import (10k rows)
# Should complete in < 30 seconds

## 3. Concurrent User Testing

Use Apache Bench:

# 100 concurrent requests to dashboard
ab -n 1000 -c 100 http://localhost:8080/dashboard

# Check for errors
# Average response time should be < 2s

## 4. Memory Usage

# Profile memory usage
php -d memory_limit=512M spark queue:process

# Monitor memory consumption
# Should not exceed 256MB per process

## 5. Webhook Processing Speed

# Measure webhook processing time
# Check webhook_logs.processing_time_ms

# Average should be < 100ms
# 95th percentile < 500ms

## 6. Broadcast Performance

# Test broadcast to 10,000 recipients
# Should process at 70 msg/sec
# Total time: ~2.4 minutes

# Monitor job queue during broadcast
watch -n 1 "php spark queue:status"

## 7. Large Dataset Handling

□ Contacts table with 100k records
□ Messages table with 1M records
□ Dashboard still loads in < 2s
□ Search/filter still works

Test:
# Seed large dataset
php spark db:seed LargeDataSeeder

# Test queries
time php spark test:query-contacts

## 8. File Upload Performance

# Test 10MB media upload
time curl -F "media=@test-10mb.jpg" \
     http://localhost:8080/inbox/upload

# Should complete in < 5 seconds

---

MANUAL TESTING CHECKLIST:

## Critical Flows

□ Signup → Login → Configure WhatsApp
□ Receive inbound message → Reply
□ Create contact → Send message
□ Create broadcast → Send to 100 recipients
□ Create automation → Trigger automation
□ Create flow → Trigger via keyword
□ Invite team member → Accept invitation
□ Change settings → Verify applied

## Browser Compatibility

□ Chrome (latest)
□ Firefox (latest)
□ Safari (latest)
□ Edge (latest)
□ Mobile Safari (iOS)
□ Chrome Mobile (Android)

## Responsive Design

□ Works on mobile (320px width)
□ Works on tablet (768px width)
□ Works on desktop (1920px width)
□ No horizontal scroll
□ Touch targets large enough (44px min)

## Edge Cases

□ Empty states (no data)
□ Error states (network failure)
□ Loading states (long operations)
□ Invalid input (form validation)
□ Expired sessions (auto-redirect)
□ Missing permissions (access denied)

---

ACCESSIBILITY TESTING:

□ All images have alt text
□ Form inputs have labels
□ Color contrast ratio ≥ 4.5:1
□ Keyboard navigation works
□ Screen reader compatible
□ Focus indicators visible

Test with:
# Chrome DevTools Lighthouse
# Audit → Accessibility

---

ERROR HANDLING TESTING:

## Test Scenarios

1. Database connection fails
   - Should show error page, not expose details

2. WhatsApp API returns 429 (rate limit)
   - Should retry with backoff

3. Invalid webhook signature
   - Should reject with 403

4. Malformed JSON in webhook
   - Should log error, return 400

5. File upload fails (disk full)
   - Should show error message

6. Email sending fails
   - Should log error, don't block flow

7. Job processing timeout
   - Should mark job as failed, move to DLQ

8. Automation condition error
   - Should skip, log error, continue

---

LOAD TESTING SCRIPT:

Create tests/LoadTest.php:

<?php
// Simulate 100 concurrent users sending messages

$urls = [
    'http://localhost:8080/dashboard',
    'http://localhost:8080/inbox',
    'http://localhost:8080/contacts',
    'http://localhost:8080/broadcasts'
];

$results = [];

foreach ($urls as $url) {
    $start = microtime(true);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, 'ci_session=YOUR_SESSION_COOKIE');
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $time = (microtime(true) - $start) * 1000; // ms
    
    $results[] = [
        'url' => $url,
        'status' => $status,
        'time_ms' => $time
    ];
}

echo "Load Test Results:\n";
foreach ($results as $result) {
    printf("%s: %d (%dms)\n", $result['url'], $result['status'], $result['time_ms']);
}
```

### Testing Pass Criteria Summary

**Security:**
- ✅ No SQL injection vulnerabilities
- ✅ No XSS vulnerabilities
- ✅ CSRF protection enabled
- ✅ Password hashing secure
- ✅ Access tokens encrypted
- ✅ Webhook signatures verified
- ✅ Tenant isolation enforced
- ✅ No sensitive data exposure
- ✅ Session security implemented
- ✅ File upload security in place

**Performance:**
- ✅ Dashboard loads < 2 seconds
- ✅ Webhook processing < 100ms average
- ✅ Broadcast rate 70 msg/sec
- ✅ Contact import 10k rows < 30 seconds
- ✅ No N+1 query problems
- ✅ Memory usage < 256MB per process
- ✅ Handles 100k contacts smoothly
- ✅ Handles 1M messages smoothly
- ✅ Concurrent users supported (100+)
- ✅ No memory leaks in long-running processes

**Functionality:**
- ✅ All unit tests pass
- ✅ All integration tests pass
- ✅ Manual test checklist 100% pass
- ✅ Critical flows work end-to-end
- ✅ Error handling graceful
- ✅ Edge cases handled
- ✅ Browser compatibility verified
- ✅ Mobile responsive
- ✅ Accessibility compliant
- ✅ Team permissions enforced

Continue with Part 3 (Bug Fix Procedures)?
