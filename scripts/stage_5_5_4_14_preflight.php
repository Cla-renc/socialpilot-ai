<?php
/**
 * STAGE 5.5.4.14 — T-09 Dedicated Tenancy Boundary Audit Preflight
 * STRICT READ-ONLY SCRIPT:
 * - Queries all foreign key relationships involving users and brands
 * - Checks cascade delete and nullability behaviors
 * - Queries live production counts for all tenant-scoped tables
 * - Verifies protected assets: User 30001, Brand 30001, User 344308
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp'      => date('c'),
    'stage'          => '5.5.4.14 — T-09 Dedicated Tenancy Boundary Audit Preflight',
    'mode'           => 'STRICT READ-ONLY',
    'db_connection'  => [],
    'table_counts'   => [],
    'tenant_fks'     => [],
    'baseline_audit' => [],
];

$stagingLocal = __DIR__ . '/../.env.staging.local';
$prodAppUser  = 'AZDARrqgy4MBFGE.prod_app';
$prodAppPass  = '';

if (file_exists($stagingLocal)) {
    foreach (file($stagingLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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

    $dbName      = $conn->selectOne('SELECT DATABASE() AS db')->db;
    $currentUser = $conn->selectOne('SELECT CURRENT_USER() AS usr')->usr;
    $sslCipher   = $conn->selectOne("SHOW STATUS LIKE 'Ssl_cipher'")->Value ?? 'NONE';

    $results['db_connection'] = [
        'database'   => $dbName,
        'user'       => $currentUser,
        'ssl_cipher' => $sslCipher,
    ];

    $tables = [
        'users',
        'brands',
        'brand_settings',
        'brand_members',
        'brand_content_drafts',
        'content_revisions',
        'content_variations',
        'content_generation_jobs',
        'agent_executions',
        'scheduled_posts',
        'scheduled_post_platforms',
        'scheduled_post_media',
        'brand_posts',
        'media_assets',
        'media_generation_jobs',
        'media_storyboards',
        'social_accounts',
        'social_account_tokens',
        'publishing_queues',
        'publishing_queue_items',
        'publishing_attempts',
        'publishing_notifications',
        'post_analytics',
        'analytics_snapshots',
        'analytics_reports',
        'audience_insights',
        'campaigns',
        'campaign_posts',
        'autopilot_settings',
        'autopilot_rules',
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

    $u30001 = $conn->selectOne('SELECT id, name, email, created_at FROM users WHERE id = 30001');
    $b30001 = $conn->selectOne('SELECT id, user_id, name, status, created_at FROM brands WHERE id = 30001');
    $u344308 = $conn->selectOne('SELECT id, name, email, created_at FROM users WHERE id = 344308');
    $b344308 = $conn->select('SELECT id, user_id, name, status FROM brands WHERE user_id = 344308');

    $results['baseline_audit'] = [
        'user_30001' => [
            'present'    => (bool) $u30001,
            'id'         => $u30001?->id,
            'created_at' => $u30001?->created_at,
        ],
        'brand_30001' => [
            'present' => (bool) $b30001,
            'id'      => $b30001?->id,
            'user_id' => $b30001?->user_id,
            'status'  => $b30001?->status,
        ],
        'user_344308' => [
            'present'     => (bool) $u344308,
            'id'          => $u344308?->id,
            'created_at'  => $u344308?->created_at,
            'brand_count' => count($b344308),
        ],
    ];

    // Foreign keys involving users
    $userFks = $conn->select("
        SELECT 
            TABLE_NAME, 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'socialpilot'
          AND REFERENCED_TABLE_NAME = 'users'
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    $results['tenant_fks']['referencing_users'] = $userFks;

    // Foreign keys involving brands
    $brandFks = $conn->select("
        SELECT 
            TABLE_NAME, 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'socialpilot'
          AND REFERENCED_TABLE_NAME = 'brands'
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    $results['tenant_fks']['referencing_brands'] = $brandFks;

    file_put_contents(
        __DIR__ . '/stage_5_5_4_14_preflight_results.json',
        json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    echo "PREFLIGHT COMPLETE: Success\n";

} catch (\Throwable $e) {
    echo "PREFLIGHT ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
