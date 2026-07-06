<?php

namespace App\Libraries;

use App\Models\AccountModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageTemplateModel;
use App\Models\ContactModel;
use App\Models\MessageModel;
use App\Models\AppointmentModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\TemplateSendBuilder;

/**
 * Sends the daily WhatsApp report to an account's configured founder/HR
 * numbers. Always via an Approved Template — founder/HR don't have an open
 * 24h session with the business number, so a plain text message would get
 * rejected by Meta on any day they haven't messaged the bot themselves.
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

        $prefs         = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $founderNumber = trim($prefs['daily_report_founder_number'] ?? '');
        $hrNumber      = trim($prefs['daily_report_hr_number'] ?? '');
        $templateId    = $prefs['daily_report_template_id'] ?? null;

        $recipients = array_filter([$founderNumber, $hrNumber]);
        if (empty($recipients)) {
            return;
        }
        if (!$templateId) {
            log_message('warning', "[DailyReportSender] account {$accountId} has recipients but no template selected — skipping");
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

        $stats = $this->gatherStats($accountId);

        $variables = [
            'body_1' => date('d M Y'),
            'body_2' => (string) $stats['new_leads'],
            'body_3' => (string) $stats['messages_sent'],
            'body_4' => (string) $stats['messages_received'],
            'body_5' => (string) $stats['appointments_booked'],
        ];

        $components  = TemplateSendBuilder::buildComponents($template, $variables);
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
        $metaApi     = new MetaApi();

        foreach ($recipients as $number) {
            $normalized = \App\Libraries\WhatsApp\PhoneUtils::normalize($number);
            if (!$normalized) continue;

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
                log_message('error', "[DailyReportSender] send to {$number} failed: " . $e->getMessage());
            }
        }
    }

    private function gatherStats(string $accountId): array
    {
        $today = date('Y-m-d');

        $newLeads = (new ContactModel())
            ->where('account_id', $accountId)
            ->where('created_at >=', $today . ' 00:00:00')
            ->countAllResults();

        $messagesSent = (new MessageModel())
            ->where('account_id', $accountId)
            ->where('sender_type', 'agent')
            ->where('created_at >=', $today . ' 00:00:00')
            ->countAllResults();

        $messagesReceived = (new MessageModel())
            ->where('account_id', $accountId)
            ->where('sender_type', 'customer')
            ->where('created_at >=', $today . ' 00:00:00')
            ->countAllResults();

        $appointmentsBooked = (new AppointmentModel())
            ->where('account_id', $accountId)
            ->where('created_at >=', $today . ' 00:00:00')
            ->countAllResults();

        return [
            'new_leads'           => $newLeads,
            'messages_sent'       => $messagesSent,
            'messages_received'  => $messagesReceived,
            'appointments_booked' => $appointmentsBooked,
        ];
    }
}
