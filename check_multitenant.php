<?php

/**
 * Multitenant DB health check (no framework bootstrap needed).
 * Run: C:\xampp\php\php.exe C:\xampp\htdocs\rovix-crm\check_multitenant.php
 */

$envFile = __DIR__ . '/.env';
$env     = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    if (str_contains($line, '=')) {
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B'\"");
    }
}

$host = $env['database.default.hostname'] ?? 'localhost';
$db   = $env['database.default.database'] ?? 'rovix_crm';
$user = $env['database.default.username'] ?? 'root';
$pass = $env['database.default.password'] ?? '';
$port = (int) ($env['database.default.port'] ?? 3306);

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_error) {
    fwrite(STDERR, "DB connect failed: {$mysqli->connect_error}\n");
    exit(1);
}

$requiredTables = [
    'accounts', 'profiles', 'contacts', 'conversations', 'messages',
    'whatsapp_config', 'ci_sessions', 'password_resets', 'broadcasts', 'message_templates',
];

echo "=== MULTITENANT DB CHECK ({$db}) ===\n\n";

$missing = [];
echo "-- Required tables --\n";
foreach ($requiredTables as $table) {
    $r = $mysqli->query("SHOW TABLES LIKE '{$table}'");
    $ok = $r && $r->num_rows > 0;
    echo ($ok ? '[OK] ' : '[MISSING] ') . $table . "\n";
    if (!$ok) {
        $missing[] = $table;
    }
}

echo "\n-- Accounts --\n";
$res = $mysqli->query('SELECT id, name, owner_user_id FROM accounts');
$accounts = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $accounts[] = $row;
        echo "  {$row['name']} ({$row['id']})\n";
    }
    echo 'Total accounts: ' . count($accounts) . "\n";
}

echo "\n-- Per-account data (tenant isolation) --\n";
foreach ($accounts as $a) {
    $id   = $mysqli->real_escape_string($a['id']);
    $name = $a['name'];
    $q = fn (string $table) => (int) ($mysqli->query("SELECT COUNT(*) c FROM {$table} WHERE account_id = '{$id}'")->fetch_assoc()['c'] ?? 0);

    $profiles = $q('profiles');
    $wa       = $q('whatsapp_config');
    $convs    = $q('conversations');
    $msgs     = $q('messages');

    echo "{$name}:\n";
    echo "  profiles={$profiles}  whatsapp_config={$wa}  conversations={$convs}  messages={$msgs}\n";

    if ($wa > 0) {
        $cfg = $mysqli->query("SELECT status, phone_number_id, waba_id FROM whatsapp_config WHERE account_id = '{$id}' LIMIT 1")->fetch_assoc();
        echo "  WA: status={$cfg['status']}  phone_number_id={$cfg['phone_number_id']}  waba_id={$cfg['waba_id']}\n";
    } else {
        echo "  WA: NOT CONFIGURED for this account\n";
    }
}

echo "\n-- Sessions --\n";
if (!in_array('ci_sessions', $missing, true)) {
    $cnt = (int) ($mysqli->query('SELECT COUNT(*) c FROM ci_sessions')->fetch_assoc()['c'] ?? 0);
    echo "ci_sessions rows: {$cnt}\n";
}

echo "\n-- Recent migrations --\n";
$r = $mysqli->query('SHOW TABLES LIKE "migrations"');
if ($r && $r->num_rows > 0) {
    $m = $mysqli->query('SELECT version, class FROM migrations ORDER BY id DESC LIMIT 5');
    while ($row = $m->fetch_assoc()) {
        echo "  {$row['version']} {$row['class']}\n";
    }
} else {
    echo "  migrations table not found\n";
}

echo "\n-- account_id columns on core tables --\n";
$core = ['contacts', 'conversations', 'messages', 'whatsapp_config', 'broadcasts'];
foreach ($core as $table) {
    if (in_array($table, $missing, true)) {
        continue;
    }
    $cols = $mysqli->query("SHOW COLUMNS FROM {$table} LIKE 'account_id'");
    echo ($cols && $cols->num_rows ? '[OK] ' : '[NO account_id] ') . $table . "\n";
}

if ($missing) {
    echo "\nRESULT: FAIL — run: php spark migrate\n";
    echo 'Missing: ' . implode(', ', $missing) . "\n";
    exit(1);
}

echo "\nRESULT: OK — multitenant schema is in place\n";
$mysqli->close();