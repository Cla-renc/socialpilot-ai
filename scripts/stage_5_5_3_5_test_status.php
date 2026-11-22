<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

// Load password from .env.staging.local
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

// Test migrate:status
Artisan::call('migrate:status', ['--database' => 'tidb_prod_migrator']);
echo Artisan::output();
