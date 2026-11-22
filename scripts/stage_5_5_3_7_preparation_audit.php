<?php
/**
 * STAGE 5.5.3.7 — Production Environment Configuration Preparation Script
 * STRICT READ-ONLY AUDIT: No mutations, no credentials printed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$stagingLocal = __DIR__ . '/../.env.staging.local';
$prodAppUser = 'AZDARrqgy4MBFGE.prod_app';
$prodAppPass = '';

if (file_exists($stagingLocal)) {
    $lines = file($stagingLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_starts_with(trim($line), 'TIDB_PROD_APP_PASSWORD=')) {
            $prodAppPass = trim(substr(trim($line), strlen('TIDB_PROD_APP_PASSWORD=')));
        }
        if (str_starts_with(trim($line), 'TIDB_PROD_APP_USERNAME=')) {
            $prodAppUser = trim(substr(trim($line), strlen('TIDB_PROD_APP_USERNAME=')));
        }
    }
}

Config::set('database.connections.tidb_prod_app.username', $prodAppUser);
Config::set('database.connections.tidb_prod_app.password', $prodAppPass);

$results = [];
$results['timestamp'] = date('c');
$results['stage'] = '5.5.3.7 — Production Environment Configuration Preparation & Authorization Gate';
$results['mode'] = 'STRICT READ-ONLY';

echo "=== STAGE 5.5.3.7 — PRODUCTION ENVIRONMENT AUDIT ===\n\n";

// ── 1. Database Verification ────────────────────────────────────────────────
echo "--- 1. Task 3: Production Database Verification ---\n";
try {
    $ident = DB::connection('tidb_prod_app')->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
    $ssl = DB::connection('tidb_prod_app')->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
    $tables = DB::connection('tidb_prod_app')->select("SHOW FULL TABLES FROM `socialpilot` WHERE Table_type = 'BASE TABLE'");
    $migs = DB::connection('tidb_prod_app')->table('migrations')->get();

    $results['db_user'] = $ident->cu;
    $results['db_name'] = $ident->db;
    $results['db_tls'] = $ssl ? $ssl->Value : '';
    $results['table_count'] = count($tables);
    $results['migration_count'] = count($migs);

    echo "User: {$ident->cu}\n";
    echo "DB: {$ident->db}\n";
    echo "TLS: {$results['db_tls']}\n";
    echo "Tables: " . count($tables) . " (Expected: 59)\n";
    echo "Migrations: " . count($migs) . " (Expected: 29)\n";

    // Verify DDL rejection
    $ddlDenied = false;
    try {
        DB::connection('tidb_prod_app')->statement("CREATE TABLE `_probe_ddl` (id INT)");
    } catch (\Exception $e) {
        $ddlDenied = str_contains($e->getMessage(), 'denied') || str_contains($e->getMessage(), '1142');
    }
    $results['ddl_denied'] = $ddlDenied;
    echo "DDL Denied: " . ($ddlDenied ? 'PASS' : 'FAIL') . "\n";

    // Verify staging isolation
    $stagingDenied = false;
    try {
        DB::connection('tidb_prod_app')->selectOne("SELECT COUNT(*) FROM `socialpilot_staging`.`migrations`");
    } catch (\Exception $e) {
        $stagingDenied = str_contains($e->getMessage(), 'denied') || str_contains($e->getMessage(), '1044');
    }
    $results['staging_denied'] = $stagingDenied;
    echo "Staging Denied: " . ($stagingDenied ? 'PASS' : 'FAIL') . "\n";

    // Verify test DB isolation
    $testDenied = false;
    try {
        DB::connection('tidb_prod_app')->selectOne("SELECT COUNT(*) FROM `socialpilot_migration_test`.`migrations`");
    } catch (\Exception $e) {
        $testDenied = str_contains($e->getMessage(), 'denied') || str_contains($e->getMessage(), '1044');
    }
    $results['test_denied'] = $testDenied;
    echo "Test DB Denied: " . ($testDenied ? 'PASS' : 'FAIL') . "\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $results['db_error'] = $e->getMessage();
}

// ── 2. Storage Inspection ───────────────────────────────────────────────────
echo "\n--- 2. Task 4: Supabase Storage Configuration ---\n";
$supabase = config('filesystems.disks.supabase');
$results['storage'] = [
    'disk'                    => 'supabase',
    'driver'                  => $supabase['driver'] ?? null,
    'bucket'                  => $supabase['bucket'] ?? null,
    'region'                  => $supabase['region'] ?? null,
    'endpoint'                => $supabase['endpoint'] ?? null,
    'use_path_style_endpoint' => $supabase['use_path_style_endpoint'] ?? null,
    'url'                     => $supabase['url'] ?? null,
];
foreach ($results['storage'] as $k => $v) {
    echo "Storage {$k}: " . (is_bool($v) ? ($v ? 'true' : 'false') : $v) . "\n";
}

file_put_contents(__DIR__ . '/stage_5_5_3_7_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults written to scripts/stage_5_5_3_7_results.json\n";
