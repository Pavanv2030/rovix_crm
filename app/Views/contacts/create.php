<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= base_url('contacts') ?>" class="text-gray-400 hover:text-gray-600">← Contacts</a>
        <h1 class="text-xl font-bold text-gray-900">New Contact</h1>
    </div>

    <form action="<?= base_url('contacts') ?>" method="POST" class="space-y-4">
        <?= csrf_field() ?>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Basic Info</h2>

            <!-- ── Phone + OTP Verification ────────────────────────────── -->
            <div id="otp-widget">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone Number <span class="text-red-500">*</span>
                    <span id="otp-verified-badge"
                          class="hidden ml-2 text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-semibold">
                        <?= rx_icon('check', 'w-3 h-3', '!text-green-700') ?> Verified
                    </span>
                </label>

                <!-- Phone row -->
                <div class="flex gap-2">
                    <input type="text" name="phone" id="otp-phone"
                           value="<?= old('phone') ?>" required
                           placeholder="e.g. 919876543210 (with country code)"
                           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="button" id="otp-send-btn"
                            onclick="otpSend()"
                            class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg font-medium whitespace-nowrap transition-colors">
                        Send OTP
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">Include country code without + (e.g. 919876543210 for India)</p>

                <!-- OTP row (hidden until OTP sent) -->
                <div id="otp-row" class="hidden mt-3">
                    <div class="flex gap-2">
                        <input type="text" id="otp-input" maxlength="6"
                               placeholder="Enter 6-digit OTP"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 tracking-widest font-mono text-center text-lg">
                        <button type="button" id="otp-verify-btn"
                                onclick="otpVerify()"
                                class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg font-medium whitespace-nowrap transition-colors">
                            Verify
                        </button>
                    </div>
                    <div class="flex items-center justify-between mt-1.5">
                        <p id="otp-msg" class="text-xs"></p>
                        <p id="otp-timer" class="text-xs text-gray-400"></p>
                    </div>
                </div>

                <input type="hidden" name="is_phone_verified" id="otp-verified-flag" value="0">
            </div>
            <!-- ── End OTP Widget ─────────────────────────────────────── -->

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="<?= old('name') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= old('email') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                    <input type="text" name="company" value="<?= old('company') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Channel</label>
                    <select name="channel" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select channel...</option>
                        <?php foreach (['Cold Call','WhatsApp','Referral','Walk-in','Website','Social Media','Email','Exhibition','Other'] as $ch): ?>
                        <option value="<?= $ch ?>" <?= old('channel') === $ch ? 'selected' : '' ?>><?= $ch ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vertical</label>
                    <input type="text" name="vertical" value="<?= old('vertical') ?>" placeholder="e.g. Interior Design, IT, Real Estate"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach (['New','Active','Follow-up','Lost'] as $st): ?>
                        <option value="<?= $st ?>" <?= old('status', 'New') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Rep</label>
                    <select name="assigned_agent_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Unassigned</option>
                        <?php foreach ($agents ?? [] as $agent): ?>
                        <option value="<?= esc($agent['user_id']) ?>" <?= old('assigned_agent_id') === $agent['user_id'] ? 'selected' : '' ?>>
                            <?= esc($agent['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Follow-up Date</label>
                    <input type="date" name="follow_up_date" value="<?= old('follow_up_date') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

        </div>

        <!-- Tags -->
        <?php if (!empty($allTags)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Tags</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($allTags as $tag): ?>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="tag_ids[]" value="<?= esc($tag['id']) ?>" class="rounded">
                    <span class="text-sm px-2 py-0.5 rounded-full text-white" style="background-color: <?= esc($tag['color'] ?? '#3B82F6') ?>">
                        <?= esc($tag['name']) ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Custom Fields -->
        <?php if (!empty($customFields)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Custom Fields</h2>
            <?php foreach ($customFields as $field): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= esc($field['field_name']) ?></label>
                <?php if ($field['field_type'] === 'dropdown'): ?>
                    <?php $opts = json_decode($field['field_options'] ?? '[]', true); ?>
                    <select name="custom_fields[<?= esc($field['id']) ?>]"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select...</option>
                        <?php foreach ($opts as $opt): ?>
                        <option><?= esc($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field['field_type'] === 'date'): ?>
                    <input type="date" name="custom_fields[<?= esc($field['id']) ?>]"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php elseif ($field['field_type'] === 'number'): ?>
                    <input type="number" name="custom_fields[<?= esc($field['id']) ?>]"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php else: ?>
                    <input type="text" name="custom_fields[<?= esc($field['id']) ?>]"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-3 items-center">
            <button type="submit" id="contact-submit-btn" disabled
                    class="px-6 py-2 bg-blue-900 text-white text-sm rounded-lg font-medium opacity-50 cursor-not-allowed transition-all"
                    title="Verify phone number first">
                Save Contact
            </button>
            <a href="<?= base_url('contacts') ?>" class="px-6 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</a>
            <p id="submit-hint" class="text-xs text-amber-600">Verify the phone number to enable save</p>
        </div>
    </form>
</div>

<script>
const OTP_SEND_URL   = <?= json_encode(base_url('api/otp/send'),   JSON_HEX_TAG|JSON_HEX_AMP) ?>;
const OTP_VERIFY_URL = <?= json_encode(base_url('api/otp/verify'), JSON_HEX_TAG|JSON_HEX_AMP) ?>;
const CSRF_NAME      = '<?= csrf_token() ?>';
const CSRF_HASH      = '<?= csrf_hash() ?>';

let otpCountdown = null;

function otpHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}
function otpBody(extra) {
    return JSON.stringify(Object.assign({ [CSRF_NAME]: CSRF_HASH }, extra));
}

function setMsg(text, color) {
    const el = document.getElementById('otp-msg');
    el.textContent = text;
    el.className   = 'text-xs ' + (color === 'green' ? 'text-green-600 font-semibold' :
                                    color === 'red'   ? 'text-red-600' : 'text-gray-500');
}

async function otpSend() {
    const phone = document.getElementById('otp-phone').value.trim().replace(/[\s\-+]/g, '');
    if (!phone) { alert('Please enter a phone number first.'); return; }

    const btn = document.getElementById('otp-send-btn');
    btn.disabled = true;
    btn.textContent = 'Sending…';
    setMsg('', '');

    try {
        const r    = await fetch(OTP_SEND_URL, { method: 'POST', headers: otpHeaders(), body: otpBody({ phone }) });
        const data = await r.json();

        if (data.success) {
            document.getElementById('otp-row').classList.remove('hidden');
            document.getElementById('otp-input').value = '';
            document.getElementById('otp-input').focus();
            document.getElementById('otp-verified-flag').value = '0';
            setMsg('OTP sent! Check your WhatsApp.', 'green');
            startCountdown(60);
            btn.textContent = 'Resend OTP';
        } else {
            setMsg(data.message || 'Failed to send OTP.', 'red');
            btn.textContent = 'Send OTP';
            btn.disabled = false;
        }
    } catch (e) {
        setMsg('Network error. Please try again.', 'red');
        btn.textContent = 'Send OTP';
        btn.disabled = false;
    }
}

async function otpVerify() {
    const phone = document.getElementById('otp-phone').value.trim().replace(/[\s\-+]/g, '');
    const otp   = document.getElementById('otp-input').value.trim();

    if (otp.length !== 6) { setMsg('Enter the 6-digit OTP.', 'red'); return; }

    const btn = document.getElementById('otp-verify-btn');
    btn.disabled = true;
    btn.textContent = 'Verifying…';

    try {
        const r    = await fetch(OTP_VERIFY_URL, { method: 'POST', headers: otpHeaders(), body: otpBody({ phone, otp }) });
        const data = await r.json();

        if (data.success) {
            // Mark verified
            document.getElementById('otp-verified-flag').value = '1';
            document.getElementById('otp-verified-badge').classList.remove('hidden');
            document.getElementById('otp-row').classList.add('hidden');
            document.getElementById('otp-phone').readOnly = true;
            document.getElementById('otp-phone').classList.add('bg-green-50', 'border-green-400');
            document.getElementById('otp-send-btn').classList.add('hidden');

            clearInterval(otpCountdown);
            document.getElementById('otp-timer').textContent = '';
            setMsg('', '');

            // Enable submit
            const submitBtn = document.getElementById('contact-submit-btn');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            submitBtn.removeAttribute('title');
            document.getElementById('submit-hint').classList.add('hidden');
        } else {
            setMsg(data.message || 'Incorrect OTP.', 'red');
            btn.disabled = false;
            btn.textContent = 'Verify';
        }
    } catch (e) {
        setMsg('Network error. Please try again.', 'red');
        btn.disabled = false;
        btn.textContent = 'Verify';
    }
}

function startCountdown(seconds) {
    clearInterval(otpCountdown);
    const timerEl  = document.getElementById('otp-timer');
    const sendBtn  = document.getElementById('otp-send-btn');
    let remaining  = seconds;

    sendBtn.disabled = true;
    timerEl.textContent = 'Resend in ' + remaining + 's';

    otpCountdown = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(otpCountdown);
            timerEl.textContent = '';
            sendBtn.disabled = false;
            sendBtn.textContent = 'Resend OTP';
        } else {
            timerEl.textContent = 'Resend in ' + remaining + 's';
        }
    }, 1000);
}

// Allow form submit if OTP already skipped (optional: remove to enforce verification)
document.querySelector('form').addEventListener('submit', function(e) {
    if (document.getElementById('otp-verified-flag').value !== '1') {
        if (!confirm('Phone is not verified. Save contact anyway?')) {
            e.preventDefault();
            return false;
        }
        // Allow unverified submission
        const btn = document.getElementById('contact-submit-btn');
        btn.disabled = false;
    }
});
</script>

<?= $this->endSection() ?>
