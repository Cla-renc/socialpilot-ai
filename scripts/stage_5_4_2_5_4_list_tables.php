<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
echo "TOTAL: " . count($tables) . PHP_EOL;
foreach ($tables as $i => $t) {
    echo ($i + 1) . ". " . $t . PHP_EOL;
}
