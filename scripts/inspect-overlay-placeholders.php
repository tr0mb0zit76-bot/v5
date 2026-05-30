<?php

use App\Services\DocxPlaceholderExtractor;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$extractor = app(DocxPlaceholderExtractor::class);

foreach (glob(__DIR__.'/../public/change/*.docx') as $file) {
    $ph = $extractor->extractFromFile($file);
    $ov = array_values(array_filter($ph, static fn (string $p): bool => preg_match('/sign|stamp|stmp/i', $p) === 1));
    if ($ov !== []) {
        echo basename($file).': '.implode(', ', $ov)."\n";
    }
}
