<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\ActivityLogModel;
use App\Models\WhatsAppConfigModel;
use App\Models\AiConfigModel;
use App\Models\AiUsageLogModel;
use App\Models\MessageTemplateModel;
use App\Libraries\WhatsApp\Encryption;

class SettingsController extends BaseController
{
    public function index()
    {
        if (!can_edit_settings()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin required.');
        }

        $account = (new AccountModel())->find(session('account_id'));

        return view('settings/index', [
            'pageTitle' => 'Settings',
            'account'   => $account,
            'activeTab' => 'account',
        ]);
    }

    public function updateAccount()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $rules = [
            'name'     => 'required|min_length[2]',
            'timezone' => 'required',
        ];
        if (!$this->validate($rules)) {
            return $this->response->setJSON(['errors' => $this->validator->getErrors()])->setStatusCode(400);
        }

        (new AccountModel())->update(session('account_id'), [
            'name'     => trim($this->request->getPost('name')),
            'timezone' => $this->request->getPost('timezone'),
        ]);

        ActivityLogModel::record('settings.account_updated', 'account', session('account_id'));

        return $this->response->setJSON(['success' => true]);
    }

    public function whatsapp()
    {
        if (!can_edit_settings()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin required.');
        }

        $account  = (new AccountModel())->find(session('account_id'));
        $waConfig = (new WhatsAppConfigModel())->first();

        if ($waConfig && !empty($waConfig['access_token'])) {
            try {
                $decrypted = (new Encryption())->decrypt($waConfig['access_token']);
                $waConfig['access_token_masked'] = substr($decrypted, 0, 8) . str_repeat('•', 20) . substr($decrypted, -4);
            } catch (\Throwable $e) {
                $waConfig['access_token_masked'] = '(unable to decode)';
            }
        }

        return view('settings/index', [
            'pageTitle' => 'Settings — WhatsApp',
            'account'   => $account,
            'waConfig'  => $waConfig,
            'activeTab' => 'whatsapp',
        ]);
    }

    public function updateWhatsApp()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $waConfigModel = new WhatsAppConfigModel();
        $existing      = $waConfigModel->first();

        $rules = [
            'phone_number_id'      => 'required',
            'waba_id'              => 'required',
            'webhook_verify_token' => 'required',
        ];
        // Only require access_token if no config exists yet
        if (!$existing) {
            $rules['access_token'] = 'required';
        }

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['errors' => $this->validator->getErrors()])->setStatusCode(400);
        }

        $data = [
            'phone_number_id'      => trim($this->request->getPost('phone_number_id')),
            'waba_id'              => trim($this->request->getPost('waba_id')),
            'webhook_verify_token' => trim($this->request->getPost('webhook_verify_token')),
            'status'               => 'connected',
        ];

        $newToken = trim($this->request->getPost('access_token') ?? '');
        if ($newToken !== '') {
            $data['access_token'] = (new Encryption())->encrypt($newToken);
        }

        // Auto-fix the common mistake of pasting Phone Number ID into WABA ID.
        if ($data['waba_id'] === $data['phone_number_id']) {
            try {
                $token = $newToken !== ''
                    ? $newToken
                    : (new Encryption())->decrypt($existing['access_token'] ?? '');
                $data['waba_id'] = (new \App\Libraries\WhatsApp\MetaApi())
                    ->resolveWabaId($data['phone_number_id'], $token);
            } catch (\Throwable $e) {
                return $this->response->setJSON([
                    'error' => 'WABA ID cannot be the same as Phone Number ID. '
                        . 'Enter your WhatsApp Business Account ID from Meta API Setup.',
                ])->setStatusCode(400);
            }
        }

        if ($existing) {
            $waConfigModel->update($existing['id'], $data);
        } else {
            $data['id']         = generate_uuid();
            $data['account_id'] = session('account_id');
            $waConfigModel->insert($data);
        }

        ActivityLogModel::record('settings.whatsapp_updated', 'account', session('account_id'));

        return $this->response->setJSON(['success' => true]);
    }

    public function sendDemoReport()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $phone = trim($this->request->getPost('phone') ?? '');
        if ($phone === '') {
            return $this->response->setJSON(['error' => 'Phone number required'])->setStatusCode(400);
        }

        $normalized = \App\Libraries\WhatsApp\PhoneUtils::normalize($phone);
        if (strlen($normalized) === 10) {
            $normalized = '91' . $normalized;
        }

        $accountId = session('account_id');
        $waConfig  = (new WhatsAppConfigModel())->where('account_id', $accountId)->first();
        if (!$waConfig || ($waConfig['status'] ?? '') !== 'connected') {
            return $this->response->setJSON(['error' => 'WhatsApp not connected'])->setStatusCode(400);
        }

        $account = (new AccountModel())->find($accountId);
        $digest  = new \App\Libraries\DailyDigestService();
        $stats   = $digest->generateStats($accountId);
        $message = $digest->formatMessage($stats, 'founder');
        $message = "[DEMO PREVIEW]\n\n" . $message;

        $metaApi     = new \App\Libraries\WhatsApp\MetaApi();
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        try {
            $result = $metaApi->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $normalized,
                $message
            );

            ActivityLogModel::record('settings.demo_report_sent', 'account', $accountId, [
                'phone' => $normalized,
            ]);

            return $this->response->setJSON([
                'success'    => true,
                'message'    => 'Demo report sent via WhatsApp',
                'message_id' => $result['messages'][0]['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $template = $this->resolveReportTemplate($account);

            if (!$template) {
                log_message('error', 'Demo report send failed: ' . $e->getMessage());
                return $this->response->setJSON([
                    'error' => $e->getMessage(),
                    'hint'  => 'Select an approved daily report template in Settings → Notifications.',
                ])->setStatusCode(500);
            }

            try {
                $variables  = $digest->formatTemplateVariables($stats, 'founder');
                $variables['body_1'] = '[DEMO] ' . $variables['body_1'];
                $components = \App\Libraries\WhatsApp\TemplateSendBuilder::buildComponents($template, $variables);
                $result     = $metaApi->sendTemplate(
                    $waConfig['phone_number_id'],
                    $accessToken,
                    $normalized,
                    $template['name'],
                    $template['language'] ?? 'en',
                    $components
                );

                return $this->response->setJSON([
                    'success'    => true,
                    'message'    => 'Demo report sent via template (' . $template['name'] . ')',
                    'message_id' => $result['messages'][0]['id'] ?? null,
                ]);
            } catch (\Throwable $e2) {
                log_message('error', 'Demo report template send failed: ' . $e2->getMessage());
                return $this->response->setJSON(['error' => $e2->getMessage()])->setStatusCode(500);
            }
        }
    }

    public function testWhatsApp()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $testPhone = trim($this->request->getPost('test_phone') ?? '');
        if (!$testPhone) {
            return $this->response->setJSON(['error' => 'Phone number required'])->setStatusCode(400);
        }

        $waConfig = (new WhatsAppConfigModel())->first();
        if (!$waConfig) {
            return $this->response->setJSON(['error' => 'WhatsApp not configured yet'])->setStatusCode(400);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            (new \App\Libraries\WhatsApp\MetaApi())->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $testPhone,
                'Test message from Rovix AI ✓'
            );

            ActivityLogModel::record('settings.whatsapp_test_sent', 'account', session('account_id'), [
                'test_phone' => $testPhone,
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Test message sent successfully']);
        } catch (\Throwable $e) {
            log_message('error', 'WhatsApp test failed: ' . $e->getMessage());
            return $this->response->setJSON(['error' => $e->getMessage()])->setStatusCode(500);
        }
    }

    private function formatMetaFetchError(string $message): string
    {
        if (str_contains($message, 'API access blocked')) {
            return 'Meta has blocked API access for this app/token. '
                . 'Go to developers.facebook.com → your app → WhatsApp → API Setup → Generate a NEW access token. '
                . 'Paste the full token in the Access Token field above (do not leave blank), click Save Configuration, then Fetch again. '
                . 'Also check business.facebook.com → WhatsApp Manager for verification or payment warnings.';
        }

        if (str_contains($message, '401') || str_contains($message, '190') || str_contains($message, 'OAuthException')) {
            return 'Invalid or expired access token. Generate a new token in Meta → WhatsApp → API Setup, paste it in the Access Token field (required — leaving blank keeps the old token), Save, then Fetch again.';
        }

        return $message;
    }

    private function deriveAccountMode(array $data): ?string
    {
        $platform = strtoupper((string) ($data['platform_type'] ?? ''));
        if ($platform === 'CLOUD_API' || $platform === 'NOT_APPLICABLE') {
            return 'LIVE';
        }

        $status = strtoupper((string) ($data['status'] ?? ''));
        if ($status === 'CONNECTED') {
            return 'LIVE';
        }

        return $platform !== '' ? $platform : ($status !== '' ? $status : null);
    }

    public function fetchNumberInfo()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $waConfig = (new WhatsAppConfigModel())->first();
        if (!$waConfig) {
            return $this->response->setJSON(['error' => 'WhatsApp not configured'])->setStatusCode(400);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $data        = (new \App\Libraries\WhatsApp\MetaApi())->getPhoneNumberInfo(
                $waConfig['phone_number_id'],
                $accessToken
            );

            $accountMode = $this->deriveAccountMode($data);

            $waConfigModel = new WhatsAppConfigModel();
            $waConfigModel->update($waConfig['id'], [
                'display_phone_number'    => $data['display_phone_number'] ?? null,
                'verified_name'           => $data['verified_name']        ?? null,
                'quality_rating'          => $data['quality_rating']       ?? null,
                'name_status'             => $data['name_status']          ?? null,
                'account_mode'            => $accountMode,
                'number_info_fetched_at'  => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON([
                'success'              => true,
                'display_phone_number' => $data['display_phone_number'] ?? null,
                'verified_name'        => $data['verified_name']        ?? null,
                'quality_rating'       => $data['quality_rating']       ?? 'UNKNOWN',
                'name_status'          => $data['name_status']          ?? null,
                'account_mode'         => $accountMode,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'fetchNumberInfo failed: ' . $e->getMessage());
            $message = $this->formatMetaFetchError($e->getMessage());
            return $this->response->setJSON(['error' => $message])->setStatusCode(400);
        }
    }

    public function ai()
    {
        if (!can_edit_settings()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin required.');
        }

        $account  = (new AccountModel())->find(session('account_id'));
        $aiConfig = (new AiConfigModel())->where('account_id', session('account_id'))->first();

        if ($aiConfig && !empty($aiConfig['api_key'])) {
            try {
                $decrypted = (new Encryption())->decrypt($aiConfig['api_key']);
                $aiConfig['api_key_masked'] = substr($decrypted, 0, 6) . str_repeat('•', 20) . substr($decrypted, -4);
            } catch (\Throwable $e) {
                $aiConfig['api_key_masked'] = '(unable to decode)';
            }
        }

        $usageModel = new AiUsageLogModel();
        $usageTotals = $usageModel->db->table('ai_usage_log')
            ->select('COALESCE(SUM(total_tokens),0) as total_tokens, COALESCE(SUM(cost_estimate),0) as total_cost')
            ->where('account_id', session('account_id'))
            ->get()->getRowArray();

        $usageByFeature = $usageModel->db->table('ai_usage_log')
            ->select('feature, COUNT(*) as calls, SUM(total_tokens) as tokens, SUM(cost_estimate) as cost')
            ->where('account_id', session('account_id'))
            ->groupBy('feature')
            ->orderBy('cost', 'DESC')
            ->get()->getResultArray();

        return view('settings/index', [
            'pageTitle'       => 'Settings — AI',
            'account'         => $account,
            'aiConfig'        => $aiConfig,
            'usageTotals'     => $usageTotals,
            'usageByFeature'  => $usageByFeature,
            'activeTab'       => 'ai',
        ]);
    }

    public function updateAi()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $aiConfigModel = new AiConfigModel();
        $existing      = $aiConfigModel->where('account_id', session('account_id'))->first();

        if (!$existing && trim($this->request->getPost('api_key') ?? '') === '') {
            return $this->response->setJSON(['errors' => ['api_key' => 'API key is required']])->setStatusCode(400);
        }

        $data = [
            'provider' => 'openai',
            'model'    => $this->request->getPost('model') ?: 'gpt-4o-mini',
        ];

        $newKey = trim($this->request->getPost('api_key') ?? '');
        if ($newKey !== '') {
            $data['api_key'] = (new Encryption())->encrypt($newKey);
        }

        if ($existing) {
            $aiConfigModel->update($existing['id'], $data);
        } else {
            $data['id']         = generate_uuid();
            $data['account_id'] = session('account_id');
            $aiConfigModel->insert($data);
        }

        ActivityLogModel::record('settings.ai_updated', 'account', session('account_id'));

        return $this->response->setJSON(['success' => true]);
    }

    public function notifications()
    {
        if (!can_edit_settings()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin required.');
        }

        $account     = (new AccountModel())->find(session('account_id'));
        $notifPrefs  = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $templates   = (new MessageTemplateModel())->where('status', 'approved')->findAll();

        return view('settings/index', [
            'pageTitle'   => 'Settings — Notifications',
            'account'     => $account,
            'notifPrefs'  => $notifPrefs,
            'templates'   => $templates,
            'activeTab'   => 'notifications',
        ]);
    }

    public function updateNotifications()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $ownerPhone = trim($this->request->getPost('owner_whatsapp_number') ?? '');

        $prefs = [
            'email_new_message'        => (bool)$this->request->getPost('email_new_message'),
            'email_broadcast_complete' => (bool)$this->request->getPost('email_broadcast_complete'),
            'email_daily_summary'      => (bool)$this->request->getPost('email_daily_summary'),
            'email_weekly_report'      => (bool)$this->request->getPost('email_weekly_report'),
            'owner_whatsapp_number'    => $ownerPhone,
            'daily_report_founder_number' => trim($this->request->getPost('daily_report_founder_number') ?? ''),
            'daily_report_hr_number'      => trim($this->request->getPost('daily_report_hr_number') ?? ''),
            'daily_report_founder_email'  => trim($this->request->getPost('daily_report_founder_email') ?? ''),
            'daily_report_hr_email'       => trim($this->request->getPost('daily_report_hr_email') ?? ''),
            'daily_report_time'           => $this->request->getPost('daily_report_time') ?: '08:00',
            'daily_report_template_id'    => $this->request->getPost('daily_report_template_id') ?: null,
        ];

        (new AccountModel())->update(session('account_id'), [
            'notification_preferences' => json_encode($prefs),
        ]);

        ActivityLogModel::record('settings.notifications_updated', 'account', session('account_id'));

        return $this->response->setJSON(['success' => true]);
    }

    public function apiKeys()
    {
        if (!can_edit_settings()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin required.');
        }

        $accountModel = new AccountModel();
        $account      = $accountModel->find(session('account_id'));

        if (empty($account['api_key'])) {
            $apiKey = bin2hex(random_bytes(32));
            $accountModel->update(session('account_id'), ['api_key' => $apiKey]);
            $account['api_key'] = $apiKey;
        }

        return view('settings/index', [
            'pageTitle' => 'Settings — API Keys',
            'account'   => $account,
            'activeTab' => 'api',
        ]);
    }

    public function regenerateApiKey()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $apiKey = bin2hex(random_bytes(32));
        (new AccountModel())->update(session('account_id'), ['api_key' => $apiKey]);

        ActivityLogModel::record('settings.api_key_regenerated', 'account', session('account_id'));

        return $this->response->setJSON(['success' => true, 'api_key' => $apiKey]);
    }

    public function webhooks()
    {
        if (!can_edit_settings()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin required.');
        }

        $account     = (new AccountModel())->find(session('account_id'));
        $webhookLogs = \Config\Database::connect()
            ->table('webhook_logs')
            ->where('account_id', session('account_id'))
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        return view('settings/index', [
            'pageTitle'   => 'Settings — Webhooks',
            'account'     => $account,
            'webhookLogs' => $webhookLogs,
            'activeTab'   => 'webhooks',
        ]);
    }

    public function tags()
    {
        return view('settings/tags', ['pageTitle' => 'Tags']);
    }

    public function leadStatuses()
    {
        $templates = (new MessageTemplateModel())->where('status', 'approved')->findAll();
        foreach ($templates as &$t) {
            $t['needs_header_url'] = in_array($t['header_type'] ?? 'none', ['image', 'video', 'document'], true);
        }
        unset($t);

        return view('settings/lead_statuses', [
            'pageTitle' => 'Lead Statuses',
            'templates' => $templates,
        ]);
    }

    public function customFields()
    {
        return view('settings/custom_fields', ['pageTitle' => 'Custom Fields']);
    }

    private function resolveReportTemplate(array $account): ?array
    {
        $prefs      = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $templateId = $prefs['daily_report_template_id'] ?? null;

        if ($templateId) {
            $template = (new MessageTemplateModel())->find($templateId);
            if ($template) {
                return $template;
            }
        }

        return (new MessageTemplateModel())
            ->where('account_id', $account['id'])
            ->where('status', 'approved')
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    public function previewDailyReport()
    {
        if (!can_edit_settings()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin required.');
        }

        $role   = $this->request->getGet('role') === 'hr' ? 'hr' : 'founder';
        $digest = new \App\Libraries\DailyDigestService();
        $stats  = $digest->generateStats(session('account_id'));

        return $this->response
            ->setContentType('text/html')
            ->setBody($digest->renderEmailHtml($stats, $role));
    }
}
