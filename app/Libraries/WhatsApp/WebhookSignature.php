<?php

namespace App\Libraries\WhatsApp;

class WebhookSignature
{
    public static function verify(string $rawBody, string $signature, string $appSecret): bool
    {
        if (empty($signature) || empty($appSecret)) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expectedSignature, $signature);
    }
}
