<?php
/**
 * STAGE 5.5.4.16 — Social Provider Post-Deployment Configuration Verification
 * READ-ONLY: Bootstraps Laravel, reads config() — zero DB operations.
 * Reports provider configured status, redirect URIs, and scopes.
 * No credential values are printed.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$providerKeys = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
$results = [];

foreach ($providerKeys as $key) {
    $cfg = config('services.social.' . $key, []);
    $clientIdFilled  = !empty($cfg['client_id'] ?? null);
    $clientSecretFilled = !empty($cfg['client_secret'] ?? null);
    $redirectFilled  = !empty($cfg['redirect'] ?? null);
    $configured      = $clientIdFilled && $clientSecretFilled && $redirectFilled;

    $results[$key] = [
        'provider'        => $key,
        'configured'      => $configured,
        'client_id_set'   => $clientIdFilled,
        'client_secret_set' => $clientSecretFilled,
        'redirect_uri'    => $cfg['redirect'] ?? null,   // redirect URL is not sensitive
        'scopes'          => $cfg['scopes'] ?? [],
        'graph_version'   => $cfg['graph_version'] ?? null,
        'status'          => $configured ? 'CONFIGURED — ready to authorize' : 'NOT CONFIGURED — credentials missing',
    ];
}

// Also call SocialProviderCatalog to replicate exact UI/API behaviour
$catalog = app(App\Services\SocialProviderCatalog::class);
$catalogStatuses = $catalog->statuses();

echo json_encode([
    'timestamp'       => date('c'),
    'env_config'      => $results,
    'catalog_statuses' => $catalogStatuses,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
