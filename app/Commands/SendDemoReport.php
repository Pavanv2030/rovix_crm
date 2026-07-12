<?php

namespace App\Commands;

use App\Libraries\DailyDigestService;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\PhoneUtils;
use App\Libraries\WhatsApp\TemplateSendBuilder;
use App\Models\AccountModel;
use App\Models\MessageTemplateModel;
use App\Models\WhatsAppConfigModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SendDemoReport extends BaseCommand
{
    protected $group       = 'CRM';
    protected $name        = 'crm:send-demo-report';
    protected $description = 'Send a demo daily executive report to a WhatsApp number';
    protected $usage       = 'crm:send-demo-report <phone> [account_id]';

    public function run(array $params)
    {
        $phone = $params[0] ?? '';
        if ($phone === '') {
            CLI::error('Usage: php spark crm:send-demo-report <phone> [account_id]');
            return;
        }

        $normalized = PhoneUtils::normalize($phone);
        if (strlen($normalized) === 10) {
            $normalized = '91' . $normalized;
        }

        $accountModel = new AccountModel();
        $accountId    = $params[1] ?? null;

        if ($accountId) {
            $account = $accountModel->find($accountId);
        } else {
            $account = $accountModel->first();
        }

        if (!$account) {
            CLI::error('No account found.');
            return;
        }

        \App\Models\BaseModel::setBypassAccountScope(true);

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $account['id'])->first();
        if (!$waConfig || ($waConfig['status'] ?? '') !== 'connected') {
            CLI::error('WhatsApp not connected for account ' . $account['id']);
            return;
        }

        $digest = new DailyDigestService();
        $stats  = $digest->generateStats($account['id']);
        $message = $digest->formatMessage($stats, 'founder');

        $encryption  = new Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);
        $metaApi     = new MetaApi();

        CLI::write("Account: {$account['name']} ({$account['id']})", 'yellow');
        CLI::write("Sending to: {$normalized}", 'cyan');

        try {
            $result = $metaApi->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $normalized,
                $message
            );
            CLI::write('Demo report sent via text message.', 'green');
            CLI::write('Message ID: ' . ($result['messages'][0]['id'] ?? 'n/a'), 'green');
            return;
        } catch (\Throwable $e) {
            CLI::write('Text send failed: ' . $e->getMessage(), 'yellow');
        }

        $prefs      = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $templateId = $prefs['daily_report_template_id'] ?? null;

        if (!$templateId) {
            CLI::error('No approved daily report template configured. Text failed and template unavailable.');
            return;
        }

        $template = (new MessageTemplateModel())->find($templateId);
        if (!$template) {
            CLI::error('Template not found.');
            return;
        }

        try {
            $variables  = $digest->formatTemplateVariables($stats, 'founder');
            $components = TemplateSendBuilder::buildComponents($template, $variables);
            $result     = $metaApi->sendTemplate(
                $waConfig['phone_number_id'],
                $accessToken,
                $normalized,
                $template['name'],
                $template['language'] ?? 'en',
                $components
            );
            CLI::write('Demo report sent via template: ' . $template['name'], 'green');
            CLI::write('Message ID: ' . ($result['messages'][0]['id'] ?? 'n/a'), 'green');
        } catch (\Throwable $e) {
            CLI::error('Template send failed: ' . $e->getMessage());
        }
    }
}