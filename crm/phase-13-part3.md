### Prompt 13.3 — Routes & Testing

```
Complete settings module with routes and comprehensive testing.

Add routes to app/Config/Routes.php:

GET /settings → SettingsController::index
POST /settings/update-account → SettingsController::updateAccount
GET /settings/whatsapp → SettingsController::whatsapp
POST /settings/update-whatsapp → SettingsController::updateWhatsApp
POST /settings/test-whatsapp → SettingsController::testWhatsApp
GET /settings/notifications → SettingsController::notifications
POST /settings/update-notifications → SettingsController::updateNotifications
GET /settings/api-keys → SettingsController::apiKeys
POST /settings/regenerate-api-key → SettingsController::regenerateApiKey
GET /settings/webhooks → SettingsController::webhooks

Update app/Controllers/WebhookController.php to log webhooks:

Add at the start of handleWhatsApp() method:

$startTime = microtime(true);
$success = true;
$errorMessage = null;

try {
    // ... existing webhook processing code ...
    
} catch (\Exception $e) {
    $success = false;
    $errorMessage = $e->getMessage();
    log_message('error', 'Webhook processing failed: ' . $e->getMessage());
}

// Log webhook at the end
$processingTime = (microtime(true) - $startTime) * 1000; // ms

$db = \Config\Database::connect();
$db->table('webhook_logs')->insert([
    'account_id' => $accountId,
    'event_type' => $entry['changes'][0]['field'] ?? 'unknown',
    'payload' => json_encode($payload),
    'status' => $success ? 'success' : 'failed',
    'error_message' => $errorMessage,
    'processing_time_ms' => (int)$processingTime,
    'created_at' => date('Y-m-d H:i:s')
]);

Create webhook cleanup command app/Commands/CleanupWebhookLogs.php:

<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CleanupWebhookLogs extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'webhooks:cleanup';
    protected $description = 'Delete webhook logs older than 30 days';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $deleted = $db->table('webhook_logs')
            ->where('created_at <', $cutoffDate)
            ->delete();

        CLI::write("Deleted {$deleted} old webhook logs", 'green');
    }
}

Add to app/Commands/RunScheduled.php:

// Cleanup old webhook logs daily at 4 AM
if ((int)date('H') === 4) {
    command('webhooks:cleanup');
}
```

### Testing Phase 13 (Settings Module)

Manual test checklist:

```bash
# 1. Navigate to settings
http://localhost:8080/settings

# Test: Only admins can access (agents/viewers redirected)

# 2. Account Settings Tab
- Change account name
- Change timezone
- Click "Save Changes"

# Test:
- Settings saved to database
- Session updated (account name)
- Activity logged

# 3. WhatsApp Configuration Tab
- Enter Phone Number ID
- Enter WABA ID
- Enter Access Token
- Enter Webhook Verify Token
- Click "Save Configuration"

# Test:
- Credentials saved to database
- Access token encrypted (check database - should be encrypted string)
- Webhook URL displayed correctly
- Activity logged

# 4. Send Test Message
- Click "Send Test Message"
- Enter test phone number (with country code)
- Submit

# Test:
- Test message sent via WhatsApp
- Success message shown
- Activity logged
- Webhook log created

# 5. Test with invalid credentials
- Enter wrong access token
- Try to send test message

# Test: Error message shown, not saved if validation fails

# 6. Notifications Tab
- Toggle checkboxes for different notification types
- Click "Save Preferences"

# Test:
- Preferences saved to account.notification_preferences JSON
- Activity logged

# 7. API Keys Tab
- View generated API key
- Click "Copy"

# Test:
- API key copied to clipboard
- Key is 64 characters (hex)

# 8. Regenerate API Key
- Click "Regenerate"
- Confirm warning

# Test:
- New key generated
- Old key invalidated
- Display updated with new key
- Activity logged

# 9. Test API with key
curl -H "Authorization: Bearer YOUR_API_KEY" \
     http://localhost:8080/api/v1/contacts

# Test: API authenticates successfully with key

# 10. Webhooks Tab
- Send test webhook from Meta
- Check webhook logs table

# Test:
- Webhook appears in logs
- Shows timestamp, event type, status
- Processing time displayed
- Payload viewable

# 11. Webhook log with error
- Send malformed webhook payload

# Test:
- Status shows "failed"
- Error message displayed
- Processing time captured

# 12. Webhook log cleanup
php spark webhooks:cleanup

# Test:
- Logs older than 30 days deleted
- Recent logs preserved

# 13. Tab navigation
- Click through all tabs

# Test:
- Active tab highlighted
- URL changes (/settings/whatsapp, etc.)
- Content loads correctly

# 14. Permission enforcement
# Login as agent, try:
http://localhost:8080/settings

# Test: Redirected to dashboard with error

# 15. Timezone functionality
- Set timezone to non-UTC
- Check timestamps throughout app

# Test: Timestamps display in correct timezone

# 16. Access token masking
- Save WhatsApp config
- Return to settings

# Test:
- Token shown as "EAAG..." (masked)
- Full token not exposed in HTML
- Database has encrypted version

# 17. Form validation
- Try to save empty account name
- Try to save invalid timezone

# Test: Validation errors shown

# 18. Webhook verify token
- Configure in Meta Business Manager
- Send webhook

# Test: Webhook verified and processed

# 19. API documentation display
- Check API section

# Test:
- Base URL correct
- Example endpoints shown
- Authentication header format correct

# 20. Activity logging
- Perform various settings changes
- Check team activity log

# Test: All settings changes logged with details
```

**Pass Criteria:**
- ✅ Only admins can access settings
- ✅ Account name and timezone save correctly
- ✅ WhatsApp credentials save and encrypt properly
- ✅ Test message sends successfully
- ✅ Notification preferences save to JSON
- ✅ API key generates on first visit
- ✅ API key copy to clipboard works
- ✅ Regenerate API key creates new key
- ✅ Webhook logs capture all events
- ✅ Webhook logs show status, time, payload
- ✅ Failed webhooks log error messages
- ✅ Webhook cleanup command works
- ✅ Tab navigation works smoothly
- ✅ All forms have validation
- ✅ Access token encrypted in database
- ✅ Access token masked in UI
- ✅ Activity logging for all changes
- ✅ Timezone affects app-wide timestamps
- ✅ Tenant isolation (settings per account)
- ✅ Form submissions use AJAX (no page reload)

**Common Issues:**
- Settings not saving: Check form submission AJAX, check validation
- Access token exposed: Ensure encryption used, ensure masking in view
- Test message fails: Check MetaApi integration, check access token validity
- Webhook logs not appearing: Check logging code in WebhookController
- API key not copying: Check clipboard API support in browser
- Regenerate API key doesn't work: Check uniqueness constraint on api_key column
- Timezone not applying: Check date() functions use account timezone
- Tab navigation broken: Check route definitions, check activeTab variable
- Webhook verify token mismatch: Check Meta config matches database value
- Permission bypass: Check RoleFilter applied to all settings routes
- Activity log missing entries: Check ActivityLogModel::log() calls
- Webhook cleanup not running: Check cron setup, check date calculation
- Empty state issues: Check null coalescing for optional fields
- JSON decode errors: Check notification_preferences column exists and is JSON type

---

**Phase 13 Complete!**

Settings module includes:
- Account information (name, timezone)
- WhatsApp configuration with encryption
- Test message functionality
- Notification preferences
- API key management with regeneration
- Webhook activity logs with 30-day retention
- Tab-based navigation
- Activity logging for all changes
- Permission enforcement (admin-only)

**Database additions:**
- accounts.timezone (VARCHAR)
- accounts.notification_preferences (JSON)
- accounts.api_key (VARCHAR, unique)
- webhook_logs table (tracks all incoming webhooks)

**Commands added:**
- webhooks:cleanup (delete logs >30 days old)

