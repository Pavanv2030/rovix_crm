### Prompt 13.2 — Settings Views (All Tabs)

```
Create comprehensive settings interface for Rovix AI Leads Tool.

Create app/Views/settings/index.php:

<?php $this->extend('layouts/main'); ?>

<?php $this->section('content'); ?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
    <p class="text-sm text-gray-600 mt-1">Manage your account settings and configurations</p>
</div>

<!-- Tab Navigation -->
<div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-8">
        <a href="<?= base_url('settings') ?>" 
           class="py-2 border-b-2 text-sm font-medium <?= $activeTab === 'account' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900' ?>">
            Account
        </a>
        <a href="<?= base_url('settings/whatsapp') ?>" 
           class="py-2 border-b-2 text-sm font-medium <?= $activeTab === 'whatsapp' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900' ?>">
            WhatsApp
        </a>
        <a href="<?= base_url('settings/notifications') ?>" 
           class="py-2 border-b-2 text-sm font-medium <?= $activeTab === 'notifications' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900' ?>">
            Notifications
        </a>
        <a href="<?= base_url('settings/api-keys') ?>" 
           class="py-2 border-b-2 text-sm font-medium <?= $activeTab === 'api' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900' ?>">
            API Keys
        </a>
        <a href="<?= base_url('settings/webhooks') ?>" 
           class="py-2 border-b-2 text-sm font-medium <?= $activeTab === 'webhooks' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900' ?>">
            Webhooks
        </a>
    </nav>
</div>

<!-- Tab Content -->
<?php if ($activeTab === 'account'): ?>
    <!-- Account Settings -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 max-w-2xl">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h3>
        
        <form id="account-form">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                <input type="text" 
                       name="name" 
                       value="<?= esc($account['name']) ?>"
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                <select name="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="UTC" <?= $account['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                    <option value="America/New_York" <?= $account['timezone'] === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US)</option>
                    <option value="America/Chicago" <?= $account['timezone'] === 'America/Chicago' ? 'selected' : '' ?>>Central Time (US)</option>
                    <option value="America/Denver" <?= $account['timezone'] === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (US)</option>
                    <option value="America/Los_Angeles" <?= $account['timezone'] === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US)</option>
                    <option value="Europe/London" <?= $account['timezone'] === 'Europe/London' ? 'selected' : '' ?>>London</option>
                    <option value="Europe/Paris" <?= $account['timezone'] === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                    <option value="Asia/Dubai" <?= $account['timezone'] === 'Asia/Dubai' ? 'selected' : '' ?>>Dubai</option>
                    <option value="Asia/Kolkata" <?= $account['timezone'] === 'Asia/Kolkata' ? 'selected' : '' ?>>India</option>
                    <option value="Asia/Singapore" <?= $account['timezone'] === 'Asia/Singapore' ? 'selected' : '' ?>>Singapore</option>
                    <option value="Australia/Sydney" <?= $account['timezone'] === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney</option>
                </select>
            </div>
            
            <button type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Save Changes
            </button>
        </form>
    </div>

<?php elseif ($activeTab === 'whatsapp'): ?>
    <!-- WhatsApp Configuration -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 max-w-2xl">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">WhatsApp Business API Configuration</h3>
        
        <form id="whatsapp-form">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number ID</label>
                <input type="text" 
                       name="phone_number_id" 
                       value="<?= esc($waConfig['phone_number_id'] ?? '') ?>"
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">From Meta Business Manager → WhatsApp → API Setup</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Business Account ID (WABA ID)</label>
                <input type="text" 
                       name="waba_id" 
                       value="<?= esc($waConfig['waba_id'] ?? '') ?>"
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Access Token</label>
                <input type="password" 
                       name="access_token" 
                       placeholder="<?= isset($waConfig['access_token_masked']) ? $waConfig['access_token_masked'] : 'Enter your access token' ?>"
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">Permanent access token from Meta Business Manager</p>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Verify Token</label>
                <input type="text" 
                       name="webhook_verify_token" 
                       value="<?= esc($waConfig['webhook_verify_token'] ?? '') ?>"
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">Custom token for webhook verification</p>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h4 class="font-semibold text-sm text-gray-900 mb-2">Webhook URL</h4>
                <code class="text-sm text-gray-700 block mb-2"><?= base_url('webhook/whatsapp') ?></code>
                <p class="text-xs text-gray-600">Configure this URL in Meta Business Manager → WhatsApp → Webhooks</p>
            </div>
            
            <div class="flex items-center gap-3">
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Save Configuration
                </button>
                
                <button type="button" 
                        onclick="showTestModal()"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Send Test Message
                </button>
            </div>
        </form>
    </div>

<?php elseif ($activeTab === 'notifications'): ?>
    <!-- Notification Preferences -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 max-w-2xl">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Email Notifications</h3>
        
        <form id="notifications-form">
            <div class="space-y-4">
                <label class="flex items-center gap-3">
                    <input type="checkbox" 
                           name="email_new_message" 
                           <?= ($notificationPrefs['email_new_message'] ?? false) ? 'checked' : '' ?>
                           class="rounded">
                    <div>
                        <div class="font-medium text-gray-900">New Message Alerts</div>
                        <div class="text-sm text-gray-600">Get notified when a new message arrives</div>
                    </div>
                </label>
                
                <label class="flex items-center gap-3">
                    <input type="checkbox" 
                           name="email_broadcast_complete" 
                           <?= ($notificationPrefs['email_broadcast_complete'] ?? false) ? 'checked' : '' ?>
                           class="rounded">
                    <div>
                        <div class="font-medium text-gray-900">Broadcast Completion</div>
                        <div class="text-sm text-gray-600">Get notified when a broadcast finishes</div>
                    </div>
                </label>
                
                <label class="flex items-center gap-3">
                    <input type="checkbox" 
                           name="email_daily_summary" 
                           <?= ($notificationPrefs['email_daily_summary'] ?? false) ? 'checked' : '' ?>
                           class="rounded">
                    <div>
                        <div class="font-medium text-gray-900">Daily Summary</div>
                        <div class="text-sm text-gray-600">Receive a daily summary of activity</div>
                    </div>
                </label>
                
                <label class="flex items-center gap-3">
                    <input type="checkbox" 
                           name="email_weekly_report" 
                           <?= ($notificationPrefs['email_weekly_report'] ?? false) ? 'checked' : '' ?>
                           class="rounded">
                    <div>
                        <div class="font-medium text-gray-900">Weekly Report</div>
                        <div class="text-sm text-gray-600">Receive a weekly performance report</div>
                    </div>
                </label>
            </div>
            
            <button type="submit" 
                    class="mt-6 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Save Preferences
            </button>
        </form>
    </div>

<?php elseif ($activeTab === 'api'): ?>
    <!-- API Keys -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 max-w-2xl">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">API Access</h3>
        
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Your API Key</label>
            <div class="flex items-center gap-3">
                <input type="text" 
                       id="api-key-display"
                       value="<?= esc($account['api_key']) ?>"
                       readonly
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm">
                <button onclick="copyApiKey()" 
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Copy
                </button>
                <button onclick="regenerateApiKey()" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Regenerate
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-2">⚠️ Keep this key secret. Regenerating will invalidate the old key.</p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-sm text-gray-900 mb-3">API Documentation</h4>
            
            <div class="space-y-3 text-sm">
                <div>
                    <p class="font-medium text-gray-900">Authentication</p>
                    <p class="text-gray-600">Include your API key in the header:</p>
                    <code class="block mt-1 bg-white px-3 py-2 rounded border border-gray-200">
                        Authorization: Bearer YOUR_API_KEY
                    </code>
                </div>
                
                <div>
                    <p class="font-medium text-gray-900">Base URL</p>
                    <code class="block mt-1 bg-white px-3 py-2 rounded border border-gray-200">
                        <?= base_url('api/v1') ?>
                    </code>
                </div>
                
                <div>
                    <p class="font-medium text-gray-900">Example Endpoints</p>
                    <ul class="mt-1 space-y-1 text-gray-600">
                        <li>• GET /api/v1/contacts - List contacts</li>
                        <li>• POST /api/v1/messages - Send message</li>
                        <li>• GET /api/v1/conversations - List conversations</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($activeTab === 'webhooks'): ?>
    <!-- Webhook Logs -->
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Recent Webhook Activity</h3>
            <p class="text-sm text-gray-600 mt-1">Last 50 webhook events from WhatsApp</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processing Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (!empty($webhookLogs)): ?>
                        <?php foreach ($webhookLogs as $log): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= date('M j, g:i:s A', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-900"><?= esc($log['event_type']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-block px-2 py-1 text-xs rounded-full
                                    <?= $log['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= ucfirst($log['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= $log['processing_time_ms'] ?>ms
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($log['error_message']): ?>
                                <span class="text-sm text-red-600"><?= esc($log['error_message']) ?></span>
                                <?php else: ?>
                                <button onclick="showWebhookPayload(<?= htmlspecialchars($log['payload']) ?>)" 
                                        class="text-sm text-blue-600 hover:text-blue-700">
                                    View Payload
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                No webhook logs yet
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Test WhatsApp Modal -->
<div x-data="{ open: false }" 
     x-show="open" 
     @show-test-modal.window="open = true"
     style="display: none;"
     class="fixed inset-0 z-50 overflow-y-auto">
    
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black opacity-30" @click="open = false"></div>
        
        <div class="bg-white rounded-lg shadow-xl z-50 max-w-md w-full p-6">
            <h3 class="text-lg font-semibold mb-4">Send Test Message</h3>
            
            <form id="test-form" @submit.prevent="submitTest">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Test Phone Number</label>
                    <input type="tel" 
                           name="test_phone" 
                           placeholder="+1234567890"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Include country code (e.g., +1 for US)</p>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" 
                            @click="open = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Send Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Account form
document.getElementById('account-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const response = await fetch('/settings/update-account', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        alert('Settings saved!');
    } else {
        alert('Failed to save settings');
    }
});

// WhatsApp form
document.getElementById('whatsapp-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const response = await fetch('/settings/update-whatsapp', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        alert('WhatsApp configuration saved!');
    } else {
        alert('Failed to save configuration');
    }
});

// Notifications form
document.getElementById('notifications-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const response = await fetch('/settings/update-notifications', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        alert('Notification preferences saved!');
    }
});

function showTestModal() {
    window.dispatchEvent(new CustomEvent('show-test-modal'));
}

async function submitTest() {
    const form = document.getElementById('test-form');
    const formData = new FormData(form);
    
    const response = await fetch('/settings/test-whatsapp', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    
    if (response.ok) {
        alert('✓ ' + data.message);
        window.dispatchEvent(new CustomEvent('show-test-modal')); // close modal
    } else {
        alert('✗ ' + (data.error || 'Test failed'));
    }
}

function copyApiKey() {
    const input = document.getElementById('api-key-display');
    input.select();
    document.execCommand('copy');
    alert('API key copied to clipboard');
}

async function regenerateApiKey() {
    if (!confirm('⚠️ This will invalidate your current API key. Continue?')) return;
    
    const response = await fetch('/settings/regenerate-api-key', {
        method: 'POST'
    });
    
    const data = await response.json();
    
    if (response.ok) {
        document.getElementById('api-key-display').value = data.api_key;
        alert('New API key generated');
    }
}

function showWebhookPayload(payload) {
    alert(JSON.stringify(JSON.parse(payload), null, 2));
}
</script>

<?php $this->endSection(); ?>
```

Continue with Part 3 (Testing)?
