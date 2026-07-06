<?php

namespace App\Libraries\WhatsApp;

class PhoneUtils
{
    public static function normalize(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    public static function isValid(string $phone): bool
    {
        return strlen(self::normalize($phone)) >= 10;
    }

    public static function format(string $phone): string
    {
        $normalized = self::normalize($phone);

        if (str_starts_with($normalized, '91') && strlen($normalized) === 12) {
            return '+91 ' . substr($normalized, 2, 5) . ' ' . substr($normalized, 7);
        }

        if (strlen($normalized) > 10) {
            $country = substr($normalized, 0, -10);
            $rest    = substr($normalized, -10);
            return '+' . $country . ' ' . $rest;
        }

        return $normalized;
    }
}
