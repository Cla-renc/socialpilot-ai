<?php
/**
 * STAGE 5.5.4.12 — T-08 Synthetic Media Probe Preflight
 * STRICT READ-ONLY AUDIT:
 * - Queries row counts for media_assets, media_generation_jobs, media_storyboards,
 *   scheduled_post_media, users, brands
 * - Inspects foreign keys referencing media_assets
 * - Verifies User 30001 & Brand 30001 baseline state
 * - Audits Supabase storage configuration (without exposing secret keys)
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp'       => date('c'),
    'stage'           => '5.5.4.12 — T-08 Synthetic Media Probe Preflight',
    'mode'            => 'STRICT READ-ONLY',
    'storage_config'  => [],
    'db_connection'   => [],
    'table_counts'    => [],
    'foreign_keys'    => [],
    'columns'         => [],
    'baseline_audit'  => [],
];

// 1. Audit storage configuration (sanitized, zero secrets)
$mediaDisk = config('services.media.storage_disk', 'local');
$diskConfig = config("filesystems.disks.{$mediaDisk}", []);

$results['storage_config'] = [
    'configured_media_disk'   => $mediaDisk,
    'disk_driver'             => $diskConfig['driver'] ?? 'unknown',
    'bucket'                  => $diskConfig['bucket'] ?? 'unknown',
    'region'                  => $diskConfig['region'] ?? 'unknown',
    'endpoint'                => $diskConfig['endpoint'] ?? 'unknown',
    'use_path_style_endpoint' => $diskConfig['use_path_style_endpoint'] ?? false,
    'public_url_base'         => $diskConfig['url'] ?? 'unknown',
    'has_access_key'          => !empty($diskConfig['key']),
    'access_key_length'       => strlen($diskConfig['key'] ?? ''),
    'has_secret_key'          => !empty($diskConfig['secret']),
    'secret_key_length'       => strlen($diskConfig['secret'] ?? ''),
    'max_upload_size_mb'      => config('services.media.max_upload_size_mb', 20),
    'image_provider'          => config('services.media.image_provider', 'none'),
    'video_provider'          => config('services.media.video_provider', 'none'),
];

// 2. Database connection via prod_app
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
        'media_assets',
        'media_generation_jobs',
        'media_storyboards',
        'scheduled_post_media',
        'brand_content_drafts',
        'scheduled_posts',
        'publishing_attempts',
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

    $u30001 = $conn->selectOne('SELECT id, name, email FROM users WHERE id = 30001');
    $b30001 = $conn->selectOne('SELECT id, user_id, name, status FROM brands WHERE id = 30001');

    $results['baseline_audit'] = [
        'user_30001_present'         => (bool) $u30001,
        'user_30001_id'              => $u30001?->id,
        'brand_30001_present'        => (bool) $b30001,
        'brand_30001_id'             => $b30001?->id,
        'brand_30001_user_id'        => $b30001?->user_id,
        'brand_30001_status'         => $b30001?->status,
    ];

    $fks = $conn->select("
        SELECT 
            TABLE_NAME, 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'socialpilot'
          AND REFERENCED_TABLE_NAME = 'media_assets'
    ");
    $results['foreign_keys']['referencing_media_assets'] = $fks;

    $cols = $conn->select("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'socialpilot' AND TABLE_NAME = 'media_assets'
        ORDER BY ORDINAL_POSITION
    ");
    $results['columns']['media_assets'] = $cols;

    file_put_contents(
        __DIR__ . '/stage_5_5_4_12_preflight_results.json',
        json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    echo "PREFLIGHT COMPLETE: Success\n";

} catch (\Throwable $e) {
    echo "PREFLIGHT ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
