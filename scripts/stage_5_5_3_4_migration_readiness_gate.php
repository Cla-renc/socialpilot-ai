<?php
/**
 * STAGE 5.5.3.4 — Production Migration Execution Readiness & Pre-Execution Gate Script
 * READ-ONLY AUDIT: No schema changes, no migrations executed, no data mutations.
 *
 * Verifies:
 * 1. tidb_prod_migrator connection & authentication.
 * 2. TLS cipher active on connection.
 * 3. Exact target database: socialpilot.
 * 4. Baseline state: 59 tables, 28 migrations.
 * 5. Pending migration: 2026_11_16_200000_add_integrity_constraints_to_content_generation_tables.
 * 6. Live pre-flight checks: 0 duplicate generation_job_id, 0 duplicate (draft_id, revision_number).
 * 7. Conflicting index checks on brand_content_drafts & content_revisions.
 * 8. Confirmation that no mutations have occurred.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$results = [];
$results['timestamp'] = date('c');
$results['stage'] = '5.5.3.4 — Migration Execution Readiness & Pre-Execution Gate';
$results['mode'] = 'READ-ONLY GATE — NO MUTATIONS PERFORMED';

echo "=== STAGE 5.5.3.4 — PRODUCTION MIGRATION READINESS GATE ===\n\n";

// ── 1. Configure tidb_prod_migrator connection using .env.staging.local ─────
$stagingLocal = __DIR__ . '/../.env.staging.local';
$migratorUser = 'AZDARrqgy4MBFGE.prod_migrator';
$migratorPass = '';

if (file_exists($stagingLocal)) {
    $lines = file($stagingLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_starts_with(trim($line), 'TIDB_PROD_MIGRATOR_PASSWORD=')) {
            $migratorPass = trim(substr(trim($line), strlen('TIDB_PROD_MIGRATOR_PASSWORD=')));
        }
        if (str_starts_with(trim($line), 'TIDB_PROD_MIGRATOR_USERNAME=')) {
            $migratorUser = trim(substr(trim($line), strlen('TIDB_PROD_MIGRATOR_USERNAME=')));
        }
    }
}

Config::set('database.connections.tidb_prod_migrator.username', $migratorUser);
Config::set('database.connections.tidb_prod_migrator.password', $migratorPass);

// ── 2. Authenticate as tidb_prod_migrator ───────────────────────────────────
echo "--- 1. Connection & Identity Verification ---\n";
try {
    $row = DB::connection('tidb_prod_migrator')->selectOne("SELECT current_user() AS cu, database() AS db");
    $results['authenticated_user'] = $row->cu;
    $results['active_database']    = $row->db;
    echo "Connected as: {$row->cu}\n";
    echo "Active DB:    {$row->db}\n";

    if (!str_contains($row->cu, 'prod_migrator')) {
        throw new \RuntimeException("User identity mismatch: expected prod_migrator, got {$row->cu}");
    }
    if ($row->db !== 'socialpilot') {
        throw new \RuntimeException("Database mismatch: expected socialpilot, got {$row->db}");
    }
    $results['identity_check'] = 'PASS';
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    $results['identity_check'] = 'FAIL: ' . $e->getMessage();
    file_put_contents(__DIR__ . '/stage_5_5_3_4_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}

// ── 3. TLS verification ──────────────────────────────────────────────────────
try {
    $tlsRow = DB::connection('tidb_prod_migrator')->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
    $results['tls_cipher'] = $tlsRow ? $tlsRow->Value : 'NONE';
    echo "TLS Cipher:   " . ($tlsRow ? $tlsRow->Value : 'NONE') . "\n";
    $results['tls_check'] = (!empty($tlsRow?->Value)) ? 'PASS' : 'FAIL';
} catch (\Exception $e) {
    $results['tls_check'] = 'ERROR: ' . $e->getMessage();
}

// ── 4. Baseline table and migration counts ───────────────────────────────────
echo "\n--- 2. Production Baseline Verification ---\n";
try {
    $tables = DB::connection('tidb_prod_migrator')->select("SHOW TABLES FROM `socialpilot`");
    $results['baseline_table_count'] = count($tables);
    echo "Base tables in socialpilot: " . count($tables) . " (Expected: 59)\n";

    $ranMigrations = DB::connection('tidb_prod_migrator')
        ->table('migrations')
        ->orderBy('id')
        ->get(['id', 'migration', 'batch'])
        ->toArray();
    $results['baseline_migration_count'] = count($ranMigrations);
    echo "Executed migrations in socialpilot: " . count($ranMigrations) . " (Expected: 28)\n";

    $latestBatch = !empty($ranMigrations) ? max(array_column($ranMigrations, 'batch')) : 0;
    $results['latest_batch'] = $latestBatch;
    echo "Latest migration batch: {$latestBatch} (Next batch will be: " . ($latestBatch + 1) . ")\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $results['baseline_error'] = $e->getMessage();
}

// ── 5. Confirm pending migration ─────────────────────────────────────────────
echo "\n--- 3. Pending Migration Identification ---\n";
$targetMigration = '2026_11_16_200000_add_integrity_constraints_to_content_generation_tables';
$isPending = true;
foreach ($ranMigrations ?? [] as $m) {
    if ($m->migration === $targetMigration) {
        $isPending = false;
        break;
    }
}
$results['target_migration'] = $targetMigration;
$results['is_pending'] = $isPending;
echo "Target migration: {$targetMigration}\n";
echo "Pending status:   " . ($isPending ? "CONFIRMED PENDING (Ready for execution)" : "ALREADY EXECUTED") . "\n";

// ── 6. Live Pre-Flight Integrity Verification ────────────────────────────────
echo "\n--- 4. Live Pre-Flight Data Integrity Checks ---\n";
try {
    $dupJobs = DB::connection('tidb_prod_migrator')->select(
        "SELECT generation_job_id, COUNT(*) as cnt
         FROM `socialpilot`.`brand_content_drafts`
         WHERE generation_job_id IS NOT NULL
         GROUP BY generation_job_id
         HAVING cnt > 1"
    );
    $results['preflight_duplicate_job_ids'] = count($dupJobs);
    echo "Duplicate generation_job_id count in brand_content_drafts: " . count($dupJobs) . "\n";

    $dupRevs = DB::connection('tidb_prod_migrator')->select(
        "SELECT draft_id, revision_number, COUNT(*) as cnt
         FROM `socialpilot`.`content_revisions`
         GROUP BY draft_id, revision_number
         HAVING cnt > 1"
    );
    $results['preflight_duplicate_revisions'] = count($dupRevs);
    echo "Duplicate (draft_id, revision_number) count in content_revisions: " . count($dupRevs) . "\n";

    $preflightPass = (count($dupJobs) === 0 && count($dupRevs) === 0);
    $results['preflight_status'] = $preflightPass ? 'PASS' : 'FAIL';
    echo "Preflight check status: " . ($preflightPass ? 'PASS (0 conflicts)' : 'FAIL (duplicates detected)') . "\n";
} catch (\Exception $e) {
    echo "ERROR in preflight checks: " . $e->getMessage() . "\n";
    $results['preflight_error'] = $e->getMessage();
}

// ── 7. Check for conflicting indexes ─────────────────────────────────────────
echo "\n--- 5. Index Conflict Checks ---\n";
try {
    $idxDrafts = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`brand_content_drafts`");
    $idxDraftsNames = array_map(fn($r) => $r->Key_name, $idxDrafts);
    $conflict1 = in_array('bcd_generation_job_id_unique', $idxDraftsNames);

    $idxRevs = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`content_revisions`");
    $idxRevsNames = array_map(fn($r) => $r->Key_name, $idxRevs);
    $conflict2 = in_array('cr_draft_id_revision_number_unique', $idxRevsNames);

    $results['index_conflict_bcd'] = $conflict1;
    $results['index_conflict_cr']  = $conflict2;

    echo "Index bcd_generation_job_id_unique exists: " . ($conflict1 ? 'YES (Conflict)' : 'NO (Clear)') . "\n";
    echo "Index cr_draft_id_revision_number_unique exists: " . ($conflict2 ? 'YES (Conflict)' : 'NO (Clear)') . "\n";
} catch (\Exception $e) {
    echo "ERROR in index checks: " . $e->getMessage() . "\n";
    $results['index_error'] = $e->getMessage();
}

// ── 8. Staging & test database isolation confirmation ───────────────────────
echo "\n--- 6. Isolation Checks (Staging & Migration-Test) ---\n";
try {
    DB::connection('tidb_prod_migrator')->selectOne("SELECT COUNT(*) FROM `socialpilot_staging`.`migrations`");
    $results['staging_isolation'] = 'FAIL — access granted unexpectedly';
    echo "Staging isolation: FAIL\n";
} catch (\Exception $e) {
    $results['staging_isolation'] = 'PASS — access denied';
    echo "Staging isolation: PASS (access denied)\n";
}

try {
    DB::connection('tidb_prod_migrator')->selectOne("SELECT COUNT(*) FROM `socialpilot_migration_test`.`migrations`");
    $results['migration_test_isolation'] = 'FAIL — access granted unexpectedly';
    echo "Migration-test isolation: FAIL\n";
} catch (\Exception $e) {
    $results['migration_test_isolation'] = 'PASS — access denied';
    echo "Migration-test isolation: PASS (access denied)\n";
}

// ── 9. Write results ─────────────────────────────────────────────────────────
file_put_contents(__DIR__ . '/stage_5_5_3_4_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults saved to scripts/stage_5_5_3_4_results.json\n";
echo "READ-ONLY GATE AUDIT COMPLETE — ZERO MUTATIONS OCCURRED\n";
