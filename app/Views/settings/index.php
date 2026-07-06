<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$SETTINGS_BASE = base_url('settings');
$timezones = [
    'UTC'                => 'UTC',
    'America/New_York'   => 'Eastern Time (US & Canada)',
    'America/Chicago'    => 'Central Time (US & Canada)',
    'America/Denver'     => 'Mountain Time (US & Canada)',
    'America/Los_Angeles'=> 'Pacific Time (US & Canada)',
    'Europe/London'      => 'London',
    'Europe/Paris'       => 'Paris / Berlin',
    'Asia/Dubai'         => 'Dubai',
    'Asia/Kolkata'       => 'India (IST)',
    'Asia/Singapore'     => 'Singapore',
    'Australia/Sydney'   => 'Sydney',
];
$currentTz = $account['timezone'] ?? 'UTC';
?>

<!-- Header -->
<div class="mb-5">
    <h1 class="text-xl font-bold text-gray-900">Settings</h1>
    <p class="text-sm text-gray-500 mt-0.5">Manage your account settings and configurations</p>
</div>

<!-- Flash messages -->
<?php if (session()->getFlashdata('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-6 -mb-px overflow-x-auto scrollbar-none">
        <?php
        $tabs = [
            'account'       => ['label' => 'Account',       'url' => $SETTINGS_BASE],
            'whatsapp'      => ['label' => 'WhatsApp',      'url' => $SETTINGS_BASE . '/whatsapp'],
            'ai'            => ['label' => 'AI',            'url' => $SETTINGS_BASE . '/ai'],
            'notifications' => ['label' => 'Notifications', 'url' => $SETTINGS_BASE . '/notifications'],
            'api'           => ['label' => 'API Keys',      'url' => $SETTINGS_BASE . '/api-keys'],
            'webhooks'      => ['label' => 'Webhooks',      'url' => $SETTINGS_BASE . '/webhooks'],
        ];
        foreach ($tabs as $key => $tab):
            $active = $activeTab === $key;
        ?>
        <a href="<?= $tab['url'] ?>"
           class="py-3 text-sm font-medium border-b-2 whitespace-nowrap
               <?= $active
                   ? 'border-blue-600 text-blue-700'
                   : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
            <?= $tab['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- ===== ACCOUNT TAB ===== -->
<?php if ($activeTab === 'account'): ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-xl">
    <h2 class="text-sm font-semibold text-gray-800 mb-4">Account Information</h2>

    <div id="account-error" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
    <div id="account-success" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">Settings saved.</div>

    <form id="account-form" onsubmit="submitForm(event, 'account')">
        <?= csrf_field() ?>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
            <input type="text" name="name" required
                   value="<?= esc($account['name'] ?? '') ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <select name="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
                <?php foreach ($timezones as $tz => $label): ?>
                <option value="<?= $tz ?>" <?= $currentTz === $tz ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" id="account-btn"
                class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
            Save Changes
        </button>
    </form>
</div>

<!-- ===== WHATSAPP TAB ===== -->
<?php elseif ($activeTab === 'whatsapp'): ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-xl">
    <h2 class="text-sm font-semibold text-gray-800 mb-4">WhatsApp Business API Configuration</h2>

    <div id="wa-error" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
    <div id="wa-success" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">Configuration saved.</div>

    <form id="whatsapp-form" onsubmit="submitForm(event, 'whatsapp')">
        <?= csrf_field() ?>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number ID</label>
            <input type="text" name="phone_number_id" required
                   value="<?= esc($waConfig['phone_number_id'] ?? '') ?>"
                   placeholder="123456789012345"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            <p class="text-xs text-gray-400 mt-1">From Meta Business Manager → WhatsApp → API Setup</p>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Business Account ID</label>
            <input type="text" name="waba_id" required
                   value="<?= esc($waConfig['waba_id'] ?? '') ?>"
                   placeholder="WABA ID"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Access Token
                <?php if (!empty($waConfig['access_token_masked'])): ?>
                <span class="font-normal text-gray-400 text-xs ml-1">(leave blank to keep current)</span>
                <?php endif; ?>
            </label>
            <input type="password" name="access_token"
                   placeholder="<?= !empty($waConfig['access_token_masked']) ? $waConfig['access_token_masked'] : 'Paste your permanent access token' ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            <p class="text-xs text-gray-400 mt-1">Permanent token from Meta Business Manager. Stored encrypted.</p>
        </div>

        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Verify Token</label>
            <input type="text" name="webhook_verify_token" required
                   value="<?= esc($waConfig['webhook_verify_token'] ?? '') ?>"
                   placeholder="my-secret-verify-token"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            <p class="text-xs text-gray-400 mt-1">Custom string — must match what you enter in Meta's webhook config.</p>
        </div>

        <!-- Webhook URL info box -->
        <div class="mb-5 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-xs font-semibold text-gray-800 mb-1">Webhook URL</p>
            <code class="block text-xs text-blue-800 break-all mb-1"><?= base_url('api/whatsapp/webhook') ?></code>
            <p class="text-xs text-gray-500">Paste this into Meta Business Manager → WhatsApp → Webhooks.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" id="wa-btn"
                    class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
                Save Configuration
            </button>
            <button type="button" onclick="showTestModal()"
                    class="px-4 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-700">
                Send Test Message
            </button>
        </div>
    </form>
</div>

<!-- Official Numbers Panel -->
<?php if ($waConfig): ?>
<div class="mt-6 bg-white border border-gray-200 rounded-xl p-5 max-w-2xl" id="official-numbers-panel">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-800">Manage Official Numbers</h2>
        <button onclick="fetchNumberInfo()" id="fetch-btn"
                class="px-3 py-1.5 text-xs bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
            Fetch from Meta
        </button>
    </div>

    <div id="fetch-error" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="numbers-table">
            <thead>
                <tr class="bg-teal-600 text-white text-xs uppercase">
                    <th class="px-4 py-3 text-left font-semibold">Name</th>
                    <th class="px-4 py-3 text-left font-semibold">WA Number</th>
                    <th class="px-4 py-3 text-center font-semibold">Status</th>
                    <th class="px-4 py-3 text-center font-semibold">Rating</th>
                    <th class="px-4 py-3 text-center font-semibold">Mode</th>
                    <th class="px-4 py-3 text-left font-semibold">Last Fetched</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-t border-gray-100 hover:bg-gray-50" id="number-row">
                    <td class="px-4 py-3 font-medium text-gray-900" id="col-name">
                        <?= esc($waConfig['verified_name'] ?? $waConfig['business_name'] ?? '—') ?>
                    </td>
                    <td class="px-4 py-3 text-gray-700 font-mono text-xs" id="col-phone">
                        <?= esc($waConfig['display_phone_number'] ?? $waConfig['phone_number_id'] ?? '—') ?>
                    </td>
                    <td class="px-4 py-3 text-center" id="col-status">
                        <?php
                        $nameStatus = $waConfig['name_status'] ?? null;
                        $statusColor = match($nameStatus) {
                            'APPROVED'   => 'bg-green-100 text-green-700',
                            'PENDING'    => 'bg-yellow-100 text-yellow-700',
                            'REJECTED'   => 'bg-red-100 text-red-600',
                            default      => 'bg-gray-100 text-gray-500',
                        };
                        ?>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $statusColor ?>">
                            <?= $nameStatus ? ucfirst(strtolower($nameStatus)) : 'Active' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center" id="col-rating">
                        <?php
                        $rating = $waConfig['quality_rating'] ?? null;
                        $ratingColor = match($rating) {
                            'GREEN'   => 'bg-green-500',
                            'YELLOW'  => 'bg-yellow-400',
                            'RED'     => 'bg-red-500',
                            default   => 'bg-gray-300',
                        };
                        ?>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium">
                            <span class="w-3 h-3 rounded-full <?= $ratingColor ?>"></span>
                            <?= $rating ?? 'Unknown' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center" id="col-mode">
                        <?php $mode = $waConfig['account_mode'] ?? null; ?>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $mode === 'LIVE' ? 'bg-green-100 text-green-700' : ($mode ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500') ?>">
                            <?= $mode ? ucfirst(strtolower($mode)) : '—' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400" id="col-fetched">
                        <?= $waConfig['number_info_fetched_at'] ? date('d M Y, g:i A', strtotime($waConfig['number_info_fetched_at'])) : 'Never — click Fetch' ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
async function fetchNumberInfo() {
    const btn = document.getElementById('fetch-btn');
    const errEl = document.getElementById('fetch-error');
    errEl.classList.add('hidden');
    btn.textContent = 'Fetching…';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    try {
        const res  = await fetch('<?= base_url('settings/fetch-number-info') ?>', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.error) {
            errEl.textContent = data.error;
            errEl.classList.remove('hidden');
        } else {
            // Update table cells without reload
            const ratingColors = { GREEN: 'bg-green-500', YELLOW: 'bg-yellow-400', RED: 'bg-red-500' };
            const rating = data.quality_rating ?? 'UNKNOWN';
            const ratingDot = ratingColors[rating] ?? 'bg-gray-300';

            document.getElementById('col-name').textContent  = data.verified_name || '—';
            document.getElementById('col-phone').textContent = data.display_phone_number || '—';
            document.getElementById('col-rating').innerHTML  =
                `<span class="inline-flex items-center gap-1.5 text-xs font-medium">
                    <span class="w-3 h-3 rounded-full ${ratingDot}"></span>${rating}
                </span>`;
            const mode = data.account_mode ?? '';
            document.getElementById('col-mode').innerHTML =
                `<span class="text-xs px-2 py-0.5 rounded-full font-medium ${mode === 'LIVE' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'}">${mode ? mode.charAt(0)+mode.slice(1).toLowerCase() : '—'}</span>`;
            document.getElementById('col-fetched').textContent = 'Just now';
            document.getElementById('col-status').innerHTML =
                `<span class="text-xs px-2 py-0.5 rounded-full font-medium bg-green-100 text-green-700">${data.name_status ? data.name_status.charAt(0)+data.name_status.slice(1).toLowerCase() : 'Active'}</span>`;
        }
    } catch(e) {
        errEl.textContent = 'Network error';
        errEl.classList.remove('hidden');
    }

    btn.textContent = 'Fetch from Meta';
    btn.disabled = false;
}
</script>

<!-- Test Message Modal -->
<div id="test-modal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none !important;">
    <div class="absolute inset-0 bg-black/30" onclick="hideTestModal()"></div>
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Send Test Message</h3>
        <div id="test-error" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
            <input type="tel" id="test-phone" placeholder="+911234567890"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            <p class="text-xs text-gray-400 mt-1">Include country code (e.g., +91 for India)</p>
        </div>
        <div class="flex justify-end gap-3">
            <button onclick="hideTestModal()"
                    class="px-4 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-700">Cancel</button>
            <button id="test-btn" onclick="submitTest()"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">Send Test</button>
        </div>
    </div>
</div>

<!-- ===== NOTIFICATIONS TAB ===== -->
<?php elseif ($activeTab === 'notifications'): ?>
<?php $notifPrefs = $notifPrefs ?? []; ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-xl">
    <h2 class="text-sm font-semibold text-gray-800 mb-4">Email Notification Preferences</h2>

    <div id="notif-success" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">Preferences saved.</div>

    <form id="notifications-form" onsubmit="submitForm(event, 'notifications')">
        <?= csrf_field() ?>
        <div class="space-y-4">
            <?php
            $notifOptions = [
                'email_new_message'        => ['New Message Alerts',    'Get notified when a new inbound message arrives'],
                'email_broadcast_complete' => ['Broadcast Completion',  'Get notified when a broadcast campaign finishes sending'],
                'email_daily_summary'      => ['Daily Summary',         'Receive a daily digest of team activity'],
                'email_weekly_report'      => ['Weekly Report',         'Receive a weekly performance summary'],
            ];
            foreach ($notifOptions as $name => [$title, $description]):
            ?>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="<?= $name ?>" value="1"
                       <?= ($notifPrefs[$name] ?? false) ? 'checked' : '' ?>
                       class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <div>
                    <div class="text-sm font-medium text-gray-900"><?= $title ?></div>
                    <div class="text-xs text-gray-500"><?= $description ?></div>
                </div>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 pt-5 border-t border-gray-100">
            <label class="block text-sm font-medium text-gray-700 mb-1">Owner WhatsApp Number for Booking Alerts</label>
            <input type="text" name="owner_whatsapp_number"
                   value="<?= esc($notifPrefs['owner_whatsapp_number'] ?? '') ?>"
                   placeholder="+91 98765 43210"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            <p class="text-xs text-gray-400 mt-1">When a customer books an appointment, we'll WhatsApp this number the booking details. Leave blank to disable.</p>
        </div>

        <div class="mt-6 pt-5 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Daily Report</h3>
            <p class="text-xs text-gray-400 mb-4">Sent automatically every day to the numbers below. Uses an Approved Template — founder/HR won't have an open WhatsApp session with your number, so a plain text message would get rejected by Meta most days. Leave both numbers blank to disable.</p>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Founder WhatsApp Number</label>
                <input type="text" name="daily_report_founder_number"
                       value="<?= esc($notifPrefs['daily_report_founder_number'] ?? '') ?>"
                       placeholder="+91 98765 43210"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">HR WhatsApp Number</label>
                <input type="text" name="daily_report_hr_number"
                       value="<?= esc($notifPrefs['daily_report_hr_number'] ?? '') ?>"
                       placeholder="+91 98765 43210"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Send Time (daily)</label>
                <input type="time" name="daily_report_time"
                       value="<?= esc($notifPrefs['daily_report_time'] ?? '08:00') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
                <p class="text-xs text-gray-400 mt-1">Checked once a minute by the scheduler — fires the first time the clock hits this minute each day.</p>
            </div>

            <div class="mb-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Report Template</label>
                <select name="daily_report_template_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
                    <option value="">— Select template —</option>
                    <?php $currentReportTemplate = $notifPrefs['daily_report_template_id'] ?? ''; ?>
                    <?php foreach ($templates ?? [] as $t): ?>
                    <option value="<?= esc($t['id']) ?>" <?= $currentReportTemplate === $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?> (<?= esc($t['language'] ?? 'en') ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($templates)): ?>
                <p class="text-xs text-amber-600 mt-1">No approved templates yet — create one under Templates first (5 numbered placeholders: date, new leads, messages sent, messages received, appointments booked).</p>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" id="notif-btn"
                class="mt-6 px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
            Save Preferences
        </button>
    </form>
</div>

<!-- ===== AI TAB ===== -->
<?php elseif ($activeTab === 'ai'): ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-xl">
    <h2 class="text-sm font-semibold text-gray-800 mb-4">AI Integration (OpenAI)</h2>

    <div id="ai-error" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
    <div id="ai-success" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">Configuration saved.</div>

    <form id="ai-form" onsubmit="submitForm(event, 'ai')">
        <?= csrf_field() ?>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                OpenAI API Key
                <?php if (!empty($aiConfig['api_key_masked'])): ?>
                <span class="font-normal text-gray-400 text-xs ml-1">(leave blank to keep current)</span>
                <?php endif; ?>
            </label>
            <input type="password" name="api_key"
                   placeholder="<?= !empty($aiConfig['api_key_masked']) ? $aiConfig['api_key_masked'] : 'sk-...' ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
            <p class="text-xs text-gray-400 mt-1">From platform.openai.com → API Keys. Stored encrypted.</p>
        </div>

        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Default Model</label>
            <select name="model" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
                <?php $currentModel = $aiConfig['model'] ?? 'gpt-4o-mini'; ?>
                <?php foreach (['gpt-4o-mini' => 'GPT-4o mini (fastest, cheapest)', 'gpt-4o' => 'GPT-4o', 'gpt-4-turbo' => 'GPT-4 Turbo'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $currentModel === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Used by default for AI nodes in Flows — can be overridden per node.</p>
        </div>

        <button type="submit" id="ai-btn"
                class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
            Save Configuration
        </button>
    </form>
</div>

<!-- AI Usage panel -->
<div class="mt-6 bg-white border border-gray-200 rounded-xl p-6 max-w-xl">
    <h2 class="text-sm font-semibold text-gray-800 mb-1">Usage</h2>
    <p class="text-xs text-gray-400 mb-4">OpenAI doesn't expose a balance check for API keys — this is tracked from our own calls' token counts, priced at published per-model rates. Estimate, not your actual OpenAI invoice.</p>

    <?php $totalTokens = (int) ($usageTotals['total_tokens'] ?? 0); $totalCost = (float) ($usageTotals['total_cost'] ?? 0); ?>
    <div class="grid grid-cols-2 gap-4 mb-5">
        <div class="bg-gray-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-gray-900">$<?= number_format($totalCost, 4) ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Estimated cost (all-time)</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-gray-900"><?= number_format($totalTokens) ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Tokens used (all-time)</div>
        </div>
    </div>

    <?php if (!empty($usageByFeature)): ?>
    <table class="w-full text-xs">
        <thead>
            <tr class="text-left text-gray-400 uppercase tracking-wide">
                <th class="pb-2 font-medium">Feature</th>
                <th class="pb-2 font-medium text-right">Calls</th>
                <th class="pb-2 font-medium text-right">Tokens</th>
                <th class="pb-2 font-medium text-right">Cost</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php $featureLabels = ['ai_node' => 'AI Node (Flows)', 'translate_outgoing' => 'Translate (composer)', 'rewrite' => 'AI Rewrite (composer)', 'translate_incoming' => 'Translate (incoming)']; ?>
            <?php foreach ($usageByFeature as $row): ?>
            <tr>
                <td class="py-2 text-gray-700"><?= esc($featureLabels[$row['feature']] ?? $row['feature']) ?></td>
                <td class="py-2 text-right text-gray-600"><?= number_format((int)$row['calls']) ?></td>
                <td class="py-2 text-right text-gray-600"><?= number_format((int)$row['tokens']) ?></td>
                <td class="py-2 text-right text-gray-600">$<?= number_format((float)$row['cost'], 4) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-xs text-gray-400 text-center py-4">No AI usage yet.</p>
    <?php endif; ?>
</div>

<!-- ===== API KEYS TAB ===== -->
<?php elseif ($activeTab === 'api'): ?>
<div class="bg-white border border-gray-200 rounded-xl p-6 max-w-xl">
    <h2 class="text-sm font-semibold text-gray-800 mb-4">API Access</h2>

    <div id="api-regen-success" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
        API key regenerated successfully.
    </div>

    <!-- Inline confirmation — shown instead of native confirm() -->
    <div id="api-regen-confirm" class="hidden mb-3 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm">
        <p class="font-medium text-amber-800 mb-3"><?= rx_icon('warning', 'w-4 h-4') ?> This will immediately invalidate your current API key. Any apps using it will stop working.</p>
        <div class="flex gap-2">
            <button onclick="confirmRegenerate()"
                    class="px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700 font-medium">
                Yes, regenerate
            </button>
            <button onclick="cancelRegenerate()"
                    class="px-3 py-1.5 border border-gray-300 text-xs rounded-lg hover:bg-gray-50 text-gray-600">
                Cancel
            </button>
        </div>
    </div>

    <div class="mb-5">
        <label class="block text-sm font-medium text-gray-700 mb-2">Your API Key</label>
        <div class="flex items-center gap-2">
            <input type="password" id="api-key-display"
                   value="<?= esc($account['api_key'] ?? '') ?>"
                   readonly
                   class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 font-mono text-gray-700">
            <button onclick="toggleApiKeyVisibility(this)"
                    class="px-3 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-700 whitespace-nowrap">
                Show
            </button>
            <button onclick="copyApiKey()"
                    class="px-3 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-700 whitespace-nowrap">
                Copy
            </button>
            <button onclick="promptRegenerate()"
                    class="px-3 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 whitespace-nowrap">
                Regenerate
            </button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Keep this secret. Regenerating invalidates the current key immediately.</p>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm">
        <p class="font-semibold text-gray-800 mb-3">API Documentation</p>

        <div class="space-y-3 text-xs">
            <div>
                <p class="font-medium text-gray-700 mb-1">Authentication</p>
                <code class="block bg-white border border-gray-200 rounded px-3 py-2 text-gray-600">Authorization: Bearer YOUR_API_KEY</code>
            </div>
            <div>
                <p class="font-medium text-gray-700 mb-1">Base URL</p>
                <code class="block bg-white border border-gray-200 rounded px-3 py-2 text-gray-600"><?= base_url('api/v1') ?></code>
            </div>
            <div>
                <p class="font-medium text-gray-700 mb-1">Example Endpoints</p>
                <ul class="text-gray-500 space-y-1 list-disc list-inside">
                    <li>GET /api/v1/contacts — list contacts</li>
                    <li>POST /api/v1/messages — send a message</li>
                    <li>GET /api/v1/conversations — list conversations</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ===== WEBHOOKS TAB ===== -->
<?php elseif ($activeTab === 'webhooks'): ?>
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Recent Webhook Activity</h2>
        <p class="text-xs text-gray-400 mt-0.5">Last 50 incoming WhatsApp webhook events · Logs retained for 30 days</p>
    </div>

    <?php if (empty($webhookLogs)): ?>
    <div class="p-12 text-center">
        <div class="mb-2"><?= rx_icon('signal', 'w-10 h-10', 'mx-auto') ?></div>
        <p class="text-sm text-gray-500">No webhook events logged yet.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Time</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Event</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Duration</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($webhookLogs as $log): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                        <?= date('M j, g:i:s A', strtotime($log['created_at'])) ?>
                    </td>
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs text-gray-800"><?= esc($log['event_type']) ?></span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-block px-2 py-0.5 text-xs rounded-full font-medium
                            <?= $log['status'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= ucfirst($log['status']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-500">
                        <?= $log['processing_time_ms'] !== null ? $log['processing_time_ms'] . ' ms' : '—' ?>
                    </td>
                    <td class="px-5 py-3 text-xs">
                        <?php if ($log['error_message']): ?>
                        <span class="text-red-600"><?= esc(substr($log['error_message'], 0, 80)) ?></span>
                        <?php elseif ($log['payload']): ?>
                        <button onclick="showPayload(this)" data-payload="<?= htmlspecialchars($log['payload'], ENT_QUOTES, 'UTF-8') ?>"
                                class="text-blue-600 hover:text-blue-700">View payload</button>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Payload modal -->
<div id="payload-modal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none !important;">
    <div class="absolute inset-0 bg-black/30" onclick="hidePayloadModal()"></div>
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 z-10 max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold text-gray-900">Webhook Payload</h3>
            <button onclick="hidePayloadModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <pre id="payload-body" class="flex-1 overflow-auto bg-gray-50 rounded-lg p-4 text-xs text-gray-700 whitespace-pre-wrap break-words"></pre>
    </div>
</div>
<?php endif; ?>

<script>
const SETTINGS_BASE = <?= json_encode(base_url('settings'), JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

// Generic form submit with inline feedback
async function submitForm(e, tab) {
    e.preventDefault();
    const form    = e.target;
    const fd      = new FormData(form);
    const btnIds  = { account: 'account-btn', whatsapp: 'wa-btn', ai: 'ai-btn', notifications: 'notif-btn' };
    const errIds  = { account: 'account-error', whatsapp: 'wa-error', ai: 'ai-error' };
    const succIds = { account: 'account-success', whatsapp: 'wa-success', ai: 'ai-success', notifications: 'notif-success' };
    const btnId   = btnIds[tab];
    const errId   = errIds[tab] || null;
    const succId  = succIds[tab];
    const urlMap  = {
        account:       SETTINGS_BASE + '/update-account',
        whatsapp:      SETTINGS_BASE + '/update-whatsapp',
        ai:            SETTINGS_BASE + '/update-ai',
        notifications: SETTINGS_BASE + '/update-notifications',
    };
    const btn = document.getElementById(btnId);
    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Saving…';
    if (errId) document.getElementById(errId).classList.add('hidden');
    document.getElementById(succId).classList.add('hidden');

    try {
        const res  = await fetch(urlMap[tab], { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById(succId).classList.remove('hidden');
        } else {
            const msg = data.error || (data.errors ? Object.values(data.errors).join(' ') : 'Failed to save.');
            if (errId) { document.getElementById(errId).textContent = msg; document.getElementById(errId).classList.remove('hidden'); }
            else alert(msg);
        }
    } catch {
        if (errId) { document.getElementById(errId).textContent = 'Network error. Try again.'; document.getElementById(errId).classList.remove('hidden'); }
        else alert('Network error. Try again.');
    }
    btn.disabled = false;
    btn.textContent = origText;
}

// API Key
function copyApiKey() {
    const inp = document.getElementById('api-key-display');
    const type = inp.type;
    inp.type = 'text';
    inp.select();
    document.execCommand('copy');
    inp.type = type;
}

function toggleApiKeyVisibility(btn) {
    const inp = document.getElementById('api-key-display');
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
}

function promptRegenerate() {
    document.getElementById('api-regen-success').classList.add('hidden');
    document.getElementById('api-regen-confirm').classList.remove('hidden');
}

function cancelRegenerate() {
    document.getElementById('api-regen-confirm').classList.add('hidden');
}

async function confirmRegenerate() {
    document.getElementById('api-regen-confirm').classList.add('hidden');
    const csrfToken = document.querySelector('input[name="<?= csrf_token() ?>"]')?.value
                   ?? document.cookie.match(/csrf_cookie_name=([^;]+)/)?.[1] ?? '';
    const fd = new FormData();
    fd.append('<?= csrf_token() ?>', csrfToken);
    const res  = await fetch(SETTINGS_BASE + '/regenerate-api-key', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('api-key-display').value = data.api_key;
        document.getElementById('api-key-display').type  = 'password';
        document.querySelectorAll('button[onclick*="toggleApiKeyVisibility"]').forEach(b => b.textContent = 'Show');
        document.getElementById('api-regen-success').classList.remove('hidden');
    } else {
        alert(data.error || 'Failed to regenerate');
    }
}

// WhatsApp test modal
function showTestModal() {
    document.getElementById('test-modal').style.removeProperty('display');
    document.getElementById('test-error').classList.add('hidden');
    document.getElementById('test-phone').value = '';
}
function hideTestModal() {
    document.getElementById('test-modal').style.setProperty('display', 'none', 'important');
}

async function submitTest() {
    const phone = document.getElementById('test-phone').value.trim();
    if (!phone) { document.getElementById('test-error').textContent = 'Phone number required.'; document.getElementById('test-error').classList.remove('hidden'); return; }

    const btn = document.getElementById('test-btn');
    btn.disabled = true; btn.textContent = 'Sending…';
    document.getElementById('test-error').classList.add('hidden');

    const fd = new FormData();
    fd.append('test_phone', phone);
    try {
        const res  = await fetch(SETTINGS_BASE + '/test-whatsapp', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) { hideTestModal(); alert(data.message); }
        else { document.getElementById('test-error').textContent = data.error || 'Test failed.'; document.getElementById('test-error').classList.remove('hidden'); }
    } catch {
        document.getElementById('test-error').textContent = 'Network error. Try again.';
        document.getElementById('test-error').classList.remove('hidden');
    }
    btn.disabled = false; btn.textContent = 'Send Test';
}

// Payload modal
function showPayload(btn) {
    const raw = btn.dataset.payload;
    try { document.getElementById('payload-body').textContent = JSON.stringify(JSON.parse(raw), null, 2); }
    catch { document.getElementById('payload-body').textContent = raw; }
    document.getElementById('payload-modal').style.removeProperty('display');
}
function hidePayloadModal() {
    document.getElementById('payload-modal').style.setProperty('display', 'none', 'important');
}
</script>

<?= $this->endSection() ?>
