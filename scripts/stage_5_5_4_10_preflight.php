<?php
/**
 * STAGE 5.5.4.10 — T-07 Post Scheduling Preflight
 * STRICT READ-ONLY SCRIPT:
 * - Queries row counts for scheduled_posts, scheduled_post_platforms, scheduled_post_media,
 *   brand_posts, publishing_queues, publishing_queue_items, publishing_attempts, post_analytics,
 *   jobs, failed_jobs, notifications, users, brands
 * - Inspects foreign key constraints referencing scheduled_posts
 * - Verifies User 30001 and Brand 30001 baseline state
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.4.10 — T-07 Post Scheduling Preflight',
    'mode' => 'STRICT READ-ONLY',
    'db_connection' => [],
    'table_counts' => [],
    'foreign_keys' => [],
    'columns' => [],
    'baseline_audit' => [],
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

    // 1. Connection identity
    $dbName = $conn->selectOne('SELECT DATABASE() AS db')->db;
    $currentUser = $conn->selectOne('SELECT CURRENT_USER() AS usr')->usr;
    $sslCipher = $conn->selectOne("SHOW STATUS LIKE 'Ssl_cipher'")->Value ?? 'NONE';

    $results['db_connection'] = [
        'database' => $dbName,
        'user' => $currentUser,
        'ssl_cipher' => $sslCipher,
    ];

    // 2. Counts
    $tables = [
        'users',
        'brands',
        'brand_posts',
        'scheduled_posts',
        'scheduled_post_platforms',
        'scheduled_post_media',
        'publishing_queues',
        'publishing_queue_items',
        'publishing_attempts',
        'post_analytics',
        'publishing_notifications',
        'jobs',
        'failed_jobs',
        'notifications',
        'personal_access_tokens',
    ];

    foreach ($tables as $tbl) {
        try {
            $cnt = $conn->selectOne("SELECT COUNT(*) AS cnt FROM `{$tbl}`")->cnt;
            $results['table_counts'][$tbl] = (int) $cnt;
        } catch (\Throwable $e) {
            $results['table_counts'][$tbl] = 'ERROR: ' . $e->getMessage();
        }
    }

    // 3. User 30001 & Brand 30001
    $u30001 = $conn->selectOne('SELECT id, name, email, created_at FROM users WHERE id = 30001');
    $b30001 = $conn->selectOne('SELECT id, user_id, name, status, created_at FROM brands WHERE id = 30001');

    $results['baseline_audit'] = [
        'user_30001_present' => (bool) $u30001,
        'user_30001_id' => $u30001?->id,
        'brand_30001_present' => (bool) $b30001,
        'brand_30001_id' => $b30001?->id,
        'brand_30001_user_id' => $b30001?->user_id,
        'brand_30001_status' => $b30001?->status,
    ];

    // 4. Foreign keys referencing scheduled_posts or brands
    $fks = $conn->select("
        SELECT 
            TABLE_NAME, 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'socialpilot'
          AND REFERENCED_TABLE_NAME = 'scheduled_posts'
    ");
    $results['foreign_keys']['referencing_scheduled_posts'] = $fks;

    // 5. Columns of scheduled_posts
    $cols = $conn->select("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'socialpilot' AND TABLE_NAME = 'scheduled_posts'
        ORDER BY ORDINAL_POSITION
    ");
    $results['columns']['scheduled_posts'] = $cols;

    // 6. Columns of brand_posts
    $bcols = $conn->select("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'socialpilot' AND TABLE_NAME = 'brand_posts'
        ORDER BY ORDINAL_POSITION
    ");
    $results['columns']['brand_posts'] = $bcols;

    file_put_contents(
        __DIR__ . '/stage_5_5_4_10_preflight_results.json',
        json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    echo "PREFLIGHT COMPLETE: Success\n";

} catch (\Throwable $e) {
    echo "PREFLIGHT ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
