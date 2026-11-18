<?php
// Phase A — Verify production and test isolation table counts
// Read-only. No changes.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// We use the staging migrator to verify its own isolation
// (staging_migrator should be DENIED access to production and test)
$pdo = DB::connection('tidb_staging_migrator')->getPdo();

echo '=== PRODUCTION / TEST ISOLATION VERIFICATION ===' . PHP_EOL;

// Attempt to count production tables via information_schema (which is accessible to all users)
// but staging_migrator should be denied DML/DDL on those schemas
// We confirm via information_schema read (safe read-only view)
$prodTables = $pdo->query("
    SELECT COUNT(*) AS cnt
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = 'socialpilot'
    AND TABLE_TYPE = 'BASE TABLE'
")->fetchColumn();
echo "Production (socialpilot) BASE TABLE count via info_schema: {$prodTables}" . PHP_EOL;

$testTables = $pdo->query("
    SELECT COUNT(*) AS cnt
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = 'socialpilot_migration_test'
    AND TABLE_TYPE = 'BASE TABLE'
")->fetchColumn();
echo "Test (socialpilot_migration_test) BASE TABLE count via info_schema: {$testTables}" . PHP_EOL;

// Also verify the DML isolation (staging_migrator cannot SELECT from them)
echo PHP_EOL . 'Attempting direct DML access to production (should be DENIED):' . PHP_EOL;
try {
    $pdo->query("SELECT COUNT(*) FROM socialpilot.users")->fetchColumn();
    echo '  CRITICAL WARNING: staging_migrator CAN read socialpilot.users!' . PHP_EOL;
} catch (Exception $e) {
    echo '  CONFIRMED DENIED: ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . 'Attempting direct DML access to test (should be DENIED):' . PHP_EOL;
try {
    $pdo->query("SELECT COUNT(*) FROM socialpilot_migration_test.users")->fetchColumn();
    echo '  CRITICAL WARNING: staging_migrator CAN read socialpilot_migration_test.users!' . PHP_EOL;
} catch (Exception $e) {
    echo '  CONFIRMED DENIED: ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . '=== PHASE D: MIGRATE STATUS (pending migrations) ===' . PHP_EOL;
passthru('php artisan migrate:status --database=tidb_staging_migrator 2>&1');

echo PHP_EOL . '=== PHASE D: REVIEW MIGRATION 25 SOURCE ===' . PHP_EOL;
// Check the migration file for any --step or path:
$migFile = glob(__DIR__ . '/../database/migrations/*create_media_generation_tables*');
if (!empty($migFile)) {
    echo 'Migration 25 file: ' . basename($migFile[0]) . PHP_EOL;
    $src = file_get_contents($migFile[0]);
    // Show the guard conditions
    preg_match_all('/if\s*\(\s*!\s*Schema::hasTable\([^\)]+\)\s*\)\s*\{/m', $src, $matches);
    echo 'Guard conditions found in migration 25:' . PHP_EOL;
    foreach ($matches[0] as $m) {
        echo '  ' . trim($m) . PHP_EOL;
    }
    // Count table creations
    preg_match_all('/Schema::create\s*\(\s*\'([^\']+)\'/', $src, $creates);
    echo 'Tables created by migration 25:' . PHP_EOL;
    foreach ($creates[1] as $t) {
        echo '  - ' . $t . PHP_EOL;
    }
} else {
    echo 'WARNING: Migration 25 file not found.' . PHP_EOL;
}

echo PHP_EOL . '=== PHASE D: MIGRATE --STEP OPTION CHECK ===' . PHP_EOL;
passthru('php artisan migrate --help 2>&1');
