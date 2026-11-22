<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pdo = Illuminate\Support\Facades\DB::connection('tidb_staging_app')->getPdo();

$jobsCount = $pdo->query('SELECT count(*) FROM `socialpilot_staging`.`jobs`')->fetchColumn();
$failedJobsCount = $pdo->query('SELECT count(*) FROM `socialpilot_staging`.`failed_jobs`')->fetchColumn();
$batchesCount = $pdo->query('SELECT count(*) FROM `socialpilot_staging`.`job_batches`')->fetchColumn();
$mediaAssetsCount = $pdo->query('SELECT count(*) FROM `socialpilot_staging`.`media_assets`')->fetchColumn();

echo json_encode([
    'database' => 'socialpilot_staging',
    'pending_jobs' => (int) $jobsCount,
    'failed_jobs' => (int) $failedJobsCount,
    'job_batches' => (int) $batchesCount,
    'media_assets_count' => (int) $mediaAssetsCount,
], JSON_PRETTY_PRINT) . PHP_EOL;
