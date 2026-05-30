<?php

use App\Services\DocxPlaceholderExtractor;
use App\Support\PrintFormPlaceholderPathResolver;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$extractor = app(DocxPlaceholderExtractor::class);
$resolver = app(PrintFormPlaceholderPathResolver::class);

$overlayPlaceholders = ['signature', 'stamp'];
$cloneRowPlaceholders = [
    'cargo_row_index',
    'cargo_row_name',
    'cargo_row_summary',
    'cargo_row_text',
    'cargo_row_weight',
    'cargo_row_volume',
    'cargo_row_packages',
    'cargo_row_hs_code',
    'cargo_row_dimensions',
];

$allMissing = [];
$allSpecial = [];

foreach (glob(__DIR__.'/../public/change/*.docx') ?: [] as $file) {
    if (str_contains(basename($file), '~$')) {
        continue;
    }

    $party = str_contains($file, 'перевозчик') ? 'carrier' : 'customer';
    $placeholders = $extractor->extractFromFile($file);
    $missing = [];
    $special = [];

    foreach ($placeholders as $placeholder) {
        $resolved = $resolver->resolve($placeholder, [], 'order', $party);

        if ($resolved !== $placeholder || str_contains($placeholder, '.')) {
            continue;
        }

        if (in_array($placeholder, $overlayPlaceholders, true) || in_array($placeholder, $cloneRowPlaceholders, true)) {
            $special[] = $placeholder;

            continue;
        }

        $missing[] = $placeholder;
        $allMissing[$placeholder] = true;
    }

    foreach ($special as $item) {
        $allSpecial[$item] = true;
    }

    echo '=== '.basename($file)." (party={$party}) ===\n";
    echo 'Без legacy-сопоставления: '.(count($missing) === 0 ? '—' : implode(', ', $missing))."\n";
    echo 'Спец. обработка (cloneRow / подпись-печать): '.(count($special) === 0 ? '—' : implode(', ', array_unique($special)))."\n\n";
}

echo "=== Итого уникальные ===\n";
echo 'Нужен legacy-маппинг: '.(count($allMissing) === 0 ? '—' : implode(', ', array_keys($allMissing)))."\n";
echo 'Спец. обработка: '.(count($allSpecial) === 0 ? '—' : implode(', ', array_keys($allSpecial)))."\n";
