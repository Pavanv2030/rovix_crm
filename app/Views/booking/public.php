<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking Confirmed – <?= esc($type['name'] ?? 'Appointment') ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
body { background: #f3f4f6; min-height: 100vh; }

.confirmed-banner {
    background: #22c55e; color: white; padding: 20px 24px;
}
.confirmed-banner h2 { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
.confirmed-banner p  { font-size: 14px; opacity: .9; }
.confirmed-banner a  { color: white; word-break: break-all; }

.container { max-width: 820px; margin: 24px auto; padding: 0 16px;
             display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .container { grid-template-columns: 1fr; } }

.card { background: white; border-radius: 12px; padding: 24px;
        border: 1px solid #e5e7eb; }
.card h3 { font-size: 12px; font-weight: 700; color: #9ca3af;
           text-transform: uppercase; letter-spacing: .08em; margin-bottom: 16px; }

.invoice-num  { font-size: 26px; font-weight: 800; color: #111; margin-bottom: 4px; }
.invoice-name { font-size: 16px; color: #374151; margin-bottom: 2px; }
.invoice-loc  { font-size: 13px; color: #9ca3af; margin-bottom: 2px; }
.invoice-dt   { font-size: 13px; color: #9ca3af; margin-bottom: 20px; }

.line { display: flex; justify-content: space-between; padding: 10px 0;
        border-top: 1px solid #f3f4f6; font-size: 14px; color: #374151; }
.line.total { font-weight: 700; font-size: 15px; border-top: 2px solid #d1d5db; padding-top: 12px; }

.apt-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.apt-icon   { width: 44px; height: 44px; border-radius: 10px; background: #dcfce7;
              display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.apt-name   { font-size: 16px; font-weight: 700; color: #111; }
.apt-sub    { font-size: 13px; color: #6b7280; }

.detail-box { background: #1f2937; border-radius: 8px; padding: 16px; }
.drow { display: flex; justify-content: space-between; padding: 7px 0;
        font-size: 13px; border-bottom: 1px solid #374151; }
.drow:last-child { border-bottom: none; }
.drow .lb { color: #9ca3af; }
.drow .vl { color: #f3f4f6; font-weight: 500; }
.drow .vl a { color: #60a5fa; text-decoration: none; }
.drow .vl a:hover { text-decoration: underline; }

.print-row { text-align: right; margin-bottom: 14px; display: flex; justify-content: flex-end; gap: 8px; }
.print-row button { background: white; border: 1px solid #d1d5db; border-radius: 6px;
                    padding: 6px 14px; cursor: pointer; font-size: 13px; color: #374151; }
.print-row button:hover { background: #f9fafb; }
.print-row button.reschedule-btn { border-color: #93c5fd; color: #1d4ed8; background: #eff6ff; }
.print-row button.reschedule-btn:hover { background: #dbeafe; }
.print-row button:disabled { opacity: .6; cursor: default; }
.reschedule-msg { font-size: 13px; margin-bottom: 10px; padding: 8px 10px; border-radius: 6px; }
.reschedule-msg.ok  { background: #dcfce7; color: #166534; }
.reschedule-msg.err { background: #fee2e2; color: #991b1b; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-confirmed { background: #dcfce7; color: #166534; }
.badge-pending   { background: #fef9c3; color: #713f12; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }
.badge-completed { background: #e0f2fe; color: #075985; }

@media print {
    .print-row { display: none; }
    body { background: white; }
    .card { border: 1px solid #ddd; box-shadow: none; }
    .confirmed-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>

<div class="confirmed-banner">
    <h2><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="20" height="20" style="display:inline-block;vertical-align:-4px" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg> Booking Confirmed</h2>
    <p>Your appointment has been confirmed. Track it anytime:<br>
    <a href="<?= current_url() ?>"><?= current_url() ?></a></p>
</div>

<div class="container">

    <!-- Invoice card -->
    <div class="card">
        <h3>Invoice</h3>
        <div class="invoice-num"><?= esc($invoiceNumber) ?></div>
        <div class="invoice-name"><?= esc($type['name'] ?? '') ?></div>
        <div class="invoice-loc">Online</div>
        <div class="invoice-dt">
            <?= date('jS M Y, g:ia', strtotime($appointment['scheduled_at'])) ?>
            – <?= date('g:ia', strtotime($appointment['end_at'])) ?>
        </div>

        <div class="line">
            <span>Status</span>
            <span><span class="badge badge-<?= esc($appointment['status']) ?>"><?= ucfirst($appointment['status']) ?></span></span>
        </div>
        <div class="line">
            <span>Tax (0%)</span>
            <span><?= strtoupper(esc($type['currency'] ?? 'INR')) ?> 0.00</span>
        </div>
        <div class="line total">
            <span>Amount</span>
            <span><?= strtoupper(esc($type['currency'] ?? 'INR')) ?> <?= number_format((float)($appointment['price_paid'] ?? 0), 2) ?></span>
        </div>
    </div>

    <!-- Appointment details card -->
    <div class="card">
        <div id="reschedule-msg"></div>
        <div class="print-row">
            <?php if ($appointment['status'] !== 'cancelled' && strtotime($appointment['scheduled_at']) > time()): ?>
            <button id="reschedule-btn" class="reschedule-btn" onclick="requestReschedule()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="14" height="14" style="display:inline-block;vertical-align:-2px" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                Reschedule
            </button>
            <?php endif; ?>
            <button onclick="window.print()"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="14" height="14" style="display:inline-block;vertical-align:-2px" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg> Print</button>
        </div>
        <h3>Appointment</h3>

        <div class="apt-header">
            <div class="apt-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="22" height="22" style="color:#16a34a" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg></div>
            <div>
                <div class="apt-name"><?= esc($type['name'] ?? '') ?></div>
                <div class="apt-sub"><?= esc($type['description'] ?? '') ?></div>
            </div>
        </div>

        <div class="detail-box">
            <div class="drow">
                <span class="lb">Customer</span>
                <span class="vl"><?= esc($appointment['contact_name'] ?? 'N/A') ?></span>
            </div>
            <div class="drow">
                <span class="lb">Start</span>
                <span class="vl"><?= date('jS M Y, g:i a', strtotime($appointment['scheduled_at'])) ?></span>
            </div>
            <div class="drow">
                <span class="lb">End</span>
                <span class="vl"><?= date('jS M Y, g:i a', strtotime($appointment['end_at'])) ?></span>
            </div>
            <div class="drow">
                <span class="lb">Duration</span>
                <span class="vl"><?= esc($type['duration_minutes'] ?? 30) ?> minutes</span>
            </div>
            <div class="drow">
                <span class="lb">Location</span>
                <span class="vl">Online</span>
            </div>
            <?php if (!empty($appointment['meet_link'])): ?>
            <div class="drow">
                <span class="lb">Google Meet</span>
                <span class="vl">
                    <a href="<?= esc($appointment['meet_link']) ?>" target="_blank">Join Meeting →</a>
                </span>
            </div>
            <?php endif; ?>
            <div class="drow">
                <span class="lb">Booked At</span>
                <span class="vl"><?= date('jS M Y, H:i', strtotime($appointment['created_at'])) ?></span>
            </div>
        </div>
    </div>

</div>

<script>
async function requestReschedule() {
    const btn = document.getElementById('reschedule-btn');
    const msgEl = document.getElementById('reschedule-msg');
    btn.disabled = true;
    const origHtml = btn.innerHTML;
    btn.textContent = 'Sending…';
    msgEl.innerHTML = '';

    try {
        const res  = await fetch(window.location.pathname + '/reschedule', { method: 'POST' });
        const data = await res.json();
        msgEl.innerHTML = `<div class="reschedule-msg ${data.success ? 'ok' : 'err'}">${data.success ? data.message : (data.error || 'Something went wrong.')}</div>`;
    } catch (e) {
        msgEl.innerHTML = '<div class="reschedule-msg err">Network error — try again.</div>';
    }

    btn.disabled = false;
    btn.innerHTML = origHtml;
}
</script>
</body>
</html>
