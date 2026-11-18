<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

echo "=== STAGE 5.4.2.5.4 STEP 3: POST-MIGRATION VERIFICATION ===" . PHP_EOL;

// 1. Database & User Context
$ctx = $pdo->query("SELECT DATABASE() AS db, USER() AS usr")->fetch(PDO::FETCH_ASSOC);
echo "Active DB: {$ctx['db']}" . PHP_EOL;
echo "Active User: {$ctx['usr']}" . PHP_EOL;

// 2. Table Count & Inventory
$tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
$tables = [];
while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}
sort($tables);
$tableCount = count($tables);
echo "Base Table Count: {$tableCount} (Expected: 40)" . PHP_EOL;
if ($tableCount !== 40) {
    echo "ERROR: Table count mismatch!" . PHP_EOL;
} else {
    echo "✓ Exactly 40 base tables verified." . PHP_EOL;
}

// 3. Migration Records Verification
$migs = $pdo->query("SELECT id, migration, batch FROM migrations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$migCount = count($migs);
echo "Completed Migration Records: {$migCount} (Expected: 25)" . PHP_EOL;
if ($migCount !== 25) {
    echo "ERROR: Migration count mismatch!" . PHP_EOL;
} else {
    echo "✓ Exactly 25 migration records verified." . PHP_EOL;
}

$lastMig = end($migs);
echo "Latest Migration: {$lastMig['migration']} (Batch: {$lastMig['batch']})" . PHP_EOL;
if ($lastMig['migration'] === '2026_11_13_200000_create_media_generation_tables') {
    echo "✓ Migration 25 recorded as completed (Batch: {$lastMig['batch']})." . PHP_EOL;
} else {
    echo "ERROR: Latest migration is not Migration 25!" . PHP_EOL;
}

// 4. Verify All Five Media Tables
$mediaTables = [
    'media_assets',
    'media_generation_jobs',
    'media_storyboards',
    'media_storyboard_scenes',
    'media_visual_settings'
];

echo PHP_EOL . "=== VERIFYING MEDIA TABLES ===" . PHP_EOL;
foreach ($mediaTables as $t) {
    if (in_array($t, $tables)) {
        $cols = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'socialpilot_staging' AND TABLE_NAME = '{$t}'")->fetchColumn();
        echo "  Table `{$t}`: PRESENT ({$cols} columns)" . PHP_EOL;
    } else {
        echo "  Table `{$t}`: MISSING!" . PHP_EOL;
    }
}

// 5. Foreign Key Details on Media Tables
echo PHP_EOL . "=== FOREIGN KEYS ON MEDIA TABLES ===" . PHP_EOL;
$fkQuery = "
    SELECT 
        kcu.TABLE_NAME, 
        kcu.COLUMN_NAME, 
        kcu.CONSTRAINT_NAME, 
        kcu.REFERENCED_TABLE_NAME, 
        kcu.REFERENCED_COLUMN_NAME,
        rc.DELETE_RULE
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
    JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
      ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
      AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
    WHERE kcu.TABLE_SCHEMA = 'socialpilot_staging'
      AND kcu.TABLE_NAME IN ('" . implode("','", $mediaTables) . "')
    ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME
";
$fks = $pdo->query($fkQuery)->fetchAll(PDO::FETCH_ASSOC);
echo "Total Media Foreign Keys: " . count($fks) . PHP_EOL;
foreach ($fks as $fk) {
    echo "  {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} (FK: {$fk['CONSTRAINT_NAME']}, ON DELETE {$fk['DELETE_RULE']})" . PHP_EOL;
}

// 6. Indexes on media_generation_jobs (where failure previously occurred)
echo PHP_EOL . "=== INDEXES ON media_generation_jobs ===" . PHP_EOL;
$jobIdxs = $pdo->query("SHOW INDEX FROM `media_generation_jobs`")->fetchAll(PDO::FETCH_ASSOC);
foreach ($jobIdxs as $idx) {
    echo "  {$idx['Key_name']} (Column: {$idx['Column_name']}, Unique: " . ($idx['Non_unique'] == 0 ? 'YES' : 'NO') . ")" . PHP_EOL;
}

// 7. Isolation Checks
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
