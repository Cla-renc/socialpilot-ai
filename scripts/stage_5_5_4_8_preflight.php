<?php
/**
 * STAGE 5.5.4.8 — T-06 Internal AI Content Generation Preflight
 * STRICT READ-ONLY SCRIPT:
 * - Queries row counts for brand_content_drafts, content_revisions, content_generation_jobs, content_variations, agent_executions
 * - Inspects unique constraints added by migration 29
 * - Inspects foreign key constraints on content generation tables
 * - Checks users and brands count
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.4.8 — T-06 Internal AI Content Generation Preflight',
    'mode' => 'STRICT READ-ONLY',
    'db_connection' => [],
    'table_counts' => [],
    'migration_29_constraints' => [],
    'foreign_keys' => [],
];

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

try {
    $conn = DB::connection('tidb_prod_app');
    $ident = $conn->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
    $ssl = $conn->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");

    $results['db_connection'] = [
        'database' => $ident->db,
        'user' => $ident->cu,
        'tls_cipher' => $ssl ? $ssl->Value : 'None',
        'is_socialpilot' => ($ident->db === 'socialpilot'),
        'is_prod_app' => str_contains($ident->cu, 'prod_app'),
    ];

    $tablesToCount = [
        'users',
        'brands',
        'brand_content_drafts',
        'content_revisions',
        'content_generation_jobs',
        'content_variations',
        'agent_executions',
        'content_generation_settings',
    ];

    foreach ($tablesToCount as $tbl) {
        $cnt = $conn->table($tbl)->count();
        $results['table_counts'][$tbl] = $cnt;
        echo sprintf("  %-30s : %d rows\n", $tbl, $cnt);
    }

    // Inspect Migration 29 constraints
    $bcdIndexes = $conn->select("SHOW INDEXES FROM `brand_content_drafts` WHERE Key_name = 'bcd_generation_job_id_unique'");
    $crIndexes = $conn->select("SHOW INDEXES FROM `content_revisions` WHERE Key_name = 'cr_draft_id_revision_number_unique'");

    $results['migration_29_constraints'] = [
        'bcd_generation_job_id_unique' => !empty($bcdIndexes),
        'cr_draft_id_revision_number_unique' => !empty($crIndexes),
    ];
    echo "  Migration 29 Unique on brand_content_drafts.generation_job_id: " . (!empty($bcdIndexes) ? 'PRESENT' : 'MISSING') . "\n";
    echo "  Migration 29 Unique on content_revisions(draft_id, revision_number): " . (!empty($crIndexes) ? 'PRESENT' : 'MISSING') . "\n";

    // Inspect foreign keys
    $fks = $conn->select("
        SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE REFERENCED_TABLE_NAME IN ('brands', 'brand_content_drafts', 'content_generation_jobs')
          AND CONSTRAINT_SCHEMA = 'socialpilot'
    ");
    foreach ($fks as $fk) {
        $results['foreign_keys'][] = [
            'table' => $fk->TABLE_NAME,
            'referenced' => $fk->REFERENCED_TABLE_NAME,
            'constraint' => $fk->CONSTRAINT_NAME,
            'delete_rule' => $fk->DELETE_RULE,
        ];
    }

    file_put_contents(__DIR__ . '/stage_5_5_4_8_preflight_results.json', json_encode($results, JSON_PRETTY_PRINT));
    echo "\nResults saved to scripts/stage_5_5_4_8_preflight_results.json\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
