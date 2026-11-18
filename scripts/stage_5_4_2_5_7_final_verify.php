<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== STAGE 5.4.2.5.7 FINAL VERIFICATION ===" . PHP_EOL;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

// 1. Identity & Context
$ctx = $pdo->query("SELECT DATABASE() AS db, USER() AS usr")->fetch(PDO::FETCH_ASSOC);
echo "Active DB: {$ctx['db']}" . PHP_EOL;
echo "Active User: {$ctx['usr']}" . PHP_EOL;

// 2. Base Tables Count & List
$tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
$tables = [];
while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}
sort($tables);
$tableCount = count($tables);
echo "Base Table Count: {$tableCount} (Expected: 59)" . PHP_EOL;
if ($tableCount !== 59) {
    echo "ERROR: Table count mismatch!" . PHP_EOL;
} else {
    echo "✓ Exactly 59 base tables verified." . PHP_EOL;
}

// 3. Completed Migrations Count & Batches
$migs = $pdo->query("SELECT id, migration, batch FROM migrations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$migCount = count($migs);
echo "Completed Migrations: {$migCount} (Expected: 29)" . PHP_EOL;
if ($migCount !== 29) {
    echo "ERROR: Migration count mismatch!" . PHP_EOL;
} else {
    echo "✓ Exactly 29 migration records verified." . PHP_EOL;
}

// Print batch summary
$batches = [];
foreach ($migs as $m) {
    $batches[$m['batch']][] = $m['migration'];
}
echo "Migration Batches:" . PHP_EOL;
foreach ($batches as $b => $list) {
    echo "  Batch {$b} (" . count($list) . " migrations):" . PHP_EOL;
    foreach ($list as $mig) {
        echo "    - {$mig}" . PHP_EOL;
    }
}

// 4. Check Migration 29 Unique Constraints
echo PHP_EOL . "=== VERIFYING MIGRATION 29 UNIQUE CONSTRAINTS ===" . PHP_EOL;
$bcdIndexes = $pdo->query("SHOW INDEX FROM `brand_content_drafts` WHERE Key_name = 'bcd_generation_job_id_unique'")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($bcdIndexes)) {
    echo "✓ Unique constraint 'bcd_generation_job_id_unique' EXISTS on `brand_content_drafts` (`generation_job_id`)." . PHP_EOL;
} else {
    echo "ERROR: Unique constraint 'bcd_generation_job_id_unique' MISSING!" . PHP_EOL;
}

$crIndexes = $pdo->query("SHOW INDEX FROM `content_revisions` WHERE Key_name = 'cr_draft_id_revision_number_unique'")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($crIndexes)) {
    echo "✓ Unique constraint 'cr_draft_id_revision_number_unique' EXISTS on `content_revisions` (`draft_id`, `revision_number`)." . PHP_EOL;
} else {
    echo "ERROR: Unique constraint 'cr_draft_id_revision_number_unique' MISSING!" . PHP_EOL;
}

// 5. Total Foreign Keys Count in Schema
$fkCount = $pdo->query("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'socialpilot_staging'
")->fetchColumn();
echo PHP_EOL . "Total Foreign Keys in `socialpilot_staging`: {$fkCount}" . PHP_EOL;

// 6. Cross-Database Isolation
echo PHP_EOL . "=== CROSS-DATABASE ISOLATION CHECKS ===" . PHP_EOL;
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

// 7. Staging App Account DDL Denial Check
echo PHP_EOL . "=== STAGING APP ACCOUNT DDL RESTRICTION CHECK ===" . PHP_EOL;
try {
    $appPdo = DB::connection('tidb_staging_app')->getPdo();
    $appUser = $appPdo->query("SELECT USER()")->fetchColumn();
    echo "Connected as app user: {$appUser}" . PHP_EOL;
    try {
        $appPdo->exec("CREATE TABLE `test_staging_app_ddl` (`id` int)");
        echo "FAILED: App account has DDL privilege!" . PHP_EOL;
        $appPdo->exec("DROP TABLE `test_staging_app_ddl`");
    } catch (\Throwable $e) {
        echo "PASSED: DDL operation denied as expected (" . $e->getMessage() . ")" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "App connection note: " . $e->getMessage() . PHP_EOL;
}
