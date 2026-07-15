<?php

namespace App\Console\Commands;

use App\Services\Commercial\ProposalEmlImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProposalTemplatesImportEmlCommand extends Command
{
    protected $signature = 'proposal-templates:import-eml
        {path : Путь к .eml файлу}
        {--name= : Название шаблона}
        {--slug= : Slug шаблона}';

    protected $description = 'Импорт HTML-шаблона КП из .eml (EmailMaker) с встроенными картинками в сток';

    public function handle(ProposalEmlImportService $importService): int
    {
        $path = (string) $this->argument('path');
        $name = trim((string) ($this->option('name') ?: 'Логистические решения'));
        $slug = trim((string) ($this->option('slug') ?: Str::slug($name) ?: 'logistic-solutions'));

        try {
            $result = $importService->importFile($path, $name, $slug);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $template = $result['template'];

        $this->info("Шаблон #{$template->id} «{$template->name}» (slug={$template->slug})");
        $this->line("Картинок: {$result['assets_written']}, HTML: {$result['html_bytes']} байт");
        $this->line('Редактор: '.route('modules.proposal-templates.edit', $template));

        return self::SUCCESS;
    }
}
