<?php

namespace App\Libraries\WhatsApp;

use App\Models\WhatsAppConfigModel;

class TemplateSubmitter
{
    private MetaApi $metaApi;
    private Encryption $encryption;

    public function __construct()
    {
        $this->metaApi    = new MetaApi();
        $this->encryption = new Encryption();
    }

    public function submit(array $template, string $accountId): string
    {
        $waConfig = (new WhatsAppConfigModel())->where('account_id', $accountId)->first();
        if (!$waConfig) throw new \Exception('WhatsApp not connected');

        $accessToken = $this->encryption->decrypt($waConfig['access_token']);
        $wabaId      = $waConfig['waba_id'];

        $payload  = [
            'name'       => $template['name'],
            'language'   => $template['language'],
            'category'   => strtoupper($template['category']),
            'components' => $this->buildComponents($template, $accessToken),
        ];

        // http_errors must be false — otherwise CURLRequest throws its own
        // generic exception on 4xx/5xx BEFORE we can read Meta's actual
        // error body, hiding the real validation reason from the user.
        $client   = \Config\Services::curlrequest(['timeout' => 30, 'http_errors' => false]);
        $response = $client->post(
            "https://graph.facebook.com/" . MetaApi::GRAPH_API_VERSION . "/{$wabaId}/message_templates",
            [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
                'json'    => $payload,
            ]
        );

        $result = json_decode($response->getBody(), true);

        if ($response->getStatusCode() >= 400) {
            log_message('error', 'Template submission error: ' . $response->getBody());
            // error_user_msg is Meta's human-readable explanation when present —
            // more actionable than the generic OAuthException message.
            $msg = $result['error']['error_user_msg']
                ?? $result['error']['message']
                ?? ('HTTP ' . $response->getStatusCode());
            throw new \Exception($msg);
        }

        return $result['id'];
    }

    private function buildComponents(array $template, string $accessToken): array
    {
        $components = [];
        $headerType = $template['header_type'] ?? 'none';

        if ($headerType !== 'none' && !empty($template['header_content'])) {
            $header = ['type' => 'HEADER'];
            if ($headerType === 'text') {
                $header['format'] = 'TEXT';
                $header['text']   = $template['header_content'];
            } elseif (in_array($headerType, ['image', 'video', 'document'], true)) {
                // Meta requires the sample media to be uploaded to their
                // Resumable Upload API first — a raw external URL is
                // rejected with error 100 ("Missing sample parameter").
                $appId = env('META_APP_ID');
                if (empty($appId)) {
                    throw new \Exception(
                        'META_APP_ID is not configured in .env. Get it from '
                        . 'developers.facebook.com → your app → Settings → Basic → App ID.'
                    );
                }
                $handle = $this->metaApi->uploadTemplateMedia($appId, $accessToken, trim($template['header_content']));
                $header['format']  = strtoupper($headerType);
                $header['example'] = ['header_handle' => [$handle]];
            } else {
                // carousel or other future types: pass through as-is
                $header['format']  = strtoupper($headerType);
                $header['example'] = ['header_handle' => [$template['header_content']]];
            }
            $components[] = $header;
        }

        $body = ['type' => 'BODY', 'text' => $template['body_text']];
        if (!empty($template['sample_values'])) {
            $samples = json_decode($template['sample_values'], true);
            if (!empty($samples['body'])) {
                $body['example'] = ['body_text' => [$samples['body']]];
            }
        }
        $components[] = $body;

        if (!empty($template['footer_text'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $template['footer_text']];
        }

        if (!empty($template['buttons'])) {
            $buttons    = json_decode($template['buttons'], true);
            $buttonList = [];
            foreach ($buttons as $btn) {
                // Stray whitespace (copy-paste, accidental leading space) makes
                // Meta reject phone_number/url as invalid — trim defensively.
                if ($btn['type'] === 'QUICK_REPLY') {
                    $buttonList[] = ['type' => 'QUICK_REPLY', 'text' => trim($btn['text'])];
                } elseif ($btn['type'] === 'URL') {
                    $buttonList[] = ['type' => 'URL', 'text' => trim($btn['text']), 'url' => trim($btn['url'])];
                } elseif ($btn['type'] === 'PHONE_NUMBER') {
                    $buttonList[] = ['type' => 'PHONE_NUMBER', 'text' => trim($btn['text']), 'phone_number' => trim($btn['phone'])];
                }
            }
            if ($buttonList) $components[] = ['type' => 'BUTTONS', 'buttons' => $buttonList];
        }

        return $components;
    }
}
