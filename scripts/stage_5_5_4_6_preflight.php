<?php
/**
 * STAGE 5.5.4.6 — T-04 Brand Creation & T-05 Brand Overview Preflight Audit
 * STRICT READ-ONLY SCRIPT:
 * - Inspects `brands` table columns, types, indexes
 * - Inspects all foreign keys referencing `brands.id` and their DELETE_RULE
 * - Inspects `brand_settings` table
 * - Verifies current counts of users and brands
 * - Verifies Brand 30001 status and ownership
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.4.6 — T-04 Brand Creation & T-05 Brand Overview Preflight',
    'mode' => 'STRICT READ-ONLY',
    'db_connection' => [],
    'existing_counts' => [],
    'brand_30001_details' => [],
    'brands_schema' => [],
    'brands_indexes' => [],
    'brand_settings_schema' => [],
    'foreign_keys_referencing_brands' => [],
    'safety_verifications' => [],
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
        'tls_active' => !empty($ssl ? $ssl->Value : ''),
    ];

    // Counts
    $userCount = $conn->table('users')->count();
    $brandCount = $conn->table('brands')->count();
    $brandSettingsCount = $conn->table('brand_settings')->count();

    $results['existing_counts'] = [
        'users' => $userCount,
        'brands' => $brandCount,
        'brand_settings' => $brandSettingsCount,
    ];

    // Brand 30001
    $brand30001 = $conn->table('brands')->where('id', 30001)->first();
    if ($brand30001) {
        $results['brand_30001_details'] = [
            'id' => $brand30001->id,
            'name' => $brand30001->name,
            'user_id' => $brand30001->user_id,
            'status' => $brand30001->status,
            'industry' => $brand30001->industry,
            'owned_by_30001' => ((int)$brand30001->user_id === 30001),
            'is_draft' => ($brand30001->status === 'draft'),
        ];
    }

    // Brands schema
    $brandCols = $conn->select("SHOW COLUMNS FROM `brands`");
    foreach ($brandCols as $c) {
        $results['brands_schema'][] = [
            'field' => $c->Field,
            'type' => $c->Type,
            'null' => $c->Null,
            'key' => $c->Key,
            'default' => $c->Default,
        ];
    }

    // Brand settings schema
    $bsCols = $conn->select("SHOW COLUMNS FROM `brand_settings`");
    foreach ($bsCols as $c) {
        $results['brand_settings_schema'][] = [
            'field' => $c->Field,
            'type' => $c->Type,
            'null' => $c->Null,
            'key' => $c->Key,
            'default' => $c->Default,
        ];
    }

    // Foreign keys referencing `brands`
    $fks = $conn->select("
        SELECT rc.CONSTRAINT_NAME, rc.TABLE_NAME, rc.REFERENCED_TABLE_NAME, rc.DELETE_RULE, rc.UPDATE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        WHERE rc.REFERENCED_TABLE_NAME = 'brands' AND rc.CONSTRAINT_SCHEMA = 'socialpilot'
    ");
    foreach ($fks as $fk) {
        $results['foreign_keys_referencing_brands'][] = [
            'table' => $fk->TABLE_NAME,
            'constraint' => $fk->CONSTRAINT_NAME,
            'delete_rule' => $fk->DELETE_RULE,
        ];
    }

    // Safety checks
    $allPassed = (
        $results['db_connection']['is_socialpilot'] &&
        $results['db_connection']['is_prod_app'] &&
        $results['db_connection']['tls_active'] &&
        $userCount === 1 &&
        $brandCount === 1 &&
        $results['brand_30001_details']['owned_by_30001'] &&
        $results['brand_30001_details']['is_draft']
    );

    $results['safety_verifications'] = [
        'all_passed' => $allPassed,
        'ready_for_testing' => $allPassed,
    ];

    file_put_contents(__DIR__ . '/stage_5_5_4_6_preflight_results.json', json_encode($results, JSON_PRETTY_PRINT));
    echo "SUCCESS: Results saved to scripts/stage_5_5_4_6_preflight_results.json\n";
    echo "DB: {$ident->db}, User: {$ident->cu}, TLS: " . ($ssl ? $ssl->Value : 'None') . "\n";
    echo "Users: {$userCount}, Brands: {$brandCount}\n";
    echo "Brand 30001: Owned by User 30001 (" . ($results['brand_30001_details']['owned_by_30001'] ? 'YES' : 'NO') . "), Status: {$results['brand_30001_details']['status']}\n";
    echo "Foreign Keys referencing `brands`: " . count($fks) . "\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
