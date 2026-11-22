<?php
/**
 * STAGE 5.5.3.13 — Local APP_KEY Metadata Audit
 * STRICT READ-ONLY SCRIPT
 * Never prints APP_KEY or any secret value.
 */

$prodEnvFile = __DIR__ . '/../.env.production.local';
$results = [];

$results['file_exists'] = file_exists($prodEnvFile);
if (!$results['file_exists']) {
    die("ERROR: .env.production.local not found\n");
}

$lines = file($prodEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$appKeyFound = false;
$rawKey = '';

foreach ($lines as $line) {
    $line = trim($line);
    if (str_starts_with($line, '#')) continue;
    if (str_starts_with($line, 'APP_KEY=')) {
        $appKeyFound = true;
        $rawKey = trim(substr($line, strlen('APP_KEY=')));
        break;
    }
}

$results['app_key_present'] = $appKeyFound;
$results['app_key_non_empty'] = !empty($rawKey);
$results['starts_with_base64'] = str_starts_with($rawKey, 'base64:');

$b64Portion = $results['starts_with_base64'] ? substr($rawKey, 7) : $rawKey;
$results['raw_string_length'] = strlen($rawKey);
$results['base64_string_length'] = strlen($b64Portion);

$decoded = base64_decode($b64Portion, true);
$results['base64_valid'] = ($decoded !== false);
$results['decoded_byte_length'] = $decoded !== false ? strlen($decoded) : 0;
$results['is_32_bytes'] = ($results['decoded_byte_length'] === 32);
$results['compatible_with_aes_256_cbc'] = ($results['is_32_bytes'] && $results['starts_with_base64']);

$results['has_surrounding_quotes'] = (
    (str_starts_with($rawKey, '"') && str_ends_with($rawKey, '"')) ||
    (str_starts_with($rawKey, "'") && str_ends_with($rawKey, "'"))
);

// Git ignore check
$gitIgnoreCheck = shell_exec('git check-ignore -v .env.production.local');
$results['git_check_ignore'] = trim($gitIgnoreCheck ?? '');
$results['is_gitignored'] = !empty($results['git_check_ignore']);

// Check if .env.production.local is tracked in git
$gitTracked = shell_exec('git ls-files .env.production.local');
$results['is_untracked'] = empty(trim($gitTracked ?? ''));

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
