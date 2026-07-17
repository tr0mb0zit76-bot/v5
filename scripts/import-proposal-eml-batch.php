<?php

declare(strict_types=1);

use App\Models\ProposalHtmlTemplate;
use App\Services\Commercial\ProposalEmlImportService;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var list<array{slug: string, name: string}> $jobs */
$jobs = [
    ['slug' => 'hard-to-reach-regions', 'name' => 'Труднодоступные регионы'],
    ['slug' => 'dangerous-goods', 'name' => 'Опасные грузы'],
    ['slug' => 'export-solutions', 'name' => 'Экспорт'],
    ['slug' => 'special-equipment', 'name' => 'Спецтехника'],
];

$sourceRoot = $argv[1] ?? resource_path('proposal-emails/eml');
$import = app(ProposalEmlImportService::class);

foreach ($jobs as $job) {
    $path = rtrim($sourceRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$job['slug'].'.eml';
    if (! is_file($path)) {
        fwrite(STDERR, "MISSING: {$path}\n");
        exit(1);
    }

    $result = $import->importFile($path, $job['name'], $job['slug']);
    $template = $result['template'];

    echo sprintf(
        "OK #%d %s slug=%s assets=%d html=%d css=%d\n",
        $template->id,
        $template->name,
        $template->slug,
        $result['assets_written'],
        strlen((string) $template->html_body),
        strlen((string) $template->css_inline),
    );
}

echo "Total rich templates: ".ProposalHtmlTemplate::query()
    ->whereIn('slug', array_column($jobs, 'slug'))
    ->count().PHP_EOL;
