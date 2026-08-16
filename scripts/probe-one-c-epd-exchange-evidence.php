<?php

declare(strict_types=1);

/**
 * Verify whether 1C publication has live ETRN / EPD exchange evidence (not just EntitySet names).
 * ASCII-safe. Run from CRM root on prod/local with ONE_C http.
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

$http = Http::withBasicAuth((string) config('one_c.username'), (string) config('one_c.password'))
    ->timeout(60)
    ->acceptJson();

echo 'host='.(parse_url($baseUrl, PHP_URL_HOST) ?: 'empty')."\n";

$etrn = 'Document_'."\u{042D}\u{043B}\u{0435}\u{043A}\u{0442}\u{0440}\u{043E}\u{043D}\u{043D}\u{0430}\u{044F}\u{0422}\u{0440}\u{0430}\u{043D}\u{0441}\u{043F}\u{043E}\u{0440}\u{0442}\u{043D}\u{0430}\u{044F}\u{041D}\u{0430}\u{043A}\u{043B}\u{0430}\u{0434}\u{043D}\u{0430}\u{044F}";
$paths = [
    'etrn' => '/odata/standard.odata/'.$etrn,
    'epd_registry' => '/odata/standard.odata/InformationRegister_'."\u{0420}\u{0435}\u{0435}\u{0441}\u{0442}\u{0440}\u{042D}\u{041F}\u{0414}",
    'epd_title_versions' => '/odata/standard.odata/InformationRegister_'."\u{0412}\u{0435}\u{0440}\u{0441}\u{0438}\u{0438}\u{0422}\u{0438}\u{0442}\u{0443}\u{043B}\u{043E}\u{0432}\u{042D}\u{041F}\u{0414}",
    'epd_mp_settings' => '/odata/standard.odata/InformationRegister_'."\u{041D}\u{0430}\u{0441}\u{0442}\u{0440}\u{043E}\u{0439}\u{043A}\u{0438}\u{0412}\u{0437}\u{0430}\u{0438}\u{043C}\u{043E}\u{0434}\u{0435}\u{0439}\u{0441}\u{0442}\u{0432}\u{0438}\u{044F}\u{041C}\u{041F}\u{042D}\u{041F}\u{0414}",
    'epd_tokens' => '/odata/standard.odata/InformationRegister_'."\u{0422}\u{043E}\u{043A}\u{0435}\u{043D}\u{044B}\u{0410}\u{0432}\u{0442}\u{043E}\u{0440}\u{0438}\u{0437}\u{0430}\u{0446}\u{0438}\u{0438}\u{042D}\u{041F}\u{0414}",
    'epd_stored' => '/odata/standard.odata/Catalog_'."\u{0425}\u{0440}\u{0430}\u{043D}\u{0438}\u{043C}\u{044B}\u{0435}\u{0414}\u{0430}\u{043D}\u{043D}\u{044B}\u{0435}\u{042D}\u{041F}\u{0414}",
];

foreach ($paths as $label => $path) {
    $url = $baseUrl.$path;
    try {
        $countResp = $http->get($url, ['$top' => 0, '$count' => 'true', '$format' => 'json']);
        $topResp = $http->get($url, ['$top' => 3, '$format' => 'json', '$orderby' => 'Ref_Key desc']);
        // Information registers may not have Ref_Key — fallback without orderby
        if (! $topResp->successful()) {
            $topResp = $http->get($url, ['$top' => 3, '$format' => 'json']);
        }

        $count = $countResp->header('OData-Count')
            ?? data_get($countResp->json(), '@odata.count')
            ?? data_get($countResp->json(), 'odata.count');

        echo "--- {$label} ---\n";
        echo 'http_count='.$countResp->status().' http_top='.$topResp->status()."\n";
        if ($count !== null && $count !== '') {
            echo "count={$count}\n";
        } else {
            $value = data_get($topResp->json(), 'value');
            $n = is_array($value) ? count($value) : 0;
            echo "count_header=(none) top_rows={$n}\n";
        }

        $rows = data_get($topResp->json(), 'value');
        if (is_array($rows) && $rows !== []) {
            $sample = $rows[0];
            $keys = array_slice(array_keys($sample), 0, 25);
            echo 'sample_keys='.implode(',', $keys)."\n";
            foreach (['Number', 'Date', 'Posted', 'DeletionMark', 'Комментарий', 'ТекущийТитул', 'ТекущийПолученныйТитул'] as $k) {
                if (array_key_exists($k, $sample)) {
                    $v = $sample[$k];
                    if (is_bool($v)) {
                        $v = $v ? 'true' : 'false';
                    }
                    if (is_scalar($v) || $v === null) {
                        echo "sample.{$k}=".(($v === null || $v === '') ? '(empty)' : $v)."\n";
                    }
                }
            }
        } else {
            echo "sample=(empty or error)\n";
            if (! $topResp->successful()) {
                echo 'body='.substr($topResp->body(), 0, 240)."\n";
            }
        }
    } catch (Throwable $e) {
        echo "--- {$label} ---\n";
        echo 'error='.$e->getMessage()."\n";
    }
}

echo "done\n";
