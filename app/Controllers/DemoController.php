<?php

namespace App\Controllers;

use App\Libraries\DailyDigestService;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\PhoneUtils;
use App\Libraries\WhatsApp\TemplateSendBuilder;
use App\Models\AccountModel;
use App\Models\MessageTemplateModel;
use App\Models\WhatsAppConfigModel;

class DemoController extends BaseController
{
    public function sendReport()
    {
        $key      = $this->request->getGet('key') ?? '';
        $phone    = trim($this->request->getGet('phone') ?? '');
        $expected = env('rovix.demoReportKey', '');

        if ($expected === '' || !hash_equals($expected, $key)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        if ($phone === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'phone required']);
        }

        $normalized = PhoneUtils::normalize($phone);
        if (strlen($normalized) === 10) {
            $normalized = '91' . $normalized;
        }

        // setBypassAccountScope is a GLOBAL flag. Every early return below used
        // to skip the reset, leaving account scoping disabled for the rest of
        // the request. runUnscoped() resets it in a finally.
        $account = \App\Models\BaseModel::runUnscoped(
            static fn () => (new AccountModel())->first()
        );
        if (!$account) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'No account']);
        }

        $waConfig = \App\Models\BaseModel::runUnscoped(
            static fn () => (new WhatsAppConfigModel())->where('account_id', $account['id'])->first()
        );
        if (!$waConfig || ($waConfig['status'] ?? '') !== 'connected') {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'WhatsApp not connected']);
        }

        $digest  = new DailyDigestService();
        $stats   = $digest->generateStats($account['id']);
        $message = "[DEMO PREVIEW]\n\n" . $digest->formatMessage($stats, 'founder');

        $metaApi     = new MetaApi();
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        try {
            $result = $metaApi->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $normalized,
                $message
            );

            return $this->response->setJSON([
                'success'    => true,
                'method'     => 'text',
                'to'         => $normalized,
                'message_id' => $result['messages'][0]['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $template = $this->resolveReportTemplate($account);

            if (!$template) {
                return $this->response->setStatusCode(500)->setJSON([
                    'error' => $e->getMessage(),
                    'hint'  => 'Configure a daily report template in Settings → Notifications, or create an approved Meta template first.',
                ]);
            }

            try {
                $variables  = $digest->formatTemplateVariables($stats, 'founder');
                $variables['body_1'] = '[DEMO] ' . $variables['body_1'];
                $components = TemplateSendBuilder::buildComponents($template, $variables);
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
                    'method'     => 'template',
                    'template'   => $template['name'],
                    'to'         => $normalized,
                    'message_id' => $result['messages'][0]['id'] ?? null,
                ]);
            } catch (\Throwable $e2) {
                return $this->response->setStatusCode(500)->setJSON(['error' => $e2->getMessage()]);
            }
        }
    }

    private function resolveReportTemplate(array $account): ?array
    {
        $prefs      = json_decode($account['notification_preferences'] ?? '{}', true) ?? [];
        $templateId = $prefs['daily_report_template_id'] ?? null;

        // Scoping is bypassed deliberately here (the demo endpoint is public and
        // has no session), but each lookup is constrained to $account explicitly.
        return \App\Models\BaseModel::runUnscoped(static function () use ($templateId, $account) {
            if ($templateId) {
                $template = (new MessageTemplateModel())
                    ->where('id', $templateId)
                    ->where('account_id', $account['id'])
                    ->first();
                if ($template) {
                    return $template;
                }
            }

            return (new MessageTemplateModel())
                ->where('account_id', $account['id'])
                ->where('status', 'approved')
                ->orderBy('updated_at', 'DESC')
                ->first();
        });
    }
}