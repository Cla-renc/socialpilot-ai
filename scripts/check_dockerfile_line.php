<?php
$content = file_get_contents(__DIR__ . '/../Dockerfile.unified');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (str_contains($line, 'composer install')) {
        echo "Line " . ($i + 1) . ":\n";
        echo "Raw: " . $line . "\n";
        echo "Hex: " . bin2hex($line) . "\n";
        for ($c = 0; $c < strlen($line); $c++) {
            $ord = ord($line[$c]);
            if ($ord < 32 || $ord > 126) {
                echo "Special char at pos $c: ord=$ord (0x" . dechex($ord) . ")\n";
            }
        }
    }
}
