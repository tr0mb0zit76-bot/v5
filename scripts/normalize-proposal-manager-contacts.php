<?php

declare(strict_types=1);

use App\Models\ProposalHtmlTemplate;
use App\Support\ProposalHtmlManagerContactNormalizer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! Schema::hasTable('proposal_html_templates')) {
    fwrite(STDERR, "No proposal_html_templates table.\n");
    exit(1);
}

$slugs = [
    'parallel-import',
    'parallel-import-demo',
    'hard-to-reach-regions',
    'dangerous-goods',
    'export-solutions',
    'special-equipment',
];

$updated = 0;

foreach (ProposalHtmlTemplate::query()->whereIn('slug', $slugs)->orderBy('id')->get() as $template) {
    $before = (string) $template->html_body;
    $after = ProposalHtmlManagerContactNormalizer::normalize($before);

    if ($after === $before) {
        echo "SKIP #{$template->id} {$template->slug}\n";

        continue;
    }

    $template->html_body = $after;
    $template->save();
    $updated++;

    $hasManager = str_contains($after, '{manager.name}')
        && str_contains($after, '{manager.phone}')
        && str_contains($after, '{manager.email}');
    $hasGray = str_contains(strtolower($after), 'e5e5e5');

    echo sprintf(
        "OK #%d %s manager=%s gray=%s\n",
        $template->id,
        $template->slug,
        $hasManager ? 'yes' : 'no',
        $hasGray ? 'YES' : 'no',
    );
}

echo "Updated: {$updated}\n";
