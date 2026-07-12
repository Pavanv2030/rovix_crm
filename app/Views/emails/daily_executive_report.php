<?php
/**
 * @var array  $stats
 * @var string $recipientRole  'founder' | 'hr' | 'executive'
 */
$accountName = esc($stats['account_name'] ?? 'Your Team');
$salutation  = $recipientRole === 'hr' ? 'Dear HR Team,' : 'Dear Founder,';
$hours       = ($stats['total_time_logged'] ?? 0) > 0
    ? number_format((float) $stats['total_time_logged'], 1) . ' hrs'
    : '—';
$repLabel    = ($stats['total_reps'] ?? 0) === 1 ? 'rep' : 'reps';

$truncate = static function (?string $text, int $max = 90): string {
    $text = trim((string) $text);
    if ($text === '') {
        return 'No recent note';
    }
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Executive Report — <?= $accountName ?></title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">

    <!-- Header -->
    <tr>
        <td style="background:linear-gradient(135deg,#1e3a5f 0%,#1e40af 100%);padding:32px 40px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#93c5fd;margin-bottom:8px;">Rovix CRM</div>
                    <div style="font-size:26px;font-weight:700;color:#ffffff;line-height:1.2;">Daily Executive Report</div>
                    <div style="font-size:14px;color:#bfdbfe;margin-top:8px;"><?= esc($stats['report_date'] ?? date('l, j F Y')) ?></div>
                </td>
                <td align="right" valign="top">
                    <div style="background:rgba(255,255,255,0.12);border-radius:8px;padding:12px 16px;text-align:center;">
                        <div style="font-size:11px;color:#93c5fd;text-transform:uppercase;letter-spacing:1px;">Account</div>
                        <div style="font-size:15px;font-weight:600;color:#ffffff;margin-top:4px;"><?= $accountName ?></div>
                    </div>
                </td>
            </tr>
            </table>
        </td>
    </tr>

    <!-- Salutation -->
    <tr>
        <td style="padding:28px 40px 8px;">
            <p style="margin:0;font-size:15px;color:#475569;line-height:1.6;"><?= esc($salutation) ?></p>
            <p style="margin:12px 0 0;font-size:14px;color:#64748b;line-height:1.6;">
                Please find below your team's performance summary for the reporting period
                <strong style="color:#334155;"><?= esc($stats['report_period'] ?? '') ?></strong>.
            </p>
        </td>
    </tr>

    <!-- KPI Cards -->
    <tr>
        <td style="padding:20px 40px 8px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <?php
                $kpis = [
                    ['label' => 'Team Reached',   'value' => $stats['team_total_reached'] ?? 0,  'color' => '#1e40af'],
                    ['label' => 'Active Reps',    'value' => ($stats['total_reps'] ?? 0) . ' ' . $repLabel, 'color' => '#0f766e'],
                    ['label' => 'Hours Logged',   'value' => $hours,                              'color' => '#7c3aed'],
                    ['label' => 'New Leads',      'value' => $stats['new_leads'] ?? 0,           'color' => '#b45309'],
                ];
                foreach ($kpis as $i => $kpi):
                    $pad = $i < 3 ? 'padding-right:8px;' : '';
                ?>
                <td width="25%" style="<?= $pad ?>vertical-align:top;">
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center;">
                        <div style="font-size:22px;font-weight:700;color:<?= $kpi['color'] ?>;"><?= esc((string) $kpi['value']) ?></div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-top:6px;"><?= esc($kpi['label']) ?></div>
                    </div>
                </td>
                <?php endforeach; ?>
            </tr>
            <tr><td colspan="4" style="height:8px;"></td></tr>
            <tr>
                <?php
                $kpis2 = [
                    ['label' => 'Messages Sent',     'value' => $stats['messages_sent'] ?? 0,     'color' => '#0369a1'],
                    ['label' => 'Messages Received', 'value' => $stats['messages_received'] ?? 0, 'color' => '#15803d'],
                    ['label' => 'Appointments',      'value' => $stats['appointments_booked'] ?? 0,'color' => '#a21caf'],
                    ['label' => 'Hot Leads',         'value' => $stats['hot_lead_count'] ?? 0,    'color' => '#dc2626'],
                ];
                foreach ($kpis2 as $i => $kpi):
                    $pad = $i < 3 ? 'padding-right:8px;' : '';
                ?>
                <td width="25%" style="<?= $pad ?>vertical-align:top;">
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center;">
                        <div style="font-size:22px;font-weight:700;color:<?= $kpi['color'] ?>;"><?= esc((string) $kpi['value']) ?></div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-top:6px;"><?= esc($kpi['label']) ?></div>
                    </div>
                </td>
                <?php endforeach; ?>
            </tr>
            </table>
        </td>
    </tr>

    <!-- Team Performance -->
    <?php if (!empty($stats['agents'])): ?>
    <tr>
        <td style="padding:24px 40px 8px;">
            <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1e40af;border-bottom:2px solid #dbeafe;padding-bottom:8px;margin-bottom:16px;">
                Team Performance
            </div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr style="background:#f1f5f9;">
                <th align="left" style="padding:10px 12px;font-size:11px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Agent</th>
                <th align="center" style="padding:10px 8px;font-size:11px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Reached</th>
                <th align="center" style="padding:10px 8px;font-size:11px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Hours</th>
                <th align="center" style="padding:10px 8px;font-size:11px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Calls</th>
                <th align="center" style="padding:10px 8px;font-size:11px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">WhatsApp</th>
                <th align="center" style="padding:10px 8px;font-size:11px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Follow-ups</th>
            </tr>
            <?php foreach ($stats['agents'] as $idx => $agent):
                $bg = $idx % 2 === 0 ? '#ffffff' : '#fafbfc';
                $agentHours = ($agent['hours_logged'] ?? 0) > 0
                    ? number_format((float) $agent['hours_logged'], 1)
                    : '—';
            ?>
            <tr style="background:<?= $bg ?>;">
                <td style="padding:12px;font-size:14px;font-weight:600;color:#1e293b;border-bottom:1px solid #f1f5f9;"><?= esc($agent['name']) ?></td>
                <td align="center" style="padding:12px 8px;font-size:14px;color:#1e40af;font-weight:600;border-bottom:1px solid #f1f5f9;"><?= (int) ($agent['reached'] ?? 0) ?></td>
                <td align="center" style="padding:12px 8px;font-size:14px;color:#475569;border-bottom:1px solid #f1f5f9;"><?= esc($agentHours) ?></td>
                <td align="center" style="padding:12px 8px;font-size:14px;color:#475569;border-bottom:1px solid #f1f5f9;"><?= (int) ($agent['outreach']['call'] ?? 0) ?></td>
                <td align="center" style="padding:12px 8px;font-size:14px;color:#475569;border-bottom:1px solid #f1f5f9;"><?= (int) ($agent['outreach']['whatsapp'] ?? 0) ?></td>
                <td align="center" style="padding:12px 8px;font-size:14px;color:#475569;border-bottom:1px solid #f1f5f9;"><?= (int) ($agent['status_changes']['follow_up'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Pipeline Highlights -->
    <?php if (!empty($stats['dispositions']['strong']) || !empty($stats['dispositions']['could'])): ?>
    <tr>
        <td style="padding:24px 40px 8px;">
            <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1e40af;border-bottom:2px solid #dbeafe;padding-bottom:8px;margin-bottom:16px;">
                Pipeline Highlights
            </div>
            <?php foreach ($stats['dispositions']['strong'] as $lead):
                $label = ($lead['status'] ?? '') === 'Converted' ? 'Strong' : 'Strong Could';
            ?>
            <div style="background:#fef2f2;border-left:4px solid #dc2626;border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:10px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#dc2626;letter-spacing:0.5px;"><?= esc($label) ?></div>
                <div style="font-size:15px;font-weight:600;color:#1e293b;margin-top:4px;"><?= esc($lead['name'] ?: $lead['phone']) ?><?= !empty($lead['company']) ? ' · ' . esc($lead['company']) : '' ?></div>
                <div style="font-size:13px;color:#64748b;margin-top:6px;line-height:1.5;"><?= esc($truncate($lead['latest_note'] ?? '')) ?></div>
                <div style="font-size:12px;color:#94a3b8;margin-top:6px;"><?= esc($lead['phone'] ?? '') ?></div>
            </div>
            <?php endforeach; ?>

            <?php foreach (array_slice($stats['dispositions']['could'] ?? [], 0, 5) as $lead): ?>
            <div style="background:#fffbeb;border-left:4px solid #d97706;border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:10px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#d97706;letter-spacing:0.5px;">Could</div>
                <div style="font-size:15px;font-weight:600;color:#1e293b;margin-top:4px;"><?= esc($lead['name'] ?: $lead['phone']) ?><?= !empty($lead['company']) ? ' · ' . esc($lead['company']) : '' ?></div>
                <div style="font-size:13px;color:#64748b;margin-top:6px;line-height:1.5;"><?= esc($truncate($lead['latest_note'] ?? '')) ?></div>
                <div style="font-size:12px;color:#94a3b8;margin-top:6px;"><?= esc($lead['phone'] ?? '') ?></div>
            </div>
            <?php endforeach; ?>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Follow-ups -->
    <?php if (!empty($stats['follow_ups'])): ?>
    <tr>
        <td style="padding:24px 40px 8px;">
            <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1e40af;border-bottom:2px solid #dbeafe;padding-bottom:8px;margin-bottom:16px;">
                Upcoming Follow-ups (<?= count($stats['follow_ups']) ?>)
            </div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr style="background:#f1f5f9;">
                <th align="left" style="padding:10px 12px;font-size:11px;text-transform:uppercase;color:#64748b;">Contact</th>
                <th align="left" style="padding:10px 12px;font-size:11px;text-transform:uppercase;color:#64748b;">Company</th>
                <th align="left" style="padding:10px 12px;font-size:11px;text-transform:uppercase;color:#64748b;">Due Date</th>
                <th align="left" style="padding:10px 12px;font-size:11px;text-transform:uppercase;color:#64748b;">Latest Note</th>
            </tr>
            <?php foreach (array_slice($stats['follow_ups'], 0, 8) as $idx => $fu):
                $bg = $idx % 2 === 0 ? '#ffffff' : '#fafbfc';
            ?>
            <tr style="background:<?= $bg ?>;">
                <td style="padding:10px 12px;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;"><?= esc($fu['name'] ?: $fu['phone']) ?></td>
                <td style="padding:10px 12px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;"><?= esc($fu['company'] ?? '—') ?></td>
                <td style="padding:10px 12px;font-size:13px;color:#475569;border-bottom:1px solid #f1f5f9;"><?= !empty($fu['follow_up_date']) ? esc(date('M j, Y', strtotime($fu['follow_up_date']))) : '—' ?></td>
                <td style="padding:10px 12px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;"><?= esc($truncate($fu['latest_note'] ?? '', 70)) ?></td>
            </tr>
            <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Footer -->
    <tr>
        <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:24px 40px;">
            <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;text-align:center;">
                This report was automatically generated by <strong style="color:#64748b;">Rovix CRM</strong>.<br>
                Confidential — intended for internal leadership use only.<br>
                Generated at <?= esc(date('M j, Y · g:i A')) ?>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>