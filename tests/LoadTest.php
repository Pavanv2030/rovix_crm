<?php

/**
 * Simple load test — measures response time for key pages.
 *
 * Usage:
 *   php tests/LoadTest.php http://localhost/rovix-crm/public YOUR_SESSION_COOKIE
 *
 * Get your session cookie from browser DevTools → Application → Cookies → ci_session
 */

$baseUrl    = $argv[1] ?? 'http://localhost/rovix-crm/public';
$ciSession  = $argv[2] ?? '';

$endpoints = [
    '/dashboard',
    '/inbox',
    '/contacts',
    '/broadcasts',
    '/settings',
    '/team',
];

$results = [];
$pass    = 0;
$fail    = 0;

echo "\nRovix AI — Load Test\n";
echo str_repeat('─', 60) . "\n";
echo sprintf("%-35s %6s %8s %s\n", 'Endpoint', 'Status', 'Time', 'Result');
echo str_repeat('─', 60) . "\n";

foreach ($endpoints as $path) {
    $url = rtrim($baseUrl, '/') . $path;
    $start = microtime(true);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: text/html'],
        CURLOPT_COOKIE         => $ciSession ? 'ci_session=' . $ciSession : '',
    ]);
    curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $timeMs  = (int) ((microtime(true) - $start) * 1000);
    curl_close($ch);

    $ok     = $status >= 200 && $status < 400;
    $slow   = $timeMs > 2000;
    $result = $ok && !$slow ? 'PASS' : ($ok ? 'SLOW' : 'FAIL');

    if ($result === 'PASS') $pass++;
    else $fail++;

    $color = match($result) {
        'PASS'  => "\033[32m",
        'SLOW'  => "\033[33m",
        default => "\033[31m",
    };

    printf(
        "%-35s %6d %7dms %s%s\033[0m\n",
        $path, $status, $timeMs, $color, $result
    );

    $results[] = compact('path', 'status', 'timeMs', 'result');
}

echo str_repeat('─', 60) . "\n";

$total  = count($results);
$avgMs  = $total > 0 ? (int) (array_sum(array_column($results, 'timeMs')) / $total) : 0;
$maxMs  = $total > 0 ? max(array_column($results, 'timeMs')) : 0;

echo "\nSummary: {$pass}/{$total} passed | Avg: {$avgMs}ms | Max: {$maxMs}ms\n";

if ($fail > 0) {
    echo "\n\033[31mFAILED: {$fail} endpoint(s) exceeded thresholds.\033[0m\n";
    exit(1);
}

echo "\n\033[32mAll endpoints within acceptable response times.\033[0m\n";
exit(0);
