<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\AccountModel;
use App\Models\WhatsAppConfigModel;
use App\Libraries\DailyDigestService;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class SendDailyDigest extends BaseCommand
{
    protected $group       = 'CRM';
    protected $name        = 'crm:send-daily-digest';
    protected $description = 'Send daily digest report to managers via WhatsApp (manual test)';

    public function run(array $params)
    {
        $accountModel = new AccountModel();
        $accounts     = $accountModel->findAll();

        $digestService = new DailyDigestService();
        $waConfigModel = new WhatsAppConfigModel();
        $metaApi       = new MetaApi();
        $encryption    = new Encryption();

        $sent   = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            $accountName = $account['name'] ?? 'Account #' . $account['id'];
            CLI::write("Processing account: {$accountName} (ID: {$account['id']})", 'yellow');

            $waConfig = $waConfigModel->where('account_id', $account['id'])->first();
            if (!$waConfig) {
                CLI::write('  No WhatsApp configured, skipping', 'red');
                continue;
            }

            $recipients = $this->getRecipients($account);
            if (empty($recipients)) {
                CLI::write('  No founder/HR numbers configured, skipping', 'red');
                continue;
            }

            CLI::write('  Recipients found: ' . implode(', ', array_keys($recipients)), 'cyan');

            try {
                $stats = $digestService->generateStats($account['id']);
            } catch (\Exception $e) {
                CLI::write('  Stats generation failed: ' . $e->getMessage(), 'red');
                $failed++;
                continue;
            }

            $accessToken   = $encryption->decrypt($waConfig['access_token']);
            $phoneNumberId = $waConfig['phone_number_id'];

            foreach ($recipients as $role => $recipient) {
                try {
                    $message = $digestService->formatMessage($stats, $role);
                    $metaApi->sendText($phoneNumberId, $accessToken, $recipient, $message);
                    CLI::write("  Sent to {$role} ({$recipient})", 'green');
                    $sent++;
                } catch (\Exception $e) {
                    log_message('error', "Digest send failed to {$recipient}: " . $e->getMessage());
                    CLI::write("  Failed to send to {$role}: " . $e->getMessage(), 'red');
                    $failed++;
                }
            }
        }

        CLI::newLine();
        CLI::write("Summary: {$sent} sent, {$failed} failed", $failed > 0 ? 'yellow' : 'green');
    }

    private function getRecipients(array $account): array
    {
        $prefs = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $recipients = [];

        foreach (['founder' => 'daily_report_founder_number', 'hr' => 'daily_report_hr_number'] as $role => $key) {
            $phone = trim($prefs[$key] ?? '');
            if ($phone === '') {
                continue;
            }

            $normalized = \App\Libraries\WhatsApp\PhoneUtils::normalize($phone);
            if ($normalized) {
                $recipients[$role] = $normalized;
            }
        }

        return $recipients;
    }
}