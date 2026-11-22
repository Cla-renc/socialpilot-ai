<?php
/**
 * STAGE 5.5.3.8 — Production Secret Generation & Secure Preparation Script
 * STRICT CONFIDENTIALITY:
 * - Generates independent production secrets using random_bytes().
 * - Writes to gitignored .env.production.local.
 * - NEVER prints or logs secret values.
 * - Validates formats using regex without revealing values.
 */

$targetFile = __DIR__ . '/../.env.production.local';
$stagingFile = __DIR__ . '/../.env.staging.local';

// Guard: Confirm target file does not already exist
if (file_exists($targetFile)) {
    die("ABORT: .env.production.local already exists. Will not overwrite existing file.\n");
}

// 1. Load staging secrets for collision check (ensure zero reuse)
$stagingSecrets = [];
if (file_exists($stagingFile)) {
    $lines = file($stagingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        $parts = explode('=', trim($line), 2);
        if (count($parts) === 2) {
            $stagingSecrets[$parts[0]] = $parts[1];
        }
    }
}

// 2. Generate independent production secrets
// APP_KEY: 32 random bytes, base64-encoded with base64: prefix (standard Laravel cipher AES-256-CBC)
$rawAppKey = random_bytes(32);
$appKey = 'base64:' . base64_encode($rawAppKey);

// Internal tokens: 32 random bytes -> 64-char hex strings
$aiToken   = bin2hex(random_bytes(32));
$trendToken = bin2hex(random_bytes(32));
$pubToken  = bin2hex(random_bytes(32));
$cronSecret = bin2hex(random_bytes(32));

// 3. Validation without revealing values
$validations = [];

// APP_KEY format check
$appKeyValid = (bool) preg_match('/^base64:[A-Za-z0-9+\/]{43}=$/', $appKey);
$appKeyNotReused = ($appKey !== ($stagingSecrets['APP_KEY'] ?? ''));
$validations['APP_KEY'] = [
    'present'          => true,
    'length_valid'     => (strlen($appKey) === 51),
    'format_valid'     => $appKeyValid,
    'not_reused'       => $appKeyNotReused,
];

// Token format & independence checks
$tokens = [
    'AI_ENGINE_INTERNAL_TOKEN'    => $aiToken,
    'TREND_ENGINE_INTERNAL_TOKEN' => $trendToken,
    'PUBLISHING_INTERNAL_TOKEN'   => $pubToken,
    'INTERNAL_CRON_SECRET'        => $cronSecret,
];

$allTokensDistinct = (count(array_unique(array_values($tokens))) === count($tokens));

foreach ($tokens as $name => $val) {
    $isHex64 = (bool) preg_match('/^[a-f0-9]{64}$/', $val);
    $notInStaging = ($val !== ($stagingSecrets[$name] ?? ''));
    $validations[$name] = [
        'present'      => true,
        'length_valid' => (strlen($val) === 64),
        'format_valid' => $isHex64,
        'not_reused'   => $notInStaging,
    ];
}

$validations['all_tokens_distinct'] = $allTokensDistinct;

// 4. Construct .env.production.local content
$header = "# SOCIALPILOT AI — PRODUCTION ENVIRONMENT SECRETS\n"
        . "# Generated: " . date('c') . "\n"
        . "# This file is strictly gitignored (.env.*). DO NOT commit to Git.\n"
        . "#\n"
        . "# Instructions:\n"
        . "# Copy each value into the Render Production Web Service Dashboard\n"
        . "# (Render Dashboard > Production Service > Environment > Environment Variables).\n"
        . "#\n"
        . "# ==============================================================================\n"
        . "# GROUP 1: INDEPENDENT PRODUCTION SECRETS (GENERATED STAGE 5.5.3.8)\n"
        . "# ==============================================================================\n";

$body = "APP_KEY={$appKey}\n\n"
      . "AI_ENGINE_INTERNAL_TOKEN={$aiToken}\n"
      . "TREND_ENGINE_INTERNAL_TOKEN={$trendToken}\n"
      . "PUBLISHING_INTERNAL_TOKEN={$pubToken}\n"
      . "INTERNAL_CRON_SECRET={$cronSecret}\n\n"
      . "# ==============================================================================\n"
      . "# GROUP 2: VERIFIED NON-SECRET PRODUCTION CONFIGURATION\n"
      . "# ==============================================================================\n"
      . "APP_NAME=\"SocialPilot AI\"\n"
      . "APP_ENV=production\n"
      . "APP_DEBUG=false\n"
      . "APP_LOCALE=en\n"
      . "APP_FALLBACK_LOCALE=en\n\n"
      . "LOG_CHANNEL=stderr\n"
      . "LOG_LEVEL=info\n"
      . "LOG_DEPRECATIONS_CHANNEL=null\n\n"
      . "# ==============================================================================\n"
      . "# GROUP 3: RUNTIME & CACHE CONFIGURATION\n"
      . "# ==============================================================================\n"
      . "SESSION_DRIVER=database\n"
      . "SESSION_LIFETIME=120\n"
      . "SESSION_ENCRYPT=false\n"
      . "CACHE_STORE=database\n"
      . "QUEUE_CONNECTION=database\n\n"
      . "# ==============================================================================\n"
      . "# NOTE ON SEPARATELY MANAGED CREDENTIALS:\n"
      . "# Database credentials (AZDARrqgy4MBFGE.prod_app) and Supabase S3 credentials\n"
      . "# are managed as existing verified credentials and should be transferred to Render\n"
      . "# from their respective verified sources during Stage 5.5.3.9.\n";

// Write file
$bytesWritten = file_put_contents($targetFile, $header . $body);

$allPassed = (
    $bytesWritten > 0 &&
    $validations['APP_KEY']['format_valid'] &&
    $validations['APP_KEY']['not_reused'] &&
    $validations['all_tokens_distinct'] &&
    $validations['AI_ENGINE_INTERNAL_TOKEN']['format_valid'] &&
    $validations['AI_ENGINE_INTERNAL_TOKEN']['not_reused'] &&
    $validations['TREND_ENGINE_INTERNAL_TOKEN']['format_valid'] &&
    $validations['TREND_ENGINE_INTERNAL_TOKEN']['not_reused'] &&
    $validations['PUBLISHING_INTERNAL_TOKEN']['format_valid'] &&
    $validations['PUBLISHING_INTERNAL_TOKEN']['not_reused'] &&
    $validations['INTERNAL_CRON_SECRET']['format_valid'] &&
    $validations['INTERNAL_CRON_SECRET']['not_reused']
);

$results = [
    'timestamp'      => date('c'),
    'stage'          => '5.5.3.8 — Production Secret Generation & Secure Preparation',
    'file_created'   => '.env.production.local',
    'bytes_written'  => $bytesWritten,
    'validations'    => $validations,
    'final_decision' => $allPassed ? 'A' : 'B',
];

file_put_contents(__DIR__ . '/stage_5_5_3_8_results.json', json_encode($results, JSON_PRETTY_PRINT));

echo "Stage 5.5.3.8 generation completed.\n";
echo "File created: .env.production.local ({$bytesWritten} bytes)\n";
echo "Validation result: " . ($allPassed ? "ALL SECRETS VALIDATED & INDEPENDENT (Decision A)" : "VALIDATION FAILED (Decision B)") . "\n";
echo "Confidentiality: ZERO secret values printed or exposed.\n";
