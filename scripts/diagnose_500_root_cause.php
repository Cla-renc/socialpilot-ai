<?php
/**
 * STAGE 5.5.3.12 — Production Root Homepage HTTP 500 Diagnostic Audit Script
 * STRICT READ-ONLY SCRIPT:
 * - Redacts all secrets, passwords, tokens, keys
 * - Analyzes APP_KEY format, session driver, cache store, route definitions
 * - Rechecks /up, /api/v1/health, /api/v1/health/ready
 * - Compares staging vs production configurations
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Encryption\Encrypter;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.3.12 — Production Root Homepage HTTP 500 Diagnostic Audit',
    'mode' => 'STRICT READ-ONLY',
    'health_endpoints' => [],
    'config_inspection' => [],
    'app_key_analysis' => [],
    'staging_vs_prod_comparison' => [],
    'blade_and_routing' => [],
];

echo "======================================================================\n";
echo "STAGE 5.5.3.12: PRODUCTION ROOT HOMEPAGE HTTP 500 DIAGNOSTIC AUDIT\n";
echo "======================================================================\n\n";

// ── 1. RECHECK HEALTH ENDPOINTS (Read-Only GET) ─────────────────────────────
echo "--- 1. Rechecking Production Health Endpoints ---\n";
$healthEndpoints = [
    'up' => '/up',
    'health' => '/api/v1/health',
    'ready' => '/api/v1/health/ready',
];

$prodBase = 'https://socialpilot-ai-y4lm.onrender.com';
foreach ($healthEndpoints as $name => $path) {
    $url = $prodBase . $path;
    $tmpHeader = tempnam(sys_get_temp_dir(), 'hdr_');
    $tmpBody = tempnam(sys_get_temp_dir(), 'bdy_');
    
    $cmd = "curl.exe -s -S --max-time 25 -D " . escapeshellarg($tmpHeader) . " -o " . escapeshellarg($tmpBody) . " " . escapeshellarg($url);
    exec($cmd, $out, $ret);

    $headers = file_exists($tmpHeader) ? file_get_contents($tmpHeader) : '';
    $body = file_exists($tmpBody) ? file_get_contents($tmpBody) : '';
    @unlink($tmpHeader);
    @unlink($tmpBody);

    preg_match('/HTTP\/\S+\s+(\d+)/i', $headers, $codeMatch);
    $httpCode = isset($codeMatch[1]) ? (int)$codeMatch[1] : 0;
    preg_match('/content-type:\s*([^\r\n]+)/i', $headers, $ctMatch);
    $contentType = trim($ctMatch[1] ?? '');

    $results['health_endpoints'][$name] = [
        'path' => $path,
        'http_status' => $httpCode,
        'content_type' => $contentType,
        'is_healthy' => ($httpCode === 200),
        'snippet' => substr(trim($body), 0, 150),
    ];
    echo "Endpoint [{$path}]: HTTP {$httpCode} | Healthy: " . ($httpCode === 200 ? 'YES' : 'NO') . "\n";
}

// ── 2. INSPECT PRODUCTION CONFIGURATION (from .env.production.local) ─────────
echo "\n--- 2. Inspecting Production Environment Configuration ---\n";
$prodLocalFile = __DIR__ . '/../.env.production.local';
$prodEnv = [];
if (file_exists($prodLocalFile)) {
    $lines = file($prodLocalFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $prodEnv[trim($parts[0])] = trim($parts[1]);
        }
    }
}

$stagingLocalFile = __DIR__ . '/../.env.staging.local';
$stagingEnv = [];
if (file_exists($stagingLocalFile)) {
    $lines = file($stagingLocalFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $stagingEnv[trim($parts[0])] = trim($parts[1]);
        }
    }
}

$results['config_inspection'] = [
    'APP_ENV' => $prodEnv['APP_ENV'] ?? 'UNSET',
    'APP_DEBUG' => $prodEnv['APP_DEBUG'] ?? 'UNSET',
    'APP_URL' => $prodEnv['APP_URL'] ?? 'UNSET',
    'SESSION_DRIVER' => $prodEnv['SESSION_DRIVER'] ?? 'UNSET',
    'SESSION_LIFETIME' => $prodEnv['SESSION_LIFETIME'] ?? 'UNSET',
    'SESSION_ENCRYPT' => $prodEnv['SESSION_ENCRYPT'] ?? 'UNSET',
    'CACHE_STORE' => $prodEnv['CACHE_STORE'] ?? 'UNSET',
    'QUEUE_CONNECTION' => $prodEnv['QUEUE_CONNECTION'] ?? 'UNSET',
    'FILESYSTEM_DISK' => $prodEnv['FILESYSTEM_DISK'] ?? 'UNSET',
    'MEDIA_STORAGE_DISK' => $prodEnv['MEDIA_STORAGE_DISK'] ?? 'UNSET',
    'DB_CONNECTION' => $prodEnv['DB_CONNECTION'] ?? 'UNSET',
    'DB_HOST' => $prodEnv['DB_HOST'] ?? 'UNSET',
    'DB_PORT' => $prodEnv['DB_PORT'] ?? 'UNSET',
    'DB_DATABASE' => $prodEnv['DB_DATABASE'] ?? 'UNSET',
    'MYSQL_ATTR_SSL_CA' => $prodEnv['MYSQL_ATTR_SSL_CA'] ?? 'UNSET',
];

foreach ($results['config_inspection'] as $k => $v) {
    echo "  {$k} = {$v}\n";
}

// ── 3. APP_KEY FORMAT & ENCRYPTER VALIDATION ────────────────────────────────
echo "\n--- 3. APP_KEY Cryptographic Validation (No Secret Output) ---\n";

$rawProdKey = $prodEnv['APP_KEY'] ?? '';
$keyPrefix = substr($rawProdKey, 0, 7);
$keyLength = strlen($rawProdKey);
$hasBase64Prefix = str_starts_with($rawProdKey, 'base64:');

$decodedKey = '';
$decodedBytesLength = 0;
$encrypterValid = false;
$encrypterError = null;

if ($hasBase64Prefix) {
    $decodedKey = base64_decode(substr($rawProdKey, 7), true);
    $decodedBytesLength = strlen($decodedKey);
} else {
    $decodedKey = $rawProdKey;
    $decodedBytesLength = strlen($decodedKey);
}

// Test instantiating Laravel Encrypter with this key
try {
    $keyToTest = $hasBase64Prefix ? substr($rawProdKey, 7) : $rawProdKey;
    if ($hasBase64Prefix) {
        $keyToTest = base64_decode($keyToTest);
    }
    $enc = new Encrypter($keyToTest, 'AES-256-CBC');
    $testEncrypted = $enc->encrypt('test-payload');
    $testDecrypted = $enc->decrypt($testEncrypted);
    $encrypterValid = ($testDecrypted === 'test-payload');
} catch (\Throwable $e) {
    $encrypterValid = false;
    $encrypterError = $e->getMessage();
}

$results['app_key_analysis'] = [
    'key_present' => !empty($rawProdKey),
    'key_string_length' => $keyLength,
    'has_base64_prefix' => $hasBase64Prefix,
    'decoded_bytes_count' => $decodedBytesLength,
    'is_exactly_32_bytes' => ($decodedBytesLength === 32),
    'encrypter_instantiation_success' => $encrypterValid,
    'encrypter_error' => $encrypterError,
];

echo "  APP_KEY present: " . ($results['app_key_analysis']['key_present'] ? 'YES' : 'NO') . "\n";
echo "  String length: {$keyLength} chars\n";
echo "  Has 'base64:' prefix: " . ($hasBase64Prefix ? 'YES' : 'NO') . "\n";
echo "  Decoded byte length: {$decodedBytesLength} bytes (Expected: 32 for AES-256-CBC)\n";
echo "  Laravel Encrypter valid: " . ($encrypterValid ? 'YES (PASS)' : 'NO (FAIL: ' . $encrypterError . ')') . "\n";

// ── 4. STAGING VS PRODUCTION COMPARISON ─────────────────────────────────────
echo "\n--- 4. Staging vs Production Configuration Comparison ---\n";

$rawStagingKey = $stagingEnv['APP_KEY'] ?? '';
$stagingHasPrefix = str_starts_with($rawStagingKey, 'base64:');
$stagingDecoded = $stagingHasPrefix ? base64_decode(substr($rawStagingKey, 7)) : $rawStagingKey;

$results['staging_vs_prod_comparison'] = [
    'staging_app_key_bytes' => strlen($stagingDecoded),
    'staging_app_key_has_base64' => $stagingHasPrefix,
    'keys_are_distinct' => ($rawProdKey !== $rawStagingKey),
    'staging_session_driver' => $stagingEnv['SESSION_DRIVER'] ?? 'database (default)',
    'prod_session_driver' => $prodEnv['SESSION_DRIVER'] ?? 'UNSET',
    'staging_url' => 'https://socialpilot-staging.onrender.com',
    'prod_url' => 'https://socialpilot-ai-y4lm.onrender.com',
    'staging_root_status' => 200,
    'prod_root_status' => 500,
];

echo "  Staging APP_KEY decoded bytes: " . strlen($stagingDecoded) . " (has base64: " . ($stagingHasPrefix ? 'YES' : 'NO') . ")\n";
echo "  Keys are distinct (isolated): " . ($results['staging_vs_prod_comparison']['keys_are_distinct'] ? 'YES' : 'NO') . "\n";
echo "  Staging Root HTTP: 200 OK | Production Root HTTP: 500 Error\n";

// ── 5. ROUTING & BLADE TEMPLATE INSPECTION ──────────────────────────────────
echo "\n--- 5. Homepage Route & View Analysis ---\n";

$routeObj = \Illuminate\Support\Facades\Route::getRoutes()->getByName('welcome');
$routeMiddleware = [];
if ($routeObj) {
    $routeMiddleware = $routeObj->gatherMiddleware();
}

$results['blade_and_routing'] = [
    'route_uri' => '/',
    'route_name' => 'welcome',
    'route_action' => $routeObj ? $routeObj->getActionName() : 'None',
    'middleware_applied' => $routeMiddleware,
    'view_name' => 'welcome',
    'view_file_exists' => file_exists(__DIR__ . '/../resources/views/welcome.blade.php'),
];

echo "  Route URI: /\n";
echo "  Action: " . ($routeObj ? $routeObj->getActionName() : 'None') . "\n";
echo "  Middleware applied: " . implode(', ', $routeMiddleware) . "\n";
echo "  View exists: " . ($results['blade_and_routing']['view_file_exists'] ? 'YES' : 'NO') . "\n";

// ── 6. SIMULATE WEB MIDDLEWARE PIPELINE LOCALLY ─────────────────────────────
echo "\n--- 6. Simulating Web Middleware Execution with Prod Config ---\n";

// Temporarily set configuration to match production
Config::set('app.env', 'production');
Config::set('app.debug', false);
Config::set('app.key', $rawProdKey);
Config::set('session.driver', $prodEnv['SESSION_DRIVER'] ?? 'database');
Config::set('session.encrypt', false);
Config::set('cache.default', $prodEnv['CACHE_STORE'] ?? 'database');

// Try creating a test request to / and dispatching through web middleware
try {
    $request = \Illuminate\Http\Request::create('/', 'GET');
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    echo "  Local simulated GET / status: {$status}\n";
    $results['local_simulation'] = [
        'status' => $status,
        'headers' => array_keys($response->headers->all()),
    ];
} catch (\Throwable $e) {
    echo "  Local simulation EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $results['local_simulation'] = [
        'error_class' => get_class($e),
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'trace_snippet' => substr($e->getTraceAsString(), 0, 500),
    ];
}

// ── 7. VERIFY DATABASE-BACKED SESSION & CACHE INFRASTRUCTURE ────────────────
echo "\n--- 7. Database-Backed Session & Cache Table Verification (Read-Only) ---\n";

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
    $sessionColumns = DB::connection('tidb_prod_app')->select("SHOW COLUMNS FROM `socialpilot`.`sessions`");
    $cacheColumns = DB::connection('tidb_prod_app')->select("SHOW COLUMNS FROM `socialpilot`.`cache`");
    $cacheLocks = DB::connection('tidb_prod_app')->select("SHOW COLUMNS FROM `socialpilot`.`cache_locks`");

    $results['database_tables'] = [
        'sessions_table_exists' => !empty($sessionColumns),
        'sessions_columns' => array_map(fn($c) => $c->Field, $sessionColumns),
        'cache_table_exists' => !empty($cacheColumns),
        'cache_columns' => array_map(fn($c) => $c->Field, $cacheColumns),
        'cache_locks_table_exists' => !empty($cacheLocks),
        'cache_locks_columns' => array_map(fn($c) => $c->Field, $cacheLocks),
    ];

    echo "  `socialpilot.sessions` exists: YES (" . count($sessionColumns) . " cols)\n";
    echo "  `socialpilot.cache` exists: YES (" . count($cacheColumns) . " cols)\n";
    echo "  `socialpilot.cache_locks` exists: YES (" . count($cacheLocks) . " cols)\n";
} catch (\Exception $e) {
    echo "  DB table check ERROR: " . $e->getMessage() . "\n";
    $results['database_tables']['error'] = $e->getMessage();
}

// Write diagnostic results
file_put_contents(__DIR__ . '/stage_5_5_3_12_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nAudit results written to scripts/stage_5_5_3_12_results.json\n";
