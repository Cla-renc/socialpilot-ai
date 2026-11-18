<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== STAGE 5.4.2.5.7 BASELINE VERIFICATION ===" . PHP_EOL;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

// 1. Connection & DB
$ctx = $pdo->query("SELECT DATABASE() AS db, USER() AS usr")->fetch(PDO::FETCH_ASSOC);
echo "Active DB: {$ctx['db']}" . PHP_EOL;
echo "Active User: {$ctx['usr']}" . PHP_EOL;

if ($ctx['db'] !== 'socialpilot_staging') {
    die("FATAL: Database is NOT 'socialpilot_staging'." . PHP_EOL);
}
if (!str_starts_with($ctx['usr'], 'AZDARrqgy4MBFGE.staging_migrator')) {
    die("FATAL: User is NOT 'AZDARrqgy4MBFGE.staging_migrator'." . PHP_EOL);
}

// 2. TLS Check
$ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);
$cipher = $ssl['Value'] ?? '';
echo "TLS Cipher: {$cipher}" . PHP_EOL;
if (empty($cipher)) {
    die("FATAL: TLS is NOT active." . PHP_EOL);
}

// 3. Table Count
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$tableCount = count($tables);
echo "Current Base Table Count: {$tableCount}" . PHP_EOL;
if ($tableCount !== 40) {
    die("FATAL: Expected exactly 40 tables, got {$tableCount}." . PHP_EOL);
}

// 4. Migration Records Count
$migs = $pdo->query("SELECT id, migration, batch FROM migrations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$migCount = count($migs);
echo "Completed Migration Records: {$migCount}" . PHP_EOL;
if ($migCount !== 25) {
    die("FATAL: Expected exactly 25 migration records, got {$migCount}." . PHP_EOL);
}

// 5. Verify Backup File
$backupPath = __DIR__ . '/../storage/app/backups/backup_tidb_staging_migrator_socialpilot_staging_2026-11-18_13-29-52.sql';
if (!file_exists($backupPath)) {
    die("FATAL: Backup file does not exist at {$backupPath}." . PHP_EOL);
}
$expectedHash = '252e4d394d3c0bb715d8e15306e1447babca0bba31c9f0caf1de78184e73f74c';
$actualHash = hash_file('sha256', $backupPath);
echo "Backup SHA-256: {$actualHash}" . PHP_EOL;
if (strtolower($actualHash) !== strtolower($expectedHash)) {
    die("FATAL: Backup hash mismatch!" . PHP_EOL);
}

echo "✓ Baseline checks PASSED." . PHP_EOL;
