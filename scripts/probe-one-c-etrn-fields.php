<?php

declare(strict_types=1);

/**
 * Dump OData EntityType properties for Electronic Transport Waybill (ETRN).
 * ASCII-safe source (unicode escapes). Run from CRM root.
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

$entityLocal = "\u{042D}\u{043B}\u{0435}\u{043A}\u{0442}\u{0440}\u{043E}\u{043D}\u{043D}\u{0430}\u{044F}\u{0422}\u{0440}\u{0430}\u{043D}\u{0441}\u{043F}\u{043E}\u{0440}\u{0442}\u{043D}\u{0430}\u{044F}\u{041D}\u{0430}\u{043A}\u{043B}\u{0430}\u{0434}\u{043D}\u{0430}\u{044F}";
// ElectronicTransportnayaNakladnaya
$entityName = 'Document_'.$entityLocal;

echo 'host='.(parse_url($baseUrl, PHP_URL_HOST) ?: 'empty')."\n";
echo "entity={$entityName}\n";

$response = Http::withBasicAuth((string) config('one_c.username'), (string) config('one_c.password'))
    ->timeout(90)
    ->accept('application/xml')
    ->get($baseUrl.'/odata/standard.odata/$metadata');

echo 'http='.$response->status().' bytes='.strlen($response->body())."\n";
if (! $response->successful()) {
    exit(1);
}

$xml = $response->body();

// Find EntityType Name="Document_ЭлектроннаяТранспортнаяНакладная" ... </EntityType>
$quoted = preg_quote($entityName, '/');
if (! preg_match('/<EntityType[^>]*Name="'.$quoted.'"[^>]*>(.*?)<\\/EntityType>/s', $xml, $blockMatch)) {
    // Sometimes Name comes before other attrs order differs — already covered.
    // Try without Document_ prefix variants.
    echo "EntityType block not found\n";
    exit(1);
}

$block = $blockMatch[1];
echo 'block_bytes='.strlen($block)."\n";

preg_match_all(
    '/<Property Name="([^"]+)"[^>]*Type="([^"]+)"([^>]*)\\/?>/',
    $block,
    $props,
    PREG_SET_ORDER
);

preg_match_all(
    '/<NavigationProperty Name="([^"]+)"[^>]*Type="([^"]+)"([^>]*)\\/?>/',
    $block,
    $navs,
    PREG_SET_ORDER
);

echo 'properties='.count($props)."\n";
echo 'navigations='.count($navs)."\n";

$nullableTrue = [];
$nullableFalse = [];
$other = [];

foreach ($props as $prop) {
    $name = $prop[1];
    $type = $prop[2];
    $attrs = $prop[3];
    $nullable = null;
    if (preg_match('/Nullable="(true|false)"/', $attrs, $nm)) {
        $nullable = $nm[1] === 'true';
    }
    $line = "{$name}\t{$type}\tnullable=".($nullable === null ? '?' : ($nullable ? '1' : '0'));

    if ($nullable === false) {
        $nullableFalse[] = $line;
    } elseif ($nullable === true) {
        $nullableTrue[] = $line;
    } else {
        $other[] = $line;
    }
}

echo "--- required_or_non_nullable ---\n";
foreach ($nullableFalse as $line) {
    echo $line."\n";
}
if ($nullableFalse === []) {
    echo "(none marked Nullable=false)\n";
}

echo "--- sample_nullable_true (first 40) ---\n";
foreach (array_slice($nullableTrue, 0, 40) as $line) {
    echo $line."\n";
}

echo "--- navigations ---\n";
foreach ($navs as $nav) {
    echo $nav[1]."\t".$nav[2]."\n";
}

// Also list tabular sections EntitySets linked by name prefix
$prefix = preg_quote($entityName.'_', '/');
preg_match_all('/EntitySet Name="('.$prefix.'[^"]+)"/', $xml, $tabs);
$tabs = $tabs[1] ?? [];
sort($tabs, SORT_STRING);
echo "tabular_entity_sets=".count($tabs)."\n";
foreach ($tabs as $tab) {
    echo $tab."\n";
}

// Prefer interesting field names for stub mapping
$interestingNeedles = [
    "\u{041A}\u{043E}\u{043C}\u{043C}\u{0435}\u{043D}\u{0442}\u{0430}\u{0440}\u{0438}\u{0439}", // Comment
    'Date',
    'Number',
    'Posted',
    "\u{041E}\u{0440}\u{0433}\u{0430}\u{043D}\u{0438}\u{0437}\u{0430}\u{0446}\u{0438}\u{044F}", // Organization
    "\u{041A}\u{043E}\u{043D}\u{0442}\u{0440}\u{0430}\u{0433}\u{0435}\u{043D}\u{0442}", // Counterparty
    "\u{0413}\u{0440}\u{0443}\u{0437}\u{043E}", // Gruzo...
    "\u{041F}\u{0435}\u{0440}\u{0435}\u{0432}\u{043E}\u{0437}", // Carrier
    "\u{0412}\u{043E}\u{0434}\u{0438}\u{0442}", // Driver
    "\u{0422}\u{0421}",
    "\u{0422}\u{0438}\u{0442}\u{0443}\u{043B}", // Title
    "\u{0421}\u{0442}\u{0430}\u{0442}\u{0443}\u{0441}", // Status
];

echo "--- interesting_properties ---\n";
foreach ($props as $prop) {
    $name = $prop[1];
    foreach ($interestingNeedles as $needle) {
        if (mb_stripos($name, $needle) !== false) {
            $attrs = $prop[3];
            $nullable = '?';
            if (preg_match('/Nullable="(true|false)"/', $attrs, $nm)) {
                $nullable = $nm[1] === 'true' ? '1' : '0';
            }
            echo "{$name}\t{$prop[2]}\tnullable={$nullable}\n";
            break;
        }
    }
}
