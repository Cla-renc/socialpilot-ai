<?php
/**
 * STAGE 5.5.4.4 — Production Authentication & Identity Architecture Audit
 * STRICT READ-ONLY SCRIPT:
 * - Inspects columns, data types, indexes and constraints of `users` table
 * - Inspects `personal_access_tokens` table
 * - Inspects foreign key relationships pointing to `users.id`
 * - Verifies row count and ID of existing user(s) without printing PII
 * - Checks existing brand ownership
 * - Redacts all personal info (name, email, etc.)
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.4.4 — Production Authentication & Identity Provisioning Preflight',
    'mode' => 'STRICT READ-ONLY',
    'users_table_schema' => [],
    'users_indexes' => [],
    'personal_access_tokens_schema' => [],
    'existing_user_count' => 0,
    'existing_user_ids' => [],
    'existing_brands' => [],
    'foreign_keys_to_users' => [],
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

    // 1. Columns of `users`
    $cols = $conn->select("SHOW COLUMNS FROM `users`");
    foreach ($cols as $col) {
        $results['users_table_schema'][] = [
            'field' => $col->Field,
            'type' => $col->Type,
            'null' => $col->Null,
            'key' => $col->Key,
            'default' => $col->Default,
            'extra' => $col->Extra,
        ];
    }

    // 2. Indexes on `users`
    $idxs = $conn->select("SHOW INDEXES FROM `users`");
    foreach ($idxs as $idx) {
        $results['users_indexes'][] = [
            'key_name' => $idx->Key_name,
            'column_name' => $idx->Column_name,
            'non_unique' => $idx->Non_unique,
        ];
    }

    // 3. Columns of `personal_access_tokens`
    $patCols = $conn->select("SHOW COLUMNS FROM `personal_access_tokens`");
    foreach ($patCols as $col) {
        $results['personal_access_tokens_schema'][] = [
            'field' => $col->Field,
            'type' => $col->Type,
            'null' => $col->Null,
            'key' => $col->Key,
        ];
    }

    // 4. Count of existing users
    $userCount = $conn->table('users')->count();
    $userIds = $conn->table('users')->pluck('id')->toArray();
    $results['existing_user_count'] = $userCount;
    $results['existing_user_ids'] = $userIds;

    // 5. Existing brands
    $brands = $conn->table('brands')->select('id', 'user_id', 'status')->get();
    foreach ($brands as $b) {
        $results['existing_brands'][] = [
            'id' => $b->id,
            'user_id' => $b->user_id,
            'status' => $b->status,
        ];
    }

    // 6. Foreign key references to `users`
    $fks = $conn->select("
        SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_NAME = 'users' AND TABLE_SCHEMA = 'socialpilot'
    ");
    foreach ($fks as $fk) {
        $results['foreign_keys_to_users'][] = [
            'table' => $fk->TABLE_NAME,
            'column' => $fk->COLUMN_NAME,
            'constraint' => $fk->CONSTRAINT_NAME,
        ];
    }

    file_put_contents(__DIR__ . '/stage_5_5_4_4_preflight_results.json', json_encode($results, JSON_PRETTY_PRINT));
    echo "SUCCESS: Results saved to scripts/stage_5_5_4_4_preflight_results.json\n";
    echo "Existing Users: " . count($userIds) . " (IDs: " . implode(', ', $userIds) . ")\n";
    echo "Existing Brands: " . count($brands) . "\n";
    echo "Foreign Keys to Users: " . count($fks) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
