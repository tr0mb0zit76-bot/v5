<?php

declare(strict_types=1);
use App\Services\DocxPlaceholderExtractor;
use Illuminate\Contracts\Console\Kernel;

$file = $argv[1] ?? __DIR__.'/../public/change/Заявка с заказчиком ВЭД.docx';

$zip = new ZipArchive;
if ($zip->open($file) !== true) {
    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$pattern = '#(<w:t(?:\s[^>]*)?>)с</w:t></w:r>'
    .'(?:\s|<w:proofErr[^>]*/>)*'
    .'<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?\s*<w:t(?:\s[^>]*)?>p_#sU';

$fixed = (string) preg_replace($pattern, '\\1cp_', $xml, -1, $count);
if ($fixed !== $xml) {
    $zip->addFromString('word/document.xml', $fixed);
    echo "Fixed split Cyrillic cp_ in {$count} place(s)\n";
} else {
    echo "No split Cyrillic cp_ found\n";
}

$zip->close();

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$ph = app(DocxPlaceholderExtractor::class)->extractFromFile($file);
$cyr = array_filter($ph, fn (string $p): bool => str_starts_with($p, 'сp_'));
echo 'Cyrillic cp_ placeholders left: '.count($cyr)."\n";
