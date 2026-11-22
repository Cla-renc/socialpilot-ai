<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pdo = Illuminate\Support\Facades\DB::connection('tidb_staging_app')->getPdo();

$db = $pdo->query('SELECT DATABASE()')->fetchColumn();
$user = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
$ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);

$tablesCount = $pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema = 'socialpilot_staging' AND table_type = 'BASE TABLE'")->fetchColumn();
$migrationsCount = $pdo->query("SELECT count(*) FROM `socialpilot_staging`.`migrations`")->fetchColumn();

// Isolation check
$prodIsolation = false;
try {
    $pdo->query("SELECT count(*) FROM `socialpilot`.`users`");
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), '42000') || str_contains($e->getMessage(), 'Access denied')) {
        $prodIsolation = true;
    }
}

$testIsolation = false;
try {
    $pdo->query("SELECT count(*) FROM `socialpilot_migration_test`.`users`");
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), '42000') || str_contains($e->getMessage(), 'Access denied')) {
        $testIsolation = true;
    }
}

echo json_encode([
    'database' => $db,
    'user' => $user,
    'ssl_cipher' => $ssl['Value'] ?? null,
    'base_tables_count' => (int) $tablesCount,
    'migrations_count' => (int) $migrationsCount,
    'production_isolated' => $prodIsolation,
    'test_isolated' => $testIsolation,
], JSON_PRETTY_PRINT) . PHP_EOL;
