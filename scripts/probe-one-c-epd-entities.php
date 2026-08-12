<?php

declare(strict_types=1);

/**
 * Spike EntitySet OData (ASCII-safe source: unicode escapes only).
 * Run from CRM root: php scripts/probe-one-c-epd-entities.php
 */

use App\Services\OneC\OneCPublicationCatalog;
use Illuminate\Support\Facades\Http;

$base = getcwd();
if (! is_file($base.'/vendor/autoload.php')) {
    $base = dirname(__DIR__);
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! (bool) config('one_c.enabled') || (string) config('one_c.driver') !== 'http') {
    echo "need ONE_C_ENABLED + driver=http\n";
    exit(1);
}

$catalog = app(OneCPublicationCatalog::class);
$pubCode = (string) config('one_c.default_publication', 'autalliance');
try {
    $baseUrl = rtrim((string) ($catalog->get($pubCode)['base_url'] ?? ''), '/');
} catch (Throwable) {
    $baseUrl = rtrim((string) config('one_c.base_url', ''), '/');
}

echo 'host='.(parse_url($baseUrl, PHP_URL_HOST) ?: 'empty')."\n";

$response = Http::withBasicAuth((string) config('one_c.username'), (string) config('one_c.password'))
    ->timeout(90)
    ->accept('application/xml')
    ->get($baseUrl.'/odata/standard.odata/$metadata');

echo 'http='.$response->status().' bytes='.strlen($response->body())."\n";
if (! $response->successful()) {
    exit(1);
}

$xml = $response->body();
preg_match_all('/EntitySet Name="([^"]+)"/', $xml, $m);
$sets = $m[1] ?? [];
sort($sets, SORT_STRING);
echo 'sets='.count($sets)."\n";

// Unicode escapes keep this file ASCII-safe over Windows->Linux pipe.
$needles = [
    "\u{042D}\u{043B}\u{0435}\u{043A}\u{0442}\u{0440}\u{043E}\u{043D}", // Electron...
    "\u{0422}\u{0440}\u{0430}\u{043D}\u{0441}\u{043F}\u{043E}\u{0440}\u{0442}", // Transport
    "\u{041D}\u{0430}\u{043A}\u{043B}\u{0430}\u{0434}", // Naklad
    "\u{042D}\u{043A}\u{0441}\u{043F}\u{0435}\u{0434}\u{0438}\u{0442}\u{043E}\u{0440}", // Expeditor
    "\u{0420}\u{0430}\u{0441}\u{043F}\u{0438}\u{0441}", // Raspis
    "\u{042D}\u{041F}\u{0414}", // EPD
    "\u{042D}\u{0422}\u{0440}\u{041D}", // ETrN
    "\u{041F}\u{0435}\u{0440}\u{0435}\u{0432}\u{043E}\u{0437}", // Perevoz
    'EPD',
    'ETRN',
    'EDO',
];

$hits = [];
foreach ($sets as $set) {
    foreach ($needles as $needle) {
        if (mb_stripos($set, $needle) !== false) {
            $hits[] = $set;
            break;
        }
    }
}

$hits = array_values(array_unique($hits));
sort($hits, SORT_STRING);
echo 'hits='.count($hits)."\n";
foreach ($hits as $set) {
    echo $set."\n";
}

$knownEdo = array_values(array_filter(
    $sets,
    static fn (string $s): bool => str_contains($s, "\u{042D}\u{0414}\u{041E}") // EDO
));
echo 'edo_related='.count($knownEdo)."\n";
foreach (array_slice($knownEdo, 0, 40) as $set) {
    echo $set."\n";
}
