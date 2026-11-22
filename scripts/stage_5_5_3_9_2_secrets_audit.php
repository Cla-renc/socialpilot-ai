<?php
/**
 * Verification of presence of required configuration keys across local files
 * STRICT: Checks presence and non-emptiness only, NEVER outputs values.
 */

$stagingLocal = __DIR__ . '/../.env.staging.local';
$prodLocal    = __DIR__ . '/../.env.production.local';
$dotEnv       = __DIR__ . '/../.env';

function parseKeys($file) {
    $keys = [];
    if (!file_exists($file)) return $keys;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2 && !empty(trim($parts[1]))) {
            $keys[trim($parts[0])] = true;
        }
    }
    return $keys;
}

$stagingKeys = parseKeys($stagingLocal);
$prodKeys    = parseKeys($prodLocal);
$envKeys     = parseKeys($dotEnv);

$checklist = [
    // Task 3: Database
    'DB_PASSWORD (from .env.staging.local)' => isset($stagingKeys['TIDB_PROD_APP_PASSWORD']),
    'DB_USERNAME (AZDARrqgy4MBFGE.prod_app)' => isset($stagingKeys['TIDB_PROD_APP_USERNAME']),
    
    // Task 4: Application Secrets (from .env.production.local)
    'APP_KEY' => isset($prodKeys['APP_KEY']),
    'AI_ENGINE_INTERNAL_TOKEN' => isset($prodKeys['AI_ENGINE_INTERNAL_TOKEN']),
    'TREND_ENGINE_INTERNAL_TOKEN' => isset($prodKeys['TREND_ENGINE_INTERNAL_TOKEN']),
    'PUBLISHING_INTERNAL_TOKEN' => isset($prodKeys['PUBLISHING_INTERNAL_TOKEN']),
    'INTERNAL_CRON_SECRET' => isset($prodKeys['INTERNAL_CRON_SECRET']),

    // Task 5: Supabase Storage (from .env)
    'AWS_ACCESS_KEY_ID' => isset($envKeys['AWS_ACCESS_KEY_ID']),
    'AWS_SECRET_ACCESS_KEY' => isset($envKeys['AWS_SECRET_ACCESS_KEY']),
];

echo "=== STAGE 5.5.3.9.2 SECRETS AUDIT ===\n";
$allPresent = true;
foreach ($checklist as $item => $present) {
    echo "  {$item}: " . ($present ? "PRESENT" : "MISSING") . "\n";
    if (!$present) $allPresent = false;
}

echo "\nAll required production secrets present: " . ($allPresent ? "YES (Decision A ready)" : "NO") . "\n";
