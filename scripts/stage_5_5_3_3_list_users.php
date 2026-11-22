<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$conn = 'tidb_migration_test';
$users = DB::connection($conn)->select('SELECT user, host, plugin FROM mysql.user ORDER BY user');
foreach ($users as $u) {
    echo $u->user . '@' . $u->host . '  plugin=' . $u->plugin . PHP_EOL;
}
