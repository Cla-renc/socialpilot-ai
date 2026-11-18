$f = "C:\PROJECTS\SOCIALPILOT AI\storage\app\backups\backup_tidb_staging_migrator_socialpilot_staging_2026-11-18_13-29-52.sql"
$content = Get-Content $f -Raw -Encoding UTF8

Write-Host "=== BACKUP FILE METADATA ==="
$item = Get-Item $f
Write-Host "File: $($item.Name)"
Write-Host "Size (bytes): $($item.Length)"
Write-Host "Size (KB): $([math]::Round($item.Length/1024, 2))"
Write-Host "Created: $($item.LastWriteTime)"

Write-Host ""
Write-Host "=== HEADER (first 8 lines) ==="
($content -split "`n")[0..7] | ForEach-Object { Write-Host $_ }

Write-Host ""
Write-Host "=== STRUCTURE COUNTS ==="
$drops   = ([regex]::Matches($content, "DROP TABLE IF EXISTS")).Count
$creates = ([regex]::Matches($content, "CREATE TABLE")).Count
Write-Host "DROP TABLE IF EXISTS:  $drops"
Write-Host "CREATE TABLE:          $creates"

Write-Host ""
Write-Host "=== TABLE NAMES IN BACKUP (sorted) ==="
$tables = [regex]::Matches($content, 'DROP TABLE IF EXISTS `([^`]+)`') | ForEach-Object { $_.Groups[1].Value } | Sort-Object
$tables | ForEach-Object { Write-Host "  $_" }
Write-Host "Total: $($tables.Count)"

Write-Host ""
Write-Host "=== MIGRATIONS TABLE DATA ==="
# Extract migration INSERT lines
$migLines = ($content -split "`n") | Where-Object { $_ -match "INSERT INTO \`migrations\`" }
Write-Host "Migration INSERT rows: $($migLines.Count)"
$migLines | ForEach-Object { Write-Host "  $_" }

Write-Host ""
Write-Host "=== SECRETS SCAN ==="
$secretPatterns = @("password", "secret", "token_value", "api_key", "private_key", "access_token")
$linesTotal = ($content -split "`n").Count
Write-Host "Total lines in backup: $linesTotal"
foreach ($pattern in $secretPatterns) {
    $hits = ([regex]::Matches($content, $pattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)).Count
    if ($hits -gt 0) {
        Write-Host "  Pattern '$pattern': $hits occurrences (review manually)"
    } else {
        Write-Host "  Pattern '$pattern': 0 occurrences — CLEAN"
    }
}

Write-Host ""
Write-Host "=== DANGEROUS OPERATIONS SCAN ==="
foreach ($op in @("DROP DATABASE", "DROP SCHEMA", "GRANT ", "CREATE USER", "ALTER USER", "REVOKE")) {
    $hits = ([regex]::Matches($content, $op, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)).Count
    Write-Host "  '$op': $hits occurrences"
}

Write-Host ""
Write-Host "=== VERIFY: media_assets and media_generation_jobs present ==="
foreach ($t in @("media_assets", "media_generation_jobs", "media_storyboards", "media_storyboard_scenes", "media_visual_settings")) {
    $found = $content -match "DROP TABLE IF EXISTS \`$t\`"
    Write-Host "  ${t}: $(if ($found) { 'PRESENT' } else { 'ABSENT' })"
}

Write-Host ""
Write-Host "=== VERIFY: No data rows for media tables (should be empty) ==="
foreach ($t in @("media_assets", "media_generation_jobs")) {
    $inserts = ([regex]::Matches($content, "INSERT INTO \`$t\`")).Count
    Write-Host "  ${t} INSERT rows: $inserts (expected: 0)"
}
