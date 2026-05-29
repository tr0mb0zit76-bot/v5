<?php

use App\Services\DocxPlaceholderExtractor;
use App\Support\PrintFormPlaceholderPathResolver;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$extractor = app(DocxPlaceholderExtractor::class);
$resolver = app(PrintFormPlaceholderPathResolver::class);

foreach ([
    'public/change/Заявка с перевозчиком ВЭД.docx' => 'carrier',
    'public/change/Заявка с заказчиком ВЭД.docx' => 'customer',
] as $file => $party) {
    echo "=== {$file} (party={$party}) ===\n";
    $ph = $extractor->extractFromFile($file);
    $missing = [];
    foreach ($ph as $p) {
        $resolved = $resolver->resolve($p, [], 'order', $party);
        if ($resolved === $p && ! str_contains($p, '.')) {
            $missing[] = $p;
        }
    }
    echo 'Без сопоставления: '.(count($missing) === 0 ? 'нет' : implode(', ', $missing))."\n\n";
}
