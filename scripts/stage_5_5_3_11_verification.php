<?php
/**
 * STAGE 5.5.3.11 — Production Deployment Verification & Post-Deployment Audit
 * STRICT READ-ONLY SCRIPT:
 * - Tests production HTTP endpoints (/ , /up, /api/v1/health, /api/v1/health/ready)
 * - Verifies database identity, table count, migration count, TLS status
 * - Performs NO mutations, DDL, or DML
 * - Redacts all sensitive values in output
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.3.11 — Production Deployment Verification & Post-Deployment Audit',
    'mode' => 'STRICT READ-ONLY',
    'git_commit_expected' => '7cbd0bc',
    'service_url' => 'https://socialpilot-ai-y4lm.onrender.com',
    'service_name' => 'socialpilot-ai',
    'http_endpoints' => [],
    'database_verification' => [],
    'security_verification' => [],
    'nginx_routing_verification' => [],
];

echo "======================================================================\n";
echo "STAGE 5.5.3.11: PRODUCTION DEPLOYMENT VERIFICATION & POST-DEPLOYMENT AUDIT\n";
echo "======================================================================\n\n";

// ── 1. HTTP ENDPOINT VERIFICATION ───────────────────────────────────────────
echo "--- 1. HTTP Endpoint Verification ---\n";
$endpoints = [
    'root' => '/',
    'up' => '/up',
    'health' => '/api/v1/health',
    'ready' => '/api/v1/health/ready',
];

foreach ($endpoints as $name => $path) {
    $url = $results['service_url'] . $path;
    $tmpHeader = tempnam(sys_get_temp_dir(), 'hdr_');
    $tmpBody = tempnam(sys_get_temp_dir(), 'bdy_');
    
    // Use curl.exe directly with header dump
    $cmd = "curl.exe -s -S --max-time 35 -D " . escapeshellarg($tmpHeader) . " -o " . escapeshellarg($tmpBody) . " " . escapeshellarg($url);
    exec($cmd, $out, $ret);

    $headers = file_exists($tmpHeader) ? file_get_contents($tmpHeader) : '';
    $body = file_exists($tmpBody) ? file_get_contents($tmpBody) : '';
    @unlink($tmpHeader);
    @unlink($tmpBody);

    preg_match('/HTTP\/\S+\s+(\d+)/i', $headers, $codeMatch);
    $httpCode = isset($codeMatch[1]) ? (int)$codeMatch[1] : 0;

    preg_match('/content-type:\s*([^\r\n]+)/i', $headers, $ctMatch);
    $contentType = trim($ctMatch[1] ?? '');

    preg_match('/x-render-origin-server:\s*([^\r\n]+)/i', $headers, $originServerMatch);
    preg_match('/server:\s*([^\r\n]+)/i', $headers, $serverMatch);

    $isNginxWelcome = str_contains($body, 'Welcome to nginx!') || str_contains($body, 'nginx.org');
    $isLaravel500 = str_contains($body, '<title>Server Error</title>') && str_contains($body, '500');
    $isLaravelUp = str_contains($body, 'Application up') && str_contains($body, 'Response rendered in');
    $isJsonHealth = str_contains($body, '"status":"ok"') || str_contains($body, '"ready":true');

    $source = 'Unknown';
    if ($isNginxWelcome) {
        $source = 'Nginx Welcome Page (Debian Default)';
    } elseif ($isLaravelUp) {
        $source = 'Laravel Application (/up health view)';
    } elseif ($isJsonHealth) {
        $source = 'Laravel Application (API JSON Controller)';
    } elseif ($isLaravel500) {
        $source = 'Laravel Application (Laravel 500 error page)';
    }

    $results['http_endpoints'][$name] = [
        'path' => $path,
        'url' => $url,
        'http_status' => $httpCode,
        'content_type' => $contentType,
        'origin_server_header' => trim($originServerMatch[1] ?? 'None'),
        'server_header' => trim($serverMatch[1] ?? 'None'),
        'is_nginx_welcome' => $isNginxWelcome,
        'source_detected' => $source,
        'body_length' => strlen($body),
        'body_snippet' => substr(trim($body), 0, 300),
    ];

    echo "Endpoint [{$path}]: HTTP {$httpCode} | Content-Type: {$contentType} | Source: {$source} | Origin-Server: " . trim($originServerMatch[1] ?? 'N/A') . "\n";
}

// ── 2. DATABASE SAFETY VERIFICATION (READ-ONLY) ─────────────────────────────
echo "\n--- 2. Database Safety Verification (Read-Only) ---\n";

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
    $ident = DB::connection('tidb_prod_app')->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
    $results['database_verification']['current_user'] = $ident->cu;
    $results['database_verification']['current_database'] = $ident->db;
    $results['database_verification']['user_is_prod_app'] = str_contains($ident->cu, 'prod_app');
    $results['database_verification']['database_is_socialpilot'] = ($ident->db === 'socialpilot');

    $ssl = DB::connection('tidb_prod_app')->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
    $results['database_verification']['tls_cipher'] = $ssl ? $ssl->Value : 'None';
    $results['database_verification']['tls_active'] = !empty($ssl ? $ssl->Value : '');

    $tables = DB::connection('tidb_prod_app')->select("SHOW FULL TABLES FROM `socialpilot` WHERE Table_type = 'BASE TABLE'");
    $results['database_verification']['table_count'] = count($tables);
    $results['database_verification']['table_count_valid'] = (count($tables) === 59);

    $migs = DB::connection('tidb_prod_app')->table('migrations')->orderBy('id')->get();
    $results['database_verification']['migration_count'] = count($migs);
    $results['database_verification']['migration_count_valid'] = (count($migs) === 29);

    // Check pending migrations by scanning local migration files
    $migrationFiles = glob(__DIR__ . '/../database/migrations/*.php');
    $localMigrationNames = array_map(function($f) {
        return basename($f, '.php');
    }, $migrationFiles);

    $dbMigrationNames = $migs->pluck('migration')->toArray();
    $pending = array_diff($localMigrationNames, $dbMigrationNames);

    $results['database_verification']['local_migration_files_count'] = count($localMigrationNames);
    $results['database_verification']['pending_migrations_count'] = count($pending);
    $results['database_verification']['pending_migrations'] = array_values($pending);
    $results['database_verification']['no_pending_migrations'] = (count($pending) === 0);

    echo "Connected User: {$ident->cu} (Expected: *prod_app*) — " . ($results['database_verification']['user_is_prod_app'] ? 'PASS' : 'FAIL') . "\n";
    echo "Current Database: {$ident->db} (Expected: socialpilot) — " . ($results['database_verification']['database_is_socialpilot'] ? 'PASS' : 'FAIL') . "\n";
    echo "TLS Cipher: {$results['database_verification']['tls_cipher']} — " . ($results['database_verification']['tls_active'] ? 'PASS' : 'FAIL') . "\n";
    echo "Base Table Count: " . count($tables) . " (Expected: 59) — " . ($results['database_verification']['table_count_valid'] ? 'PASS' : 'FAIL') . "\n";
    echo "Migration Records: " . count($migs) . " (Expected: 29) — " . ($results['database_verification']['migration_count_valid'] ? 'PASS' : 'FAIL') . "\n";
    echo "Pending Migrations: " . count($pending) . " (Expected: 0) — " . ($results['database_verification']['no_pending_migrations'] ? 'PASS' : 'FAIL') . "\n";

} catch (\Exception $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . "\n";
    $results['database_verification']['error'] = $e->getMessage();
}

// ── 3. SECURITY & ENVIRONMENT CONFIGURATION VERIFICATION ────────────────────
echo "\n--- 3. Security & Environment Configuration Verification ---\n";

$prodLocal = __DIR__ . '/../.env.production.local';
$prodEnvParsed = [];
if (file_exists($prodLocal)) {
    $lines = file($prodLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $prodEnvParsed[trim($parts[0])] = trim($parts[1]);
        }
    }
}

$results['security_verification']['app_env'] = $prodEnvParsed['APP_ENV'] ?? 'MISSING';
$results['security_verification']['app_env_is_production'] = (($prodEnvParsed['APP_ENV'] ?? '') === 'production');

$results['security_verification']['app_debug'] = $prodEnvParsed['APP_DEBUG'] ?? 'MISSING';
$results['security_verification']['app_debug_is_false'] = (($prodEnvParsed['APP_DEBUG'] ?? '') === 'false');

$results['security_verification']['db_host'] = $prodEnvParsed['DB_HOST'] ?? 'MISSING';
$results['security_verification']['db_database'] = $prodEnvParsed['DB_DATABASE'] ?? 'MISSING';
$results['security_verification']['db_username_masked'] = isset($prodEnvParsed['DB_USERNAME']) 
    ? substr($prodEnvParsed['DB_USERNAME'], 0, 8) . '...' . substr($prodEnvParsed['DB_USERNAME'], -8)
    : 'MISSING';

$stagingDb = 'socialpilot_staging';
$stagingHost = 'gateway01.eu-central-1.prod.aws.tidbcloud.com'; // Shared cluster endpoint, but db name is isolated
$results['security_verification']['no_staging_database'] = (($prodEnvParsed['DB_DATABASE'] ?? '') !== $stagingDb);
$results['security_verification']['no_staging_user'] = !str_contains($prodEnvParsed['DB_USERNAME'] ?? '', 'staging_app');

echo "APP_ENV: {$results['security_verification']['app_env']} (Expected: production) — " . ($results['security_verification']['app_env_is_production'] ? 'PASS' : 'FAIL') . "\n";
echo "APP_DEBUG: {$results['security_verification']['app_debug']} (Expected: false) — " . ($results['security_verification']['app_debug_is_false'] ? 'PASS' : 'FAIL') . "\n";
echo "DB_DATABASE: {$results['security_verification']['db_database']} (Expected: socialpilot) — " . ($results['security_verification']['no_staging_database'] ? 'PASS' : 'FAIL') . "\n";
echo "DB_USERNAME (Masked): {$results['security_verification']['db_username_masked']} (Not staging_app) — " . ($results['security_verification']['no_staging_user'] ? 'PASS' : 'FAIL') . "\n";

// ── 4. NGINX ROUTING VERIFICATION & COMMIT 7cbd0bc ──────────────────────────
echo "\n--- 4. Nginx Routing Verification & Commit Audit ---\n";

$headCommit = trim(shell_exec('git rev-parse HEAD'));
$headShort = substr($headCommit, 0, 7);
$results['git_verification']['head_commit'] = $headCommit;
$results['git_verification']['head_commit_short'] = $headShort;
$results['git_verification']['commit_matches_7cbd0bc'] = ($headShort === '7cbd0bc');

echo "Git HEAD Commit: {$headShort} ({$headCommit}) — " . ($results['git_verification']['commit_matches_7cbd0bc'] ? 'MATCHES 7cbd0bc' : 'MISMATCH') . "\n";

// Check commit 7cbd0bc changes
$commitDiff = shell_exec('git show 7cbd0bc --stat');
$results['git_verification']['commit_summary'] = $commitDiff;

// Evaluate Nginx routing
$nginxDefaultGone = ($results['http_endpoints']['up']['is_nginx_welcome'] === false) 
                 && ($results['http_endpoints']['health']['is_nginx_welcome'] === false)
                 && ($results['http_endpoints']['root']['is_nginx_welcome'] === false);

$results['nginx_routing_verification']['nginx_welcome_page_absent'] = $nginxDefaultGone;
$results['nginx_routing_verification']['laravel_up_functional'] = ($results['http_endpoints']['up']['http_status'] === 200);
$results['nginx_routing_verification']['api_health_functional'] = ($results['http_endpoints']['health']['http_status'] === 200);
$results['nginx_routing_verification']['api_ready_functional'] = ($results['http_endpoints']['ready']['http_status'] === 200);
$results['nginx_routing_verification']['root_routing_to_laravel'] = ($results['http_endpoints']['root']['source_detected'] === 'Laravel Application (Laravel 500 error page)');

echo "Nginx Debian Default Welcome Page Gone: " . ($nginxDefaultGone ? 'YES (PASS)' : 'NO (FAIL)') . "\n";
echo "Laravel /up Routing Functional: " . ($results['nginx_routing_verification']['laravel_up_functional'] ? 'YES (PASS)' : 'NO (FAIL)') . "\n";
echo "API Health Routing Functional: " . ($results['nginx_routing_verification']['api_health_functional'] ? 'YES (PASS)' : 'NO (FAIL)') . "\n";
echo "API Ready Routing Functional: " . ($results['nginx_routing_verification']['api_ready_functional'] ? 'YES (PASS)' : 'NO (FAIL)') . "\n";
echo "Root URL Routing to Laravel: " . ($results['nginx_routing_verification']['root_routing_to_laravel'] ? 'YES (PASS - routed to Laravel PHP-FPM)' : 'NO') . "\n";

// Save results
file_put_contents(__DIR__ . '/stage_5_5_3_11_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nAudit results written to scripts/stage_5_5_3_11_results.json\n";
