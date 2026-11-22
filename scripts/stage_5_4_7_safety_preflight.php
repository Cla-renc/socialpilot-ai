<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pdo = Illuminate\Support\Facades\DB::connection('tidb_staging_app')->getPdo();

$db = $pdo->query('SELECT DATABASE()')->fetchColumn();
$user = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
$ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);
$users = $pdo->query('SELECT id, name, email FROM users')->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'database' => $db,
    'current_user' => $user,
    'ssl_status' => $ssl,
    'users_count' => count($users),
    'staging_users' => $users,
], JSON_PRETTY_PRINT) . PHP_EOL;
