<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== STEP 1: IMMEDIATE PRE-EXECUTION VERIFICATION ===" . PHP_EOL;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

// 1. Connection & DB Context
$ctxStmt = $pdo->query("SELECT DATABASE() AS active_db, USER() AS active_user");
$ctx = $ctxStmt->fetch(PDO::FETCH_ASSOC);
echo "Active DB: {$ctx['active_db']}" . PHP_EOL;
echo "Active User: {$ctx['active_user']}" . PHP_EOL;

if ($ctx['active_db'] !== 'socialpilot_staging') {
    die("FATAL: Active database is NOT 'socialpilot_staging'. Stopping immediately." . PHP_EOL);
}

if (!str_starts_with($ctx['active_user'], 'AZDARrqgy4MBFGE.staging_migrator')) {
    die("FATAL: User is NOT 'AZDARrqgy4MBFGE.staging_migrator'. Stopping immediately." . PHP_EOL);
}

// 2. TLS Check
$sslStmt = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
$ssl = $sslStmt->fetch(PDO::FETCH_ASSOC);
$cipher = $ssl['Value'] ?? '';
echo "TLS Cipher: {$cipher}" . PHP_EOL;
if (empty($cipher)) {
    die("FATAL: TLS connection is NOT active. Stopping immediately." . PHP_EOL);
}

// 3. Target tables existence & row count
$tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
$tables = [];
while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

if (!in_array('media_generation_jobs', $tables)) {
    die("FATAL: Table 'media_generation_jobs' does not exist before drop. Stopping." . PHP_EOL);
}
if (!in_array('media_assets', $tables)) {
    die("FATAL: Table 'media_assets' does not exist before drop. Stopping." . PHP_EOL);
}

$jobsRows = (int)$pdo->query("SELECT COUNT(*) FROM `media_generation_jobs`")->fetchColumn();
$assetRows = (int)$pdo->query("SELECT COUNT(*) FROM `media_assets`")->fetchColumn();

echo "Table `media_generation_jobs`: EXISTS, Rows = {$jobsRows}" . PHP_EOL;
echo "Table `media_assets`: EXISTS, Rows = {$assetRows}" . PHP_EOL;

if ($jobsRows !== 0) {
    die("FATAL: Table 'media_generation_jobs' contains {$jobsRows} rows (expected 0). Stopping." . PHP_EOL);
}
if ($assetRows !== 0) {
    die("FATAL: Table 'media_assets' contains {$assetRows} rows (expected 0). Stopping." . PHP_EOL);
}

// 4. Backup Hash Verification
$backupPath = __DIR__ . '/../storage/app/backups/backup_tidb_staging_migrator_socialpilot_staging_2026-11-18_13-29-52.sql';
if (!file_exists($backupPath)) {
    die("FATAL: Backup file does not exist at {$backupPath}. Stopping." . PHP_EOL);
}
$expectedHash = '252e4d394d3c0bb715d8e15306e1447babca0bba31c9f0caf1de78184e73f74c';
$actualHash = hash_file('sha256', $backupPath);
echo "Backup SHA-256: {$actualHash}" . PHP_EOL;
if (strtolower($actualHash) !== strtolower($expectedHash)) {
    die("FATAL: Backup hash mismatch! Expected {$expectedHash}, got {$actualHash}. Stopping." . PHP_EOL);
}
echo "✓ All Pre-Execution checks PASSED." . PHP_EOL . PHP_EOL;

echo "=== STEP 2: SEQUENTIAL DROP EXECUTION ===" . PHP_EOL;

// 1. DROP media_generation_jobs
echo "Executing: DROP TABLE IF EXISTS `socialpilot_staging`.`media_generation_jobs`;" . PHP_EOL;
$pdo->exec("DROP TABLE IF EXISTS `socialpilot_staging`.`media_generation_jobs`");
echo "✓ Statement 1 executed." . PHP_EOL;

// Verify media_generation_jobs is dropped
$check1 = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'socialpilot_staging' 
      AND TABLE_NAME = 'media_generation_jobs'
")->fetchColumn();
if ($check1 !== 0) {
    die("FATAL: media_generation_jobs was NOT dropped. Halting before media_assets drop." . PHP_EOL);
}
echo "✓ Verified: `media_generation_jobs` removed." . PHP_EOL;

// 2. DROP media_assets
echo "Executing: DROP TABLE IF EXISTS `socialpilot_staging`.`media_assets`;" . PHP_EOL;
$pdo->exec("DROP TABLE IF EXISTS `socialpilot_staging`.`media_assets`");
echo "✓ Statement 2 executed." . PHP_EOL;

// Verify media_assets is dropped
$check2 = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'socialpilot_staging' 
      AND TABLE_NAME = 'media_assets'
")->fetchColumn();
if ($check2 !== 0) {
    die("FATAL: media_assets was NOT dropped." . PHP_EOL);
}
echo "✓ Verified: `media_assets` removed." . PHP_EOL . PHP_EOL;

echo "=== STEP 3: POST-EXECUTION VERIFICATION ===" . PHP_EOL;

// Table Count
$postTables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$postCount = count($postTables);
sort($postTables);
echo "Post-Execution Table Count: {$postCount} (Expected: 35)" . PHP_EOL;
if ($postCount !== 35) {
    echo "ERROR: Table count is {$postCount}, expected 35!" . PHP_EOL;
} else {
    echo "✓ Exactly 35 tables verified." . PHP_EOL;
}

// Migration Records
$migs = $pdo->query("SELECT id, migration, batch FROM migrations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$migCount = count($migs);
echo "Post-Execution Migration Records: {$migCount} (Expected: 24)" . PHP_EOL;
if ($migCount !== 24) {
    echo "ERROR: Migration record count is {$migCount}, expected 24!" . PHP_EOL;
} else {
    echo "✓ Exactly 24 migration records verified." . PHP_EOL;
}

// Batch verification
$nonBatch1 = 0;
foreach ($migs as $m) {
    if ((int)$m['batch'] !== 1) {
        $nonBatch1++;
    }
}
echo "Non-batch-1 migrations: {$nonBatch1} (Expected: 0)" . PHP_EOL;
if ($nonBatch1 === 0) {
    echo "✓ All 24 migration records remain in batch 1." . PHP_EOL;
}

// Verify target tables are gone
$existsCheck = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'socialpilot_staging' 
      AND TABLE_NAME IN ('media_assets', 'media_generation_jobs')
")->fetchColumn();
echo "Target tables remaining in staging: {$existsCheck} (Expected: 0)" . PHP_EOL;

// Cross-database isolation check
echo "Testing Isolation from socialpilot (production): ";
try {
    $pdo->query("SELECT COUNT(*) FROM socialpilot.users");
    echo "FAILED (accessible!)" . PHP_EOL;
} catch (\Throwable $e) {
    echo "PASSED (denied: " . $e->getCode() . ")" . PHP_EOL;
}

echo "Testing Isolation from socialpilot_migration_test: ";
try {
    $pdo->query("SELECT COUNT(*) FROM socialpilot_migration_test.users");
    echo "FAILED (accessible!)" . PHP_EOL;
} catch (\Throwable $e) {
    echo "PASSED (denied: " . $e->getCode() . ")" . PHP_EOL;
}

// Test staging app account if configured
try {
    $appPdo = DB::connection('tidb_staging_app')->getPdo();
    $appDb = $appPdo->query("SELECT DATABASE()")->fetchColumn();
    $appUser = $appPdo->query("SELECT USER()")->fetchColumn();
    echo "Staging App Account: Connected as {$appUser} to {$appDb}" . PHP_EOL;
    // App account should be denied DDL
    try {
        $appPdo->exec("CREATE TABLE `test_app_ddl` (`id` int)");
        echo "WARNING: App account has DDL privileges!" . PHP_EOL;
        $appPdo->exec("DROP TABLE `test_app_ddl`");
    } catch (\Throwable $e) {
        echo "✓ Staging App Account DDL Denial Verified: " . $e->getMessage() . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "Staging App Account connection note: " . $e->getMessage() . PHP_EOL;
}
