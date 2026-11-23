<?php
$files = ['.env.production.local', '.env.staging.local', '.env'];
foreach ($files as $f) {
    $path = __DIR__ . '/../' . $f;
    echo "=== {$f} ===\n";
    if (!file_exists($path)) {
        echo "File does not exist.\n\n";
        continue;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $l) {
        $l = trim($l);
        if (str_starts_with($l, '#')) continue;
        if (preg_match('/^(MEDIA|AWS|FILESYSTEM|SUPABASE)/i', $l)) {
            $parts = explode('=', $l, 2);
            $k = $parts[0] ?? '';
            $v = $parts[1] ?? '';
            if (preg_match('/KEY|SECRET|PASS|TOKEN/i', $k)) {
                echo "{$k}=[SET: " . strlen($v) . " chars]\n";
            } else {
                echo "{$k}={$v}\n";
            }
        }
    }
    echo "\n";
}
