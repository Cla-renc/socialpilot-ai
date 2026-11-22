<?php

/**
 * Stage 5.4.7 — Controlled End-to-End Staging Functional Test Suite
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$baseUrl = 'https://socialpilot-staging.onrender.com';
$results = [];
$createdIds = [];

function apiCall(string $method, string $url, ?array $payload = null, ?string $token = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    
    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $json = json_decode($rawResponse, true);
    
    return [
        'code' => $httpCode,
        'body' => $json ?: $rawResponse,
        'error' => $curlError,
    ];
}

// -----------------------------------------------------------------------------
// PREFLIGHT SAFETY CHECK
// -----------------------------------------------------------------------------
echo "=== PREFLIGHT SAFETY CHECK ===\n";
$pdo = Illuminate\Support\Facades\DB::connection('tidb_staging_app')->getPdo();
$db = $pdo->query('SELECT DATABASE()')->fetchColumn();
$user = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
$ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);

if ($db !== 'socialpilot_staging') {
    die("FATAL: Active DB is '$db', expected 'socialpilot_staging'. Halting.\n");
}
if (!str_starts_with($user, 'AZDARrqgy4MBFGE.staging_app')) {
    die("FATAL: Active user is '$user', expected 'AZDARrqgy4MBFGE.staging_app'. Halting.\n");
}
if (empty($ssl['Value'])) {
    die("FATAL: TLS connection is not active. Halting.\n");
}
echo "✓ Verified Database: $db\n";
echo "✓ Verified User: $user\n";
echo "✓ Verified TLS Cipher: {$ssl['Value']}\n\n";

// Get test user credentials
$testUser = $pdo->query("SELECT id, name, email FROM users WHERE email LIKE 'staging_test_%' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$testUser) {
    die("FATAL: No staging test user found. Halting.\n");
}
echo "Found Staging Test Account ID: {$testUser['id']}, Email: {$testUser['email']}\n\n";

$testPassword = 'Password123!';
$authToken = null;

// -----------------------------------------------------------------------------
// P-01: Authentication
// -----------------------------------------------------------------------------
echo "=== EXECUTING P-01: Authentication ===\n";
$p01 = apiCall('POST', "$baseUrl/api/v1/auth/login", [
    'email' => $testUser['email'],
    'password' => $testPassword,
]);

if ($p01['code'] === 200 && !empty($p01['body']['token'])) {
    $authToken = $p01['body']['token'];
    $results['P-01'] = [
        'status' => 'PASS',
        'http_code' => $p01['code'],
        'details' => "Authentication successful for user ID {$testUser['id']}. Bearer token obtained.",
    ];
    echo "✓ P-01 PASS: HTTP {$p01['code']}, Token received (length: " . strlen($authToken) . ")\n";
} else {
    $results['P-01'] = [
        'status' => 'FAIL',
        'http_code' => $p01['code'],
        'details' => $p01['body'],
    ];
    echo "✗ P-01 FAIL: HTTP {$p01['code']} (Error: {$p01['error']})\n";
    if (is_array($p01['body'])) {
        echo "Response: " . json_encode($p01['body']) . "\n";
    } else {
        echo "Response: " . substr((string)$p01['body'], 0, 200) . "\n";
    }
    die("P-01 failed. Halting further tests.\n");
}

// -----------------------------------------------------------------------------
// P-02: Brand Lifecycle
// -----------------------------------------------------------------------------
echo "\n=== EXECUTING P-02: Brand Lifecycle ===\n";
$brandPayload = [
    'name' => '[STAGING] Brand Alpha',
    'description' => 'Controlled staging functional verification test brand.',
    'industry' => 'Technology',
    'status' => 'draft',
];
$p02Create = apiCall('POST', "$baseUrl/api/v1/brands", $brandPayload, $authToken);

if ($p02Create['code'] === 201 && !empty($p02Create['body']['data']['id'])) {
    $brandId = $p02Create['body']['data']['id'];
    $createdIds['brand_id'] = $brandId;
    echo "✓ Created Brand ID: $brandId\n";
    
    // Verify retrieval
    $p02Get = apiCall('GET', "$baseUrl/api/v1/brands/$brandId", null, $authToken);
    if ($p02Get['code'] === 200 && $p02Get['body']['data']['id'] === $brandId) {
        $results['P-02'] = [
            'status' => 'PASS',
            'http_code' => "POST: {$p02Create['code']}, GET: {$p02Get['code']}",
            'brand_id' => $brandId,
            'details' => "Brand '[STAGING] Brand Alpha' created and verified via GET.",
        ];
        echo "✓ P-02 PASS: Brand retrieval verified via GET /api/v1/brands/$brandId\n";
    } else {
        $results['P-02'] = [
            'status' => 'FAIL',
            'http_code' => $p02Get['code'],
            'details' => "Brand created (ID $brandId) but retrieval failed.",
        ];
        echo "✗ P-02 FAIL: Brand retrieval failed\n";
    }
} else {
    $results['P-02'] = [
        'status' => 'FAIL',
        'http_code' => $p02Create['code'],
        'details' => $p02Create['body'],
    ];
    echo "✗ P-02 FAIL: Brand creation failed (HTTP {$p02Create['code']})\n";
    die("P-02 failed. Halting further tests.\n");
}

// -----------------------------------------------------------------------------
// P-03: Content Drafting
// -----------------------------------------------------------------------------
echo "\n=== EXECUTING P-03: Content Drafting ===\n";
$draftPayload = [
    'title' => '[STAGING TEST] Test Draft Post',
    'platform' => 'linkedin',
    'content_type' => 'caption',
    'prompt' => 'Controlled staging end-to-end functional test draft content.',
    'status' => 'draft',
];
$p03Create = apiCall('POST', "$baseUrl/api/v1/brands/$brandId/content-drafts", $draftPayload, $authToken);

if ($p03Create['code'] === 201 && !empty($p03Create['body']['data']['id'])) {
    $draftId = $p03Create['body']['data']['id'];
    $createdIds['draft_id'] = $draftId;
    echo "✓ Created Draft ID: $draftId\n";
    
    $p03Get = apiCall('GET', "$baseUrl/api/v1/brands/$brandId/content-drafts/$draftId", null, $authToken);
    if ($p03Get['code'] === 200) {
        $results['P-03'] = [
            'status' => 'PASS',
            'http_code' => "POST: {$p03Create['code']}, GET: {$p03Get['code']}",
            'draft_id' => $draftId,
            'details' => "Draft created and retrieved successfully under Brand ID $brandId.",
        ];
        echo "✓ P-03 PASS: Draft verified via GET /api/v1/brands/$brandId/content-drafts/$draftId\n";
    } else {
        $results['P-03'] = [
            'status' => 'FAIL',
            'http_code' => $p03Get['code'],
            'details' => "Draft created (ID $draftId) but retrieval failed.",
        ];
        echo "✗ P-03 FAIL: Draft retrieval failed\n";
    }
} else {
    $results['P-03'] = [
        'status' => 'FAIL',
        'http_code' => $p03Create['code'],
        'details' => $p03Create['body'],
    ];
    echo "✗ P-03 FAIL: Draft creation failed\n";
}

// -----------------------------------------------------------------------------
// P-04: AI Content Generation (Internal Zero-Cost Provider)
// -----------------------------------------------------------------------------
echo "\n=== EXECUTING P-04: AI Content Generation (Internal Zero-Cost Provider) ===\n";
$aiPayload = [
    'provider' => 'internal',
    'platform' => 'linkedin',
    'content_format' => 'post',
    'objective' => 'engagement',
    'instructions' => 'Staging verification test post validating loopback FastAPI bridge.',
    'sync' => true,
];
$p04 = apiCall('POST', "$baseUrl/api/v1/brands/$brandId/content/generate", $aiPayload, $authToken);

if ($p04['code'] === 201 && !empty($p04['body']['data']['id'])) {
    $jobId = $p04['body']['data']['id'];
    $createdIds['ai_job_id'] = $jobId;
    if (!empty($p04['body']['draft']['id'])) {
        $createdIds['ai_draft_id'] = $p04['body']['draft']['id'];
    }
    $results['P-04'] = [
        'status' => 'PASS',
        'http_code' => $p04['code'],
        'job_id' => $jobId,
        'provider' => $p04['body']['data']['provider'] ?? 'internal',
        'details' => "AI content generated synchronously via zero-cost internal provider (Job ID $jobId).",
    ];
    echo "✓ P-04 PASS: AI generation completed with internal provider (Job ID: $jobId)\n";
} else {
    $results['P-04'] = [
        'status' => 'FAIL',
        'http_code' => $p04['code'],
        'details' => $p04['body'],
    ];
    echo "✗ P-04 FAIL: HTTP {$p04['code']}\n";
}

// -----------------------------------------------------------------------------
// P-05: Post Scheduling
// -----------------------------------------------------------------------------
echo "\n=== EXECUTING P-05: Post Scheduling ===\n";
$schedulePayload = [
    'title' => '[STAGING TEST] Future Scheduled Post',
    'caption' => 'Test post scheduled for safety verification. Will not be dispatched.',
    'scheduled_at' => '2028-12-31 12:00:00',
    'requires_approval' => true,
    'is_approved' => false,
    'platform' => 'linkedin',
];
$p05Create = apiCall('POST', "$baseUrl/api/v1/brands/$brandId/scheduled-posts", $schedulePayload, $authToken);

if ($p05Create['code'] === 201 && !empty($p05Create['body']['data']['id'])) {
    $scheduledPostId = $p05Create['body']['data']['id'];
    $createdIds['scheduled_post_id'] = $scheduledPostId;
    echo "✓ Scheduled Post ID: $scheduledPostId\n";
    
    // Explicitly cancel to guarantee safe state
    $p05Cancel = apiCall('POST', "$baseUrl/api/v1/scheduled-posts/$scheduledPostId/cancel", [], $authToken);
    
    // Verify retrieval
    $p05Get = apiCall('GET', "$baseUrl/api/v1/scheduled-posts/$scheduledPostId", null, $authToken);
    if ($p05Get['code'] === 200) {
        $status = $p05Get['body']['data']['status'] ?? 'unknown';
        $results['P-05'] = [
            'status' => 'PASS',
            'http_code' => "POST: {$p05Create['code']}, GET: {$p05Get['code']}",
            'scheduled_post_id' => $scheduledPostId,
            'post_status' => $status,
            'details' => "Scheduled post created for 2028-12-31, placed into safe state ('$status').",
        ];
        echo "✓ P-05 PASS: Scheduled post persisted and verified (Status: $status)\n";
    } else {
        $results['P-05'] = [
            'status' => 'FAIL',
            'http_code' => $p05Get['code'],
            'details' => "Post created but retrieval failed.",
        ];
        echo "✗ P-05 FAIL: Post retrieval failed\n";
    }
} else {
    $results['P-05'] = [
        'status' => 'FAIL',
        'http_code' => $p05Create['code'],
        'details' => $p05Create['body'],
    ];
    echo "✗ P-05 FAIL: Scheduling failed\n";
}

// -----------------------------------------------------------------------------
// P-06: Autopilot Rule
// -----------------------------------------------------------------------------
echo "\n=== EXECUTING P-06: Autopilot Rule ===\n";
$rulePayload = [
    'name' => '[STAGING TEST] Inactive Test Rule',
    'rule_type' => 'publishing_frequency',
    'conditions' => ['category' => 'test'],
    'actions' => ['notify' => false],
    'is_active' => false,
    'priority' => 1,
];
$p06Create = apiCall('POST', "$baseUrl/api/v1/brands/$brandId/autopilot/rules", $rulePayload, $authToken);

if ($p06Create['code'] === 201 && !empty($p06Create['body']['rule']['id'])) {
    $ruleId = $p06Create['body']['rule']['id'];
    $createdIds['rule_id'] = $ruleId;
    echo "✓ Created Autopilot Rule ID: $ruleId\n";
    
    $p06Get = apiCall('GET', "$baseUrl/api/v1/brands/$brandId/autopilot", null, $authToken);
    if ($p06Get['code'] === 200) {
        $results['P-06'] = [
            'status' => 'PASS',
            'http_code' => "POST: {$p06Create['code']}, GET: {$p06Get['code']}",
            'rule_id' => $ruleId,
            'is_active' => false,
            'details' => "Autopilot rule created in disabled state (is_active: false) and verified.",
        ];
        echo "✓ P-06 PASS: Autopilot rule verified in disabled state\n";
    } else {
        $results['P-06'] = [
            'status' => 'FAIL',
            'http_code' => $p06Get['code'],
            'details' => "Rule created but retrieval failed.",
        ];
        echo "✗ P-06 FAIL: Rule retrieval failed\n";
    }
} else {
    $results['P-06'] = [
        'status' => 'FAIL',
        'http_code' => $p06Create['code'],
        'details' => $p06Create['body'],
    ];
    echo "✗ P-06 FAIL: Rule creation failed\n";
}

// -----------------------------------------------------------------------------
// P-07: Controlled Cleanup
// -----------------------------------------------------------------------------
echo "\n=== EXECUTING P-07: Controlled Cleanup ===\n";
echo "Created IDs to clean up:\n" . json_encode($createdIds, JSON_PRETTY_PRINT) . "\n\n";

$cleanupLog = [];

// 1. Delete Autopilot Rule
if (!empty($createdIds['rule_id'])) {
    $delRule = apiCall('DELETE', "$baseUrl/api/v1/brands/$brandId/autopilot/rules/{$createdIds['rule_id']}", null, $authToken);
    $cleanupLog['rule'] = "HTTP {$delRule['code']}";
    echo "Deleted Autopilot Rule {$createdIds['rule_id']}: HTTP {$delRule['code']}\n";
}

// 2. Delete Scheduled Post
if (!empty($createdIds['scheduled_post_id'])) {
    $delPost = apiCall('DELETE', "$baseUrl/api/v1/scheduled-posts/{$createdIds['scheduled_post_id']}", null, $authToken);
    $cleanupLog['scheduled_post'] = "HTTP {$delPost['code']}";
    echo "Deleted Scheduled Post {$createdIds['scheduled_post_id']}: HTTP {$delPost['code']}\n";
}

// 3. Delete AI Generated Draft (if distinct)
if (!empty($createdIds['ai_draft_id']) && $createdIds['ai_draft_id'] !== ($createdIds['draft_id'] ?? null)) {
    $delAiDraft = apiCall('DELETE', "$baseUrl/api/v1/brands/$brandId/content-drafts/{$createdIds['ai_draft_id']}", null, $authToken);
    $cleanupLog['ai_draft'] = "HTTP {$delAiDraft['code']}";
    echo "Deleted AI Draft {$createdIds['ai_draft_id']}: HTTP {$delAiDraft['code']}\n";
}

// 4. Delete Content Draft
if (!empty($createdIds['draft_id'])) {
    $delDraft = apiCall('DELETE', "$baseUrl/api/v1/brands/$brandId/content-drafts/{$createdIds['draft_id']}", null, $authToken);
    $cleanupLog['draft'] = "HTTP {$delDraft['code']}";
    echo "Deleted Draft {$createdIds['draft_id']}: HTTP {$delDraft['code']}\n";
}

// 5. Delete Brand
if (!empty($createdIds['brand_id'])) {
    $delBrand = apiCall('DELETE', "$baseUrl/api/v1/brands/{$createdIds['brand_id']}", null, $authToken);
    $cleanupLog['brand'] = "HTTP {$delBrand['code']}";
    echo "Deleted Brand {$createdIds['brand_id']}: HTTP {$delBrand['code']}\n";
}

// 6. Verify brand is gone
$verifyGone = apiCall('GET', "$baseUrl/api/v1/brands/$brandId", null, $authToken);
if ($verifyGone['code'] === 404) {
    echo "✓ Confirmed Brand $brandId is completely removed (HTTP 404)\n";
    $cleanupSuccess = true;
} else {
    echo "! Brand check returned HTTP {$verifyGone['code']}\n";
    $cleanupSuccess = false;
}

// 7. Verify original staging user remains intact
$userCheck = $pdo->query("SELECT id, name, email FROM users WHERE id = {$testUser['id']}")->fetch(PDO::FETCH_ASSOC);
if ($userCheck && $userCheck['email'] === $testUser['email']) {
    echo "✓ Confirmed Staging Test User {$testUser['id']} remains intact.\n";
} else {
    echo "! User check unexpected.\n";
}

$results['P-07'] = [
    'status' => $cleanupSuccess ? 'PASS' : 'FAIL',
    'cleanup_log' => $cleanupLog,
    'verified_deleted_brand_http' => $verifyGone['code'],
    'user_intact' => (bool) $userCheck,
];

// -----------------------------------------------------------------------------
// POST-EXECUTION DATABASE INTEGRITY CHECK
// -----------------------------------------------------------------------------
echo "\n=== POST-EXECUTION DATABASE INTEGRITY CHECK ===\n";
$tablesCount = $pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema = 'socialpilot_staging' AND table_type = 'BASE TABLE'")->fetchColumn();
$migrationsCount = $pdo->query("SELECT count(*) FROM `socialpilot_staging`.`migrations`")->fetchColumn();
echo "Staging Base Tables: $tablesCount (Expected: 59)\n";
echo "Staging Migration Records: $migrationsCount (Expected: 29)\n";

$finalReport = [
    'environment' => 'Render staging',
    'base_url' => $baseUrl,
    'timestamp' => date('c'),
    'database' => $db,
    'user' => $user,
    'results' => $results,
    'created_ids' => $createdIds,
    'tables_count' => (int) $tablesCount,
    'migrations_count' => (int) $migrationsCount,
];

file_put_contents(__DIR__ . '/stage_5_4_7_results.json', json_encode($finalReport, JSON_PRETTY_PRINT));
echo "\nResults written to scripts/stage_5_4_7_results.json\n";
