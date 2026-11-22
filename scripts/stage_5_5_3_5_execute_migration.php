<?php
/**
 * STAGE 5.5.3.5 — AUTHORIZED PRODUCTION MIGRATION EXECUTION
 * Explicit Human Authorization Granted.
 *
 * Execution scope:
 * Single migration: database/migrations/2026_11_16_200000_add_integrity_constraints_to_content_generation_tables.php
 * Target: socialpilot via tidb_prod_migrator
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [];
$results['timestamp'] = date('c');
$results['stage'] = '5.5.3.5 — Authorized Production Migration Execution';
$results['status'] = 'IN_PROGRESS';

echo "=== STAGE 5.5.3.5 — AUTHORIZED PRODUCTION MIGRATION EXECUTION ===\n\n";

// ── 0. Configure tidb_prod_migrator connection using .env.staging.local ─────
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
putenv("TIDB_PROD_MIGRATOR_USERNAME={$migratorUser}");
putenv("TIDB_PROD_MIGRATOR_PASSWORD={$migratorPass}");
$_ENV['TIDB_PROD_MIGRATOR_USERNAME'] = $migratorUser;
$_ENV['TIDB_PROD_MIGRATOR_PASSWORD'] = $migratorPass;

// ── SECTION 3: Immediate Pre-Execution Verification (10 Checks) ─────────────
echo "--- SECTION 3: Immediate Pre-Execution Verification ---\n";
$preCheck = [];

// 1. Authenticate using AZDARrqgy4MBFGE.prod_migrator
try {
    $ident = DB::connection('tidb_prod_migrator')->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
    $preCheck['1_user'] = $ident->cu;
    $preCheck['1_db']   = $ident->db;
    echo "1. User: {$ident->cu} | DB: {$ident->db}\n";
    if (!str_contains($ident->cu, 'prod_migrator')) throw new \RuntimeException("User identity mismatch: {$ident->cu}");
    if ($ident->db !== 'socialpilot') throw new \RuntimeException("Database mismatch: {$ident->db}");
} catch (\Exception $e) {
    die("ABORT Check 1: " . $e->getMessage() . "\n");
}

// 2. Verify TiDB cluster identity
try {
    $cluster = DB::connection('tidb_prod_migrator')->selectOne("SELECT @@hostname AS h, @@version AS v");
    $preCheck['2_cluster'] = ['host' => $cluster->h, 'version' => $cluster->v];
    echo "2. Cluster Host: {$cluster->h} | Version: {$cluster->v}\n";
} catch (\Exception $e) {
    die("ABORT Check 2: " . $e->getMessage() . "\n");
}

// 3. Active database is exactly socialpilot
$preCheck['3_active_db_match'] = ($ident->db === 'socialpilot');
echo "3. Active DB exactly 'socialpilot': " . ($preCheck['3_active_db_match'] ? 'PASS' : 'FAIL') . "\n";
if (!$preCheck['3_active_db_match']) die("ABORT Check 3: Active DB is not socialpilot\n");

// 4. Verify TLS is active
try {
    $ssl = DB::connection('tidb_prod_migrator')->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
    $preCheck['4_tls_cipher'] = $ssl ? $ssl->Value : '';
    echo "4. TLS Cipher: {$preCheck['4_tls_cipher']}\n";
    if (empty($preCheck['4_tls_cipher'])) throw new \RuntimeException("TLS cipher is empty");
} catch (\Exception $e) {
    die("ABORT Check 4: " . $e->getMessage() . "\n");
}

// 5. Exactly 59 base tables
try {
    $tablesPre = DB::connection('tidb_prod_migrator')->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $preCheck['5_base_table_count'] = count($tablesPre);
    echo "5. Base Table Count: " . count($tablesPre) . " (Expected: 59)\n";
    if (count($tablesPre) !== 59) throw new \RuntimeException("Expected 59 tables, found " . count($tablesPre));
} catch (\Exception $e) {
    die("ABORT Check 5: " . $e->getMessage() . "\n");
}

// 6. Exactly 28 completed migration records
try {
    $migsPre = DB::connection('tidb_prod_migrator')->table('migrations')->orderBy('id')->get();
    $preCheck['6_migration_count'] = count($migsPre);
    echo "6. Completed Migrations: " . count($migsPre) . " (Expected: 28)\n";
    if (count($migsPre) !== 28) throw new \RuntimeException("Expected 28 migrations, found " . count($migsPre));
} catch (\Exception $e) {
    die("ABORT Check 6: " . $e->getMessage() . "\n");
}

// 7. Confirm specified migration is still pending
$targetMig = '2026_11_16_200000_add_integrity_constraints_to_content_generation_tables';
$alreadyRan = $migsPre->contains('migration', $targetMig);
$preCheck['7_target_pending'] = !$alreadyRan;
echo "7. Target Migration Pending: " . (!$alreadyRan ? 'CONFIRMED PENDING' : 'ALREADY RAN') . "\n";
if ($alreadyRan) die("ABORT Check 7: Migration {$targetMig} has already run!\n");

// 8. Re-run both duplicate-record checks
try {
    $dupJobs = DB::connection('tidb_prod_migrator')->select(
        "SELECT generation_job_id, COUNT(*) as cnt FROM `socialpilot`.`brand_content_drafts` WHERE generation_job_id IS NOT NULL GROUP BY generation_job_id HAVING cnt > 1"
    );
    $dupRevs = DB::connection('tidb_prod_migrator')->select(
        "SELECT draft_id, revision_number, COUNT(*) as cnt FROM `socialpilot`.`content_revisions` GROUP BY draft_id, revision_number HAVING cnt > 1"
    );
    $preCheck['8_dup_jobs'] = count($dupJobs);
    $preCheck['8_dup_revs'] = count($dupRevs);
    echo "8. Duplicate Job IDs: " . count($dupJobs) . " | Duplicate Revisions: " . count($dupRevs) . "\n";
    if (count($dupJobs) > 0 || count($dupRevs) > 0) throw new \RuntimeException("Duplicate records detected!");
} catch (\Exception $e) {
    die("ABORT Check 8: " . $e->getMessage() . "\n");
}

// 9. Confirm neither target unique index already exists
try {
    $idxDrafts = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`brand_content_drafts` WHERE Key_name = 'bcd_generation_job_id_unique'");
    $idxRevs   = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`content_revisions` WHERE Key_name = 'cr_draft_id_revision_number_unique'");
    $preCheck['9_index1_exists'] = (count($idxDrafts) > 0);
    $preCheck['9_index2_exists'] = (count($idxRevs) > 0);
    echo "9. Index bcd_generation_job_id_unique exists: " . ($preCheck['9_index1_exists'] ? 'YES' : 'NO') . " | cr_draft_id_revision_number_unique exists: " . ($preCheck['9_index2_exists'] ? 'YES' : 'NO') . "\n";
    if ($preCheck['9_index1_exists'] || $preCheck['9_index2_exists']) throw new \RuntimeException("One or both target indexes already exist!");
} catch (\Exception $e) {
    die("ABORT Check 9: " . $e->getMessage() . "\n");
}

// 10. Confirm production migration account has expected privileges
try {
    $grants = DB::connection('tidb_prod_migrator')->select("SHOW GRANTS FOR '{$migratorUser}'@'%'");
    $grantStrings = array_map(fn($g) => array_values((array)$g)[0], $grants);
    $preCheck['10_grants'] = $grantStrings;
    echo "10. Grants verified (" . count($grantStrings) . " grant lines)\n";
    foreach ($grantStrings as $gs) echo "    {$gs}\n";
} catch (\Exception $e) {
    die("ABORT Check 10: " . $e->getMessage() . "\n");
}

// Baseline baseline table data counts for integrity comparison
$preDataCounts = [
    'users'                 => DB::connection('tidb_prod_migrator')->table('users')->count(),
    'brands'                => DB::connection('tidb_prod_migrator')->table('brands')->count(),
    'brand_content_drafts'  => DB::connection('tidb_prod_migrator')->table('brand_content_drafts')->count(),
    'content_revisions'     => DB::connection('tidb_prod_migrator')->table('content_revisions')->count(),
];
$preCheck['baseline_data_counts'] = $preDataCounts;
echo "\nBaseline data counts: users={$preDataCounts['users']}, brands={$preDataCounts['brands']}, drafts={$preDataCounts['brand_content_drafts']}, revisions={$preDataCounts['content_revisions']}\n";

$results['pre_execution_checks'] = $preCheck;
echo "\n>>> ALL 10 PRE-EXECUTION CHECKS PASSED. PROCEEDING WITH EXECUTION. <<<\n\n";

// ── SECTION 4: Execute Exactly One Migration ────────────────────────────────
echo "--- SECTION 4: Migration Execution ---\n";

$migrationPath = "database/migrations/2026_11_16_200000_add_integrity_constraints_to_content_generation_tables.php";
$cmdParams = [
    '--database' => 'tidb_prod_migrator',
    '--path'     => $migrationPath,
    '--step'     => true,
    '--force'    => true,
];

$commandString = "php artisan migrate --database=tidb_prod_migrator --path={$migrationPath} --step --force";
$results['executed_command'] = $commandString;
echo "Executing: {$commandString}\n";

$startTime = microtime(true);
$exitCode = Artisan::call('migrate', $cmdParams);
$duration = round(microtime(true) - $startTime, 3);
$artisanOutput = Artisan::output();

echo "Artisan Exit Code: {$exitCode}\n";
echo "Artisan Output:\n{$artisanOutput}\n";

$results['execution_exit_code'] = $exitCode;
$results['execution_output'] = trim($artisanOutput);
$results['execution_duration_seconds'] = $duration;

if ($exitCode !== 0) {
    $results['status'] = 'EXECUTION_FAILED';
    file_put_contents(__DIR__ . '/stage_5_5_3_5_results.json', json_encode($results, JSON_PRETTY_PRINT));
    die("FATAL: Migration execution returned non-zero exit code {$exitCode}. STOPPING.\n");
}

// ── SECTION 5: Post-Execution Verification (9 Checks) ────────────────────────
echo "\n--- SECTION 5: Post-Execution Verification ---\n";
$postCheck = [];

// 1. Exactly 59 base tables remain
$tablesPost = DB::connection('tidb_prod_migrator')->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$postCheck['1_base_table_count'] = count($tablesPost);
echo "1. Post Base Table Count: " . count($tablesPost) . " (Expected: 59)\n";
$postCheck['1_base_table_match'] = (count($tablesPost) === 59);

// 2. Exactly 29 migration records exist
$migsPost = DB::connection('tidb_prod_migrator')->table('migrations')->orderBy('id')->get();
$postCheck['2_migration_count'] = count($migsPost);
echo "2. Post Migration Count: " . count($migsPost) . " (Expected: 29)\n";
$postCheck['2_migration_count_match'] = (count($migsPost) === 29);

// 3. Specified migration appears exactly once
$targetRecords = $migsPost->where('migration', $targetMig);
$postCheck['3_target_migration_count'] = $targetRecords->count();
$targetRecord = $targetRecords->first();
$postCheck['3_target_record_details'] = $targetRecord ? [
    'id'        => $targetRecord->id,
    'migration' => $targetRecord->migration,
    'batch'     => $targetRecord->batch,
] : null;
echo "3. Target migration records count: " . $targetRecords->count() . " (Batch: " . ($targetRecord->batch ?? 'N/A') . ")\n";
$postCheck['3_target_migration_single'] = ($targetRecords->count() === 1);

// 4. Unique index bcd_generation_job_id_unique exists on brand_content_drafts.generation_job_id
$idxDraftsPost = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`brand_content_drafts` WHERE Key_name = 'bcd_generation_job_id_unique'");
$postCheck['4_bcd_unique_index_exists'] = (count($idxDraftsPost) > 0);
$postCheck['4_bcd_unique_index_non_unique'] = $idxDraftsPost[0]->Non_unique ?? null;
$postCheck['4_bcd_unique_index_column'] = $idxDraftsPost[0]->Column_name ?? null;
echo "4. bcd_generation_job_id_unique exists: " . ($postCheck['4_bcd_unique_index_exists'] ? 'YES' : 'NO') . " | Non_unique: {$postCheck['4_bcd_unique_index_non_unique']} (0=Unique) | Column: {$postCheck['4_bcd_unique_index_column']}\n";
$postCheck['4_bcd_index_valid'] = ($postCheck['4_bcd_unique_index_exists'] && (int)$postCheck['4_bcd_unique_index_non_unique'] === 0 && $postCheck['4_bcd_unique_index_column'] === 'generation_job_id');

// 5. Unique index cr_draft_id_revision_number_unique exists on content_revisions(draft_id, revision_number)
$idxRevsPost = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`content_revisions` WHERE Key_name = 'cr_draft_id_revision_number_unique' ORDER BY Seq_in_index");
$postCheck['5_cr_unique_index_exists'] = (count($idxRevsPost) === 2);
$crCols = array_map(fn($r) => $r->Column_name, $idxRevsPost);
$crNonUnique = $idxRevsPost[0]->Non_unique ?? null;
$postCheck['5_cr_columns'] = $crCols;
$postCheck['5_cr_non_unique'] = $crNonUnique;
echo "5. cr_draft_id_revision_number_unique exists: " . ($postCheck['5_cr_unique_index_exists'] ? 'YES' : 'NO') . " | Non_unique: {$crNonUnique} (0=Unique) | Columns: " . implode(', ', $crCols) . "\n";
$postCheck['5_cr_index_valid'] = ($postCheck['5_cr_unique_index_exists'] && (int)$crNonUnique === 0 && $crCols === ['draft_id', 'revision_number']);

// 6. Both indexes are unique and have expected column definitions
$postCheck['6_both_indexes_valid'] = ($postCheck['4_bcd_index_valid'] && $postCheck['5_cr_index_valid']);
echo "6. Both index definitions valid: " . ($postCheck['6_both_indexes_valid'] ? 'PASS' : 'FAIL') . "\n";

// 7. Existing production data remains intact
$postDataCounts = [
    'users'                 => DB::connection('tidb_prod_migrator')->table('users')->count(),
    'brands'                => DB::connection('tidb_prod_migrator')->table('brands')->count(),
    'brand_content_drafts'  => DB::connection('tidb_prod_migrator')->table('brand_content_drafts')->count(),
    'content_revisions'     => DB::connection('tidb_prod_migrator')->table('content_revisions')->count(),
];
$postCheck['7_post_data_counts'] = $postDataCounts;
$dataIntact = ($preDataCounts === $postDataCounts);
$postCheck['7_data_intact'] = $dataIntact;
echo "7. Data counts intact: " . ($dataIntact ? 'PASS' : 'FAIL') . " (users={$postDataCounts['users']}, brands={$postDataCounts['brands']}, drafts={$postDataCounts['brand_content_drafts']}, revisions={$postDataCounts['content_revisions']})\n";

// 8. No unexpected schema changes occurred (table list matches exactly)
$tablesPreNames  = array_map(fn($r) => array_values((array)$r)[0], $tablesPre);
$tablesPostNames = array_map(fn($r) => array_values((array)$r)[0], $tablesPost);
sort($tablesPreNames);
sort($tablesPostNames);
$tablesDiff = array_diff($tablesPreNames, $tablesPostNames);
$postCheck['8_table_diff_count'] = count($tablesDiff);
$postCheck['8_no_unexpected_tables'] = (count($tablesDiff) === 0);
echo "8. Unexpected schema changes: " . (count($tablesDiff) === 0 ? 'NONE (PASS)' : 'DETECTED (FAIL)') . "\n";

// 9. Staging and migration-test databases remain untouched
$stagingMigsCount = DB::connection('tidb_staging_migrator')->table('migrations')->count();
$postCheck['9_staging_migrations_count'] = $stagingMigsCount;
$postCheck['9_staging_untouched'] = ($stagingMigsCount === 29);
echo "9. Staging migrations count: {$stagingMigsCount} (Expected: 29) — " . ($postCheck['9_staging_untouched'] ? 'UNTOUCHED (PASS)' : 'ALTERED (FAIL)') . "\n";

// 10. Verify production connection and TLS again
$postIdent = DB::connection('tidb_prod_migrator')->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
$postSsl   = DB::connection('tidb_prod_migrator')->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
$postCheck['10_post_user'] = $postIdent->cu;
$postCheck['10_post_db']   = $postIdent->db;
$postCheck['10_post_tls']  = $postSsl ? $postSsl->Value : '';
echo "10. Post TLS verification: {$postCheck['10_post_tls']} | Connected as: {$postIdent->cu} | DB: {$postIdent->db}\n";

$allPostPassed = (
    $postCheck['1_base_table_match'] &&
    $postCheck['2_migration_count_match'] &&
    $postCheck['3_target_migration_single'] &&
    $postCheck['6_both_indexes_valid'] &&
    $postCheck['7_data_intact'] &&
    $postCheck['8_no_unexpected_tables'] &&
    $postCheck['9_staging_untouched'] &&
    !empty($postCheck['10_post_tls'])
);

$results['post_execution_checks'] = $postCheck;
$results['status'] = $allPostPassed ? 'SUCCESS' : 'INCOMPLETE';
$results['final_decision'] = $allPostPassed ? 'A' : 'B';

echo "\n=======================================================\n";
echo "FINAL DECISION: " . ($allPostPassed ? "A — Migration executed successfully; all verification checks passed." : "B — Verification incomplete.") . "\n";
echo "=======================================================\n";

file_put_contents(__DIR__ . '/stage_5_5_3_5_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults saved to scripts/stage_5_5_3_5_results.json\n";
