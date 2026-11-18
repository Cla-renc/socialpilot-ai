<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$expectedTables = (int)($argv[1] ?? 0);
$expectedMigs = (int)($argv[2] ?? 0);
$expectedLastMig = $argv[3] ?? '';
$expectedNextMig = $argv[4] ?? '';

echo "=== CHECKPOINT VERIFICATION (Expected: {$expectedTables} tables, {$expectedMigs} migs) ===" . PHP_EOL;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

// 1. Identity
$ctx = $pdo->query("SELECT DATABASE() AS db, USER() AS usr")->fetch(PDO::FETCH_ASSOC);
echo "Active DB: {$ctx['db']}" . PHP_EOL;
echo "Active User: {$ctx['usr']}" . PHP_EOL;
if ($ctx['db'] !== 'socialpilot_staging' || !str_starts_with($ctx['usr'], 'AZDARrqgy4MBFGE.staging_migrator')) {
    die("FATAL: Identity check failed!" . PHP_EOL);
}

// 2. TLS
$ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);
$cipher = $ssl['Value'] ?? '';
echo "TLS Cipher: {$cipher}" . PHP_EOL;
if (empty($cipher)) {
    die("FATAL: TLS check failed!" . PHP_EOL);
}

// 3. Table Count
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$tableCount = count($tables);
echo "Current Table Count: {$tableCount}" . PHP_EOL;
if ($tableCount !== $expectedTables) {
    die("FATAL: Table count mismatch! Expected {$expectedTables}, got {$tableCount}." . PHP_EOL);
}

// 4. Migration Records Count
$migs = $pdo->query("SELECT id, migration, batch FROM migrations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$migCount = count($migs);
echo "Completed Migrations: {$migCount}" . PHP_EOL;
if ($migCount !== $expectedMigs) {
    die("FATAL: Migration record count mismatch! Expected {$expectedMigs}, got {$migCount}." . PHP_EOL);
}

// 5. Expected Migration Filename
$lastMig = end($migs);
echo "Last Recorded Migration: {$lastMig['migration']} (Batch: {$lastMig['batch']})" . PHP_EOL;
if ($lastMig['migration'] !== $expectedLastMig) {
    die("FATAL: Last migration mismatch! Expected {$expectedLastMig}, got {$lastMig['migration']}." . PHP_EOL);
}

// 6. Confirm Next Migration Pending
if (!empty($expectedNextMig)) {
    $found = false;
    foreach ($migs as $m) {
        if ($m['migration'] === $expectedNextMig) {
            $found = true;
            break;
        }
    }
    if ($found) {
        die("FATAL: Next migration '{$expectedNextMig}' is already recorded!" . PHP_EOL);
    }
    echo "✓ Next Migration '{$expectedNextMig}' is confirmed Pending." . PHP_EOL;
}

echo "✓ Checkpoint PASSED." . PHP_EOL;
