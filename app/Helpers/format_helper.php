<?php

if (!function_exists('format_phone')) {
    function format_phone(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone);
        if (strlen($cleaned) === 10) {
            return '+91' . $cleaned;
        }
        return '+' . ltrim($cleaned, '+');
    }
}

if (!function_exists('format_currency')) {
    function format_currency(float $amount, string $currency = 'INR'): string
    {
        return $currency . ' ' . number_format($amount, 2);
    }
}

if (!function_exists('format_relative_time')) {
    function format_relative_time(string $datetime): string
    {
        $now  = time();
        $then = strtotime($datetime);
        $diff = $now - $then;

        if ($diff < 60)       return 'just now';
        if ($diff < 3600)     return floor($diff / 60) . 'm ago';
        if ($diff < 86400)    return floor($diff / 3600) . 'h ago';
        if ($diff < 604800)   return floor($diff / 86400) . 'd ago';
        return date('d M Y', $then);
    }
}

if (!function_exists('generate_uuid')) {
    function generate_uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
