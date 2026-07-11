<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$plink = $root.'/scripts/prod-plink.ps1';
$sql = "mysql -u logodmin -pvP1xU4qV0s clear_base --default-character-set=utf8mb4 -N -e 'SELECT p.title, a.title FROM sales_book_articles a LEFT JOIN sales_book_articles p ON p.id=a.parent_id ORDER BY a.id' | base64 -w0";
$command = "powershell -NoProfile -Command \"& '{$plink}' '{$sql}'\"";
$b64 = trim((string) shell_exec($command));
if ($b64 === '') {
    fwrite(STDERR, "Failed to fetch prod articles.\n");
    exit(1);
}

$raw = base64_decode($b64, true);
if ($raw === false) {
    fwrite(STDERR, "Invalid base64 payload.\n");
    exit(1);
}

$lines = [];
foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, 'mysql:')) {
        continue;
    }
    if (! str_contains($line, "\t")) {
        continue;
    }
    [$parent, $title] = explode("\t", $line, 2);
    if (strcasecmp($parent, 'NULL') === 0) {
        $parent = '';
    }
    $lines[] = $parent."\t".$title;
}

$out = $root.'/tools/prod-existing-utf8.tsv';
file_put_contents($out, implode("\n", $lines)."\n");
echo 'lines='.count($lines).PHP_EOL;
