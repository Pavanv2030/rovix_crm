<?php

namespace App\Libraries\WhatsApp;

class TemplateSendBuilder
{
    public static function buildComponents(array $template, array $variables): array
    {
        $components = [];

        // Header component — media (image/video/document) headers normally
        // have no stored header_content at all (unlike text headers, WhatsApp
        // doesn't let a media header be "baked in"); it's only ever supplied
        // per-send via $variables['header_url']. Gating this purely on
        // header_content being non-empty meant the whole header component
        // silently never got sent for any media-header template that had no
        // stored content — Meta then rejects the send as missing the header
        // the template requires, reported as "expected IMAGE, received UNKNOWN".
        $hasHeaderValue = !empty($template['header_content']) || !empty($variables['header_url']) || !empty($variables['header']);
        if ($template['header_type'] !== 'none' && $hasHeaderValue) {
            $header = ['type' => 'header', 'parameters' => []];

            if ($template['header_type'] === 'text') {
                $header['parameters'][] = [
                    'type' => 'text',
                    'text' => $variables['header'] ?? $template['header_content'],
                ];
            } elseif (in_array($template['header_type'], ['image', 'video', 'document'])) {
                $header['parameters'][] = [
                    'type'                   => $template['header_type'],
                    $template['header_type'] => ['link' => $variables['header_url'] ?? $template['header_content']],
                ];
            }

            $components[] = $header;
        }

        // Body component
        $bodyParams = [];
        preg_match_all('/\{\{(\d+)\}\}/', $template['body_text'], $matches);

        foreach ($matches[1] as $index) {
            $bodyParams[] = [
                'type' => 'text',
                'text' => $variables['body_' . $index] ?? '',
            ];
        }

        if (!empty($bodyParams)) {
            $components[] = ['type' => 'body', 'parameters' => $bodyParams];
        }

        return $components;
    }
}
