# Flows & Automations Backend Audit

**Date:** 2026-07-06  
**Scope:** Backend security, data integrity, error handling

---

## Summary

✅ **Overall:** Good  
🔴 **Critical Issues:** 2  
🟡 **Medium Issues:** 3  
✅ **Low Issues:** 2

---

## Critical Issues

### 1. **SQL INJECTION in FlowEngine::execHttpRequest** 🔴

**File:** `app/Libraries/FlowEngine.php:863-930`

**Issue:** HTTP request node body fields use string interpolation before JSON encoding. Customer WhatsApp replies inserted into body_fields via `{{variable}}` interpolation can break JSON structure or inject fields.

**Current Code:**
```php
// Line 880-884
$bodyFields = [];
foreach ($config['body_fields'] ?? [] as $f) {
    if (!empty($f['key'])) {
        $bodyFields[$f['key']] = $this->interpolate($f['value'] ?? '', $vars);
    }
}
```

**Problem:** If `$f['value']` is `"{{user_input}}"` and user sends `" OR 1=1--`, the interpolated string isn't sanitized. While JSON encoding handles quotes, the interpolation happens BEFORE encoding, allowing injection if the template itself is malformed.

**Fix:** Already safe! The code builds `$bodyFields` as typed array, then passes to `json_encode()`. String interpolation doesn't break JSON structure because encoding happens after. Comment line 876 claims this prevents injection — **verified correct**.

**Status:** FALSE POSITIVE — code is secure. The architecture (typed array → json_encode) prevents injection. No fix needed.

---

### 2. **MISSING ACCOUNT SCOPE in FlowDataController::handle** 🔴

**File:** `app/Controllers/Api/FlowDataController.php:34`

**Issue:** Public webhook endpoint. Uses `->first()` to grab ANY WhatsApp config, not account-scoped.

```php
$waConfig = (new WhatsAppConfigModel())->first();
```

**Risk:** Multi-tenant collision. If 2 accounts exist, wrong account's flow keys used for decryption. Flow tokens leak across accounts.

**Fix:**
```php
// Extract account_id from flow_token_map FIRST, then scope the lookup
$db = \Config\Database::connect();
$flowMeta = $db->table('flow_token_map')
    ->where('flow_token', $flowToken ?? '')
    ->get()->getRowArray();

if (!$flowMeta) {
    return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid flow token']);
}

WhatsAppConfigModel::setBypassAccountScope(true);
$waConfig = (new WhatsAppConfigModel())
    ->where('account_id', $flowMeta['account_id'])
    ->first();
WhatsAppConfigModel::setBypassAccountScope(false);
```

**BUT:** flow_token not available until AFTER decryption (line 52). Chicken-egg problem.

**Real Fix:** Store `account_id` in envelope or use per-account encryption keys. For single-tenant (current state), acceptable. For multi-tenant, redesign needed.

**Status:** 🟡 MEDIUM (acceptable for single-tenant, blocker for multi-tenant)

---

## Medium Priority Issues

### 3. **MISSING INPUT VALIDATION on Flow Variable Interpolation** 🟡

**File:** `app/Libraries/FlowEngine.php:1046`

**Issue:** `interpolate()` directly substitutes user input into URLs, messages, API calls. No length limits, no sanitization.

```php
private function interpolate(string $text, array $vars): string
{
    foreach ($vars as $key => $value) {
        $text = str_replace('{{' . $key . '}}', (string)$value, $text);
    }
    return $text;
}
```

**Risk:** 
- User sends 10MB of text → OOM
- User sends malicious URL → SSRF via http_request node
- XSS if flow message echoed to admin dashboard (frontend issue, but backend enables it)

**Fix:**
```php
private function interpolate(string $text, array $vars): string
{
    foreach ($vars as $key => $value) {
        $safeValue = mb_strimwidth((string)$value, 0, 1000, '...');
        $text = str_replace('{{' . $key . '}}', $safeValue, $text);
    }
    return $text;
}
```

**Impact:** Medium (user can't DoS via long input, but no current exploit vector)

---

### 4. **NO RATE LIMITING on Flow Execution** 🟡

**File:** `app/Libraries/FlowEngine.php:36-124`

**Issue:** `dispatchInbound()` has no throttle. Customer can spam trigger keywords → flood API calls, burn OpenAI credits, hit WhatsApp rate limits.

**Example:** Flow has AI node + webhook. Customer sends trigger keyword 100x/sec → 100 OpenAI calls + 100 webhooks.

**Fix:** Add per-contact rate limit:

```php
// At start of dispatchInbound()
$cache = \Config\Services::cache();
$rateLimitKey = 'flow_rate_' . $contactId;
$attempts = (int)$cache->get($rateLimitKey) + 1;

if ($attempts > 10) { // 10 flows per 5 min per contact
    log_message('warning', "Flow rate limit hit for contact {$contactId}");
    return;
}
$cache->save($rateLimitKey, $attempts, 300);
```

**Impact:** Medium (only exploitable by malicious customer, not casual spam)

---

### 5. **INFINITE LOOP RISK in AutomationEngine::runChain** 🟡

**File:** `app/Libraries/AutomationEngine.php:134-169`

**Issue:** Recursive chain traversal has no max depth. Circular parent_step_id references → infinite loop → PHP timeout.

**Current Code:**
```php
private function runChain(array $byParent, string $parentKey, array &$context, array &$executed, array $automation): bool
{
    // No depth check!
    if (empty($byParent[$parentKey])) return true;
    
    foreach ($steps as $step) {
        // ...
        $continued = $this->runChain($byParent, $step['id'], $context, $executed, $automation);
    }
}
```

**Fix:**
```php
private function runChain(array $byParent, string $parentKey, array &$context, array &$executed, array $automation, int $depth = 0): bool
{
    if ($depth > 50) {
        log_message('error', "[AutomationEngine] Max depth exceeded, possible circular reference in automation {$automation['id']}");
        return false;
    }
    
    // ... rest of code
    $continued = $this->runChain($byParent, $step['id'], $context, $executed, $automation, $depth + 1);
}
```

**Impact:** Medium (requires malformed DB state, but could DoS server)

---

## Low Priority Issues

### 6. **VERBOSE ERROR MESSAGES Leak Internal State** 🟢

**File:** `app/Libraries/FlowEngine.php:324`

**Issue:** Exception messages logged with full stack trace. Attacker can trigger errors to map internal structure.

```php
log_message('error', "[FlowEngine] Node {$node['node_key']} error: " . $e->getMessage());
```

**Recommendation:** Log full trace internally, return generic message to user.

```php
log_message('error', "[FlowEngine] Node {$node['node_key']} error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
// Don't send exception details to customer via WhatsApp
```

**Impact:** Low (attacker needs to trigger errors, logs are server-side only)

---

### 7. **NO AUDIT TRAIL for Flow/Automation Changes** 🟢

**File:** `app/Controllers/FlowsController.php:125`, `app/Controllers/AutomationsController.php:153`

**Issue:** Update methods have no audit logging. Malicious admin can modify flows/automations, no evidence.

**Example:** Admin changes "send welcome" to "send phishing link", no trail.

**Fix:** Add to `activity_logs` table:

```php
// After update
(new \App\Models\ActivityLogModel())->insert([
    'account_id'  => session('account_id'),
    'user_id'     => session('user_id'),
    'entity_type' => 'flow',
    'entity_id'   => $flowId,
    'action'      => 'updated',
    'details'     => json_encode(['name' => $name, 'is_active' => $isActive]),
    'created_at'  => date('Y-m-d H:i:s'),
]);
```

**Impact:** Low (insider threat mitigation, not external attack)

---

## Security Strengths ✅

1. **Flow variable sync prevents SQL injection** — `syncVariableToContact()` uses query builder (line 1014)
2. **WhatsApp credentials properly encrypted** — `getWaCredentials()` decrypts access tokens (line 1030)
3. **Flow runs properly isolated** — each run has separate `vars` JSON blob
4. **Test mode skips external calls** — HTTP/AI nodes not executed in test console (line 587-597)
5. **Session window enforced** — catalog/product sends check 24h window (line 767, 805)

---

## Fixes Required

| Priority | Issue | File | Line | Fix |
|----------|-------|------|------|-----|
| 🔴 HIGH | Account scope in flow webhook | FlowDataController.php | 34 | Redesign for multi-tenant OR document single-tenant assumption |
| 🟡 MEDIUM | Rate limit flow execution | FlowEngine.php | 36 | Add per-contact throttle (10/5min) |
| 🟡 MEDIUM | Max depth in automation chain | AutomationEngine.php | 134 | Add depth param, max 50 |
| 🟡 MEDIUM | Interpolation length limit | FlowEngine.php | 1046 | Cap at 1000 chars |
| 🟢 LOW | Audit trail for changes | FlowsController.php | 125 | Log to activity_logs |

---

## Production Readiness

✅ Schema design  
✅ Encryption  
✅ Query builder usage  
🟡 Multi-tenancy (single-tenant OK, multi-tenant needs work)  
🟡 Rate limiting  
🟡 Error handling depth limits  
🟢 Audit logging

**Estimated fix time:** 2 hours

---

**END OF AUDIT**
