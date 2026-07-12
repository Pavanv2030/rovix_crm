<?php

namespace App\Libraries;

use App\Models\AccountModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageTemplateModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\TemplateSendBuilder;

/**
 * Sends the daily executive report to founder and HR via:
 * 1. Professional HTML email (full report)
 * 2. WhatsApp approved template (executive summary)
 */
class DailyReportSender
{
    public function send(string $accountId): void
    {
        $account = (new AccountModel())->find($accountId);
        if (!$account) {
            log_message('warning', "[DailyReportSender] account {$accountId} not found");
            return;
        }

        $prefs          = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $founderNumber  = trim($prefs['daily_report_founder_number'] ?? '');
        $hrNumber       = trim($prefs['daily_report_hr_number'] ?? '');
        $founderEmail   = trim($prefs['daily_report_founder_email'] ?? '');
        $hrEmail        = trim($prefs['daily_report_hr_email'] ?? '');
        $templateId     = $prefs['daily_report_template_id'] ?? null;

        $whatsappRecipients = array_filter([
            'founder' => $founderNumber,
            'hr'      => $hrNumber,
        ]);

        $emailRecipients = array_filter([
            'founder' => $founderEmail,
            'hr'      => $hrEmail,
        ]);

        if (empty($whatsappRecipients) && empty($emailRecipients)) {
            return;
        }

        $digest = new DailyDigestService();
        $stats  = $digest->generateStats($accountId);

        $this->sendEmails($digest, $stats, $emailRecipients, $account['name'] ?? 'Team');

        if (empty($whatsappRecipients)) {
            return;
        }

        if (!$templateId) {
            log_message('warning', "[DailyReportSender] account {$accountId} has WhatsApp recipients but no template — email only");
            return;
        }

        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) {
            log_message('warning', "[DailyReportSender] account {$accountId} template {$templateId} not found");
            return;
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $accountId)->first();
        if (!$waConfig || ($waConfig['status'] ?? '') !== 'connected') {
            log_message('warning', "[DailyReportSender] account {$accountId} has no connected WhatsApp");
            return;
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
        $metaApi     = new MetaApi();

        foreach ($whatsappRecipients as $role => $number) {
            $normalized = \App\Libraries\WhatsApp\PhoneUtils::normalize($number);
            if (!$normalized) {
                continue;
            }

            $variables  = $digest->formatTemplateVariables($stats, $role);
            $components = TemplateSendBuilder::buildComponents($template, $variables);

            try {
                $metaApi->sendTemplate(
                    $waConfig['phone_number_id'],
                    $accessToken,
                    $normalized,
                    $template['name'],
                    $template['language'] ?? 'en',
                    $components
                );
            } catch (\Exception $e) {
                log_message('error', "[DailyReportSender] WhatsApp to {$number} failed: " . $e->getMessage());
            }
        }
    }

    private function sendEmails(DailyDigestService $digest, array $stats, array $recipients, string $accountName): void
    {
        if (empty($recipients)) {
            return;
        }

        $config    = config('Email');
        $fromEmail = !empty($config->fromEmail) ? $config->fromEmail : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'rovix-crm.com');
        $fromName  = !empty($config->fromName) ? $config->fromName : 'Rovix CRM';
        $subject   = 'Daily Executive Report — ' . $accountName . ' — ' . ($stats['report_date'] ?? date('j M Y'));

        foreach ($recipients as $role => $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                log_message('warning', "[DailyReportSender] Invalid email for {$role}: {$email}");
                continue;
            }

            try {
                $mail = \Config\Services::email();
                $mail->setFrom($fromEmail, $fromName);
                $mail->setTo($email);
                $mail->setMailType('html');
                $mail->setSubject($subject);
                $mail->setMessage($digest->renderEmailHtml($stats, $role));

                if (!$mail->send()) {
                    log_message('error', "[DailyReportSender] Email to {$email} failed: " . $mail->printDebugger(['headers', 'subject']));
                }
            } catch (\Throwable $e) {
                log_message('error', "[DailyReportSender] Email to {$email} failed: " . $e->getMessage());
            }
        }
    }
}