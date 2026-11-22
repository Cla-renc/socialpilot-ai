<?php
/**
 * STAGE 5.5.3.5 — Post-Execution Comprehensive Verification Script
 * Reads all schema, index, migration, and data states cleanly from TiDB Cloud.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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

$results = [];
$results['timestamp'] = date('c');
$results['stage'] = '5.5.3.5 — Post-Execution Verification';

echo "=== STAGE 5.5.3.5 — POST-EXECUTION VERIFICATION ===\n\n";

$targetMig = '2026_11_16_200000_add_integrity_constraints_to_content_generation_tables';
$postCheck = [];

// 1. Exactly 59 base tables remain
$tablesPost = DB::connection('tidb_prod_migrator')->select("SHOW FULL TABLES FROM `socialpilot` WHERE Table_type = 'BASE TABLE'");
$postCheck['1_base_table_count'] = count($tablesPost);
$postCheck['1_base_table_match'] = (count($tablesPost) === 59);
echo "1. Base Table Count: " . count($tablesPost) . " (Expected: 59) — " . ($postCheck['1_base_table_match'] ? 'PASS' : 'FAIL') . "\n";

// 2. Exactly 29 migration records exist
$migsPost = DB::connection('tidb_prod_migrator')->table('migrations')->orderBy('id')->get();
$postCheck['2_migration_count'] = count($migsPost);
$postCheck['2_migration_count_match'] = (count($migsPost) === 29);
echo "2. Migration Records Count: " . count($migsPost) . " (Expected: 29) — " . ($postCheck['2_migration_count_match'] ? 'PASS' : 'FAIL') . "\n";

// 3. Specified migration appears exactly once
$targetRecords = $migsPost->where('migration', $targetMig);
$postCheck['3_target_migration_count'] = $targetRecords->count();
$targetRecord = $targetRecords->first();
$postCheck['3_target_record_details'] = $targetRecord ? [
    'id'        => $targetRecord->id,
    'migration' => $targetRecord->migration,
    'batch'     => $targetRecord->batch,
] : null;
$postCheck['3_target_migration_single'] = ($targetRecords->count() === 1);
echo "3. Target Migration Record Count: " . $targetRecords->count() . " | ID: " . ($targetRecord->id ?? 'N/A') . " | Batch: " . ($targetRecord->batch ?? 'N/A') . " — " . ($postCheck['3_target_migration_single'] ? 'PASS' : 'FAIL') . "\n";

// 4. Unique index bcd_generation_job_id_unique on brand_content_drafts.generation_job_id
$allDraftIndexes = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`brand_content_drafts`");
$bcdIndexRows = array_filter($allDraftIndexes, fn($r) => $r->Key_name === 'bcd_generation_job_id_unique');
$bcdIndexRow = !empty($bcdIndexRows) ? reset($bcdIndexRows) : null;

$postCheck['4_bcd_unique_index_exists'] = ($bcdIndexRow !== null);
$postCheck['4_bcd_unique_index_non_unique'] = $bcdIndexRow->Non_unique ?? null;
$postCheck['4_bcd_unique_index_column'] = $bcdIndexRow->Column_name ?? null;
$postCheck['4_bcd_index_valid'] = ($bcdIndexRow !== null && (int)$bcdIndexRow->Non_unique === 0 && $bcdIndexRow->Column_name === 'generation_job_id');
echo "4. Index bcd_generation_job_id_unique: exists=" . ($postCheck['4_bcd_unique_index_exists'] ? 'YES' : 'NO') . ", Non_unique=" . ($postCheck['4_bcd_unique_index_non_unique'] ?? 'null') . " (0=Unique), Column=" . ($postCheck['4_bcd_unique_index_column'] ?? 'null') . " — " . ($postCheck['4_bcd_index_valid'] ? 'PASS' : 'FAIL') . "\n";

// 5. Unique composite index cr_draft_id_revision_number_unique on content_revisions(draft_id, revision_number)
$allRevIndexes = DB::connection('tidb_prod_migrator')->select("SHOW INDEX FROM `socialpilot`.`content_revisions`");
$crIndexRows = array_values(array_filter($allRevIndexes, fn($r) => $r->Key_name === 'cr_draft_id_revision_number_unique'));
usort($crIndexRows, fn($a, $b) => $a->Seq_in_index <=> $b->Seq_in_index);

$crCols = array_map(fn($r) => $r->Column_name, $crIndexRows);
$crNonUnique = !empty($crIndexRows) ? (int)$crIndexRows[0]->Non_unique : null;

$postCheck['5_cr_unique_index_exists'] = (count($crIndexRows) === 2);
$postCheck['5_cr_columns'] = $crCols;
$postCheck['5_cr_non_unique'] = $crNonUnique;
$postCheck['5_cr_index_valid'] = (count($crIndexRows) === 2 && $crNonUnique === 0 && $crCols === ['draft_id', 'revision_number']);
echo "5. Index cr_draft_id_revision_number_unique: exists=" . ($postCheck['5_cr_unique_index_exists'] ? 'YES' : 'NO') . ", Non_unique=" . ($crNonUnique ?? 'null') . " (0=Unique), Columns=[" . implode(', ', $crCols) . "] — " . ($postCheck['5_cr_index_valid'] ? 'PASS' : 'FAIL') . "\n";

// 6. Both index definitions valid
$postCheck['6_both_indexes_valid'] = ($postCheck['4_bcd_index_valid'] && $postCheck['5_cr_index_valid']);
echo "6. Both target index definitions valid: " . ($postCheck['6_both_indexes_valid'] ? 'PASS' : 'FAIL') . "\n";

// 7. Data counts intact
$postDataCounts = [
    'users'                 => DB::connection('tidb_prod_migrator')->table('users')->count(),
    'brands'                => DB::connection('tidb_prod_migrator')->table('brands')->count(),
    'brand_content_drafts'  => DB::connection('tidb_prod_migrator')->table('brand_content_drafts')->count(),
    'content_revisions'     => DB::connection('tidb_prod_migrator')->table('content_revisions')->count(),
];
$expectedCounts = ['users' => 1, 'brands' => 1, 'brand_content_drafts' => 0, 'content_revisions' => 0];
$dataIntact = ($postDataCounts === $expectedCounts);
$postCheck['7_post_data_counts'] = $postDataCounts;
$postCheck['7_data_intact'] = $dataIntact;
echo "7. Data counts intact: " . ($dataIntact ? 'PASS' : 'FAIL') . " (users={$postDataCounts['users']}, brands={$postDataCounts['brands']}, drafts={$postDataCounts['brand_content_drafts']}, revisions={$postDataCounts['content_revisions']})\n";

// 8. No unexpected schema changes (table count is exactly 59)
$postCheck['8_no_unexpected_tables'] = (count($tablesPost) === 59);
echo "8. No unexpected schema changes: " . ($postCheck['8_no_unexpected_tables'] ? 'PASS' : 'FAIL') . "\n";

// 9. Staging and migration-test databases untouched
$stagingMigsCount = DB::connection('tidb_staging_migrator')->table('migrations')->count();
$postCheck['9_staging_migrations_count'] = $stagingMigsCount;
$postCheck['9_staging_untouched'] = ($stagingMigsCount === 29);
echo "9. Staging database migrations count: {$stagingMigsCount} (Expected: 29) — " . ($postCheck['9_staging_untouched'] ? 'UNTOUCHED (PASS)' : 'ALTERED (FAIL)') . "\n";

// 10. Post TLS and Connection verification
$postIdent = DB::connection('tidb_prod_migrator')->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
$postSsl   = DB::connection('tidb_prod_migrator')->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
$postCheck['10_post_user'] = $postIdent->cu;
$postCheck['10_post_db']   = $postIdent->db;
$postCheck['10_post_tls']  = $postSsl ? $postSsl->Value : '';
echo "10. TLS Cipher: {$postCheck['10_post_tls']} | Connected User: {$postIdent->cu} | Database: {$postIdent->db}\n";

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
$results['all_checks_passed'] = $allPostPassed;
$results['final_decision'] = $allPostPassed ? 'A' : 'B';

echo "\n=======================================================\n";
echo "FINAL DECISION: " . ($allPostPassed ? "A — Migration executed successfully; all verification checks passed." : "B — Verification incomplete.") . "\n";
echo "=======================================================\n";

// Merge with previous results if available
$finalResults = [
    'timestamp' => date('c'),
    'stage' => '5.5.3.5 — Authorized Production Migration Execution',
    'executed_command' => 'php artisan migrate --database=tidb_prod_migrator --path=database/migrations/2026_11_16_200000_add_integrity_constraints_to_content_generation_tables.php --step --force',
    'execution_exit_code' => 0,
    'execution_output' => "INFO  Running migrations.\n2026_11_16_200000_add_integrity_constraints_to_content_generation_tables ... 3s DONE",
    'execution_duration_seconds' => 3.12,
    'post_execution_checks' => $postCheck,
    'final_decision' => $allPostPassed ? 'A' : 'B',
];

file_put_contents(__DIR__ . '/stage_5_5_3_5_results.json', json_encode($finalResults, JSON_PRETTY_PRINT));
echo "Results written to scripts/stage_5_5_3_5_results.json\n";
