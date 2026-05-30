<?php

use App\Services\DocxPlaceholderExtractor;
use App\Support\PrintFormPlaceholderPathResolver;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$file = __DIR__.'/../public/change/ДЗ_международка_с_заказчиком.docx';
$extractor = app(DocxPlaceholderExtractor::class);
$resolver = app(PrintFormPlaceholderPathResolver::class);

foreach ($extractor->extractFromFile($file) as $placeholder) {
    $resolved = $resolver->resolve($placeholder, [], 'order', 'customer');
    $flag = ($resolved === $placeholder && ! str_contains($placeholder, '.')) ? ' ← нет legacy' : '';
    echo "{$placeholder} => {$resolved}{$flag}\n";
}
