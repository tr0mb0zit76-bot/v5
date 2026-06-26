<?php

namespace Database\Seeders;

use App\Models\PrintFormTemplate;
use App\Services\DocxPlaceholderExtractor;
use App\Support\PrintFormPlaceholderPathResolver;
use Illuminate\Database\Seeder;

/**
 * Обновляет сопоставления плейсхолдеров для шаблонов заявок ВЭД в БД (prod-safe).
 *
 * Запуск: php artisan db:seed --class=PrintFormVedTemplateMappingSeeder
 *
 * Ищет шаблоны заказа по коду или имени, содержащему «ВЭД».
 */
class PrintFormVedTemplateMappingSeeder extends Seeder
{
    /** @var list<string> */
    private const TEMPLATE_CODES = [
        'order_ved_carrier',
        'order_ved_customer',
        'ved_carrier',
        'ved_customer',
    ];

    public function run(): void
    {
        $resolver = app(PrintFormPlaceholderPathResolver::class);
        $extractor = app(DocxPlaceholderExtractor::class);

        $templates = PrintFormTemplate::query()
            ->where('entity_type', 'order')
            ->where(function ($query): void {
                $query->where('name', 'like', '%ВЭД%')
                    ->orWhere('name', 'like', '%перевоз%')
                    ->orWhereIn('code', self::TEMPLATE_CODES);
            })
            ->get();

        if ($templates->isEmpty()) {
            $this->command?->warn('Шаблоны ВЭД в print_form_templates не найдены — пропуск.');

            return;
        }

        foreach ($templates as $template) {
            $this->syncTemplateMapping($template, $resolver, $extractor);
        }
    }

    private function syncTemplateMapping(
        PrintFormTemplate $template,
        PrintFormPlaceholderPathResolver $resolver,
        DocxPlaceholderExtractor $extractor,
    ): void {
        $settings = is_array($template->settings) ? $template->settings : [];
        $party = is_string($template->party) && $template->party !== '' ? $template->party : null;

        $placeholders = $extractor->placeholdersForTemplate($template);

        if ($placeholders === []) {
            $this->command?->warn("Шаблон #{$template->id} «{$template->name}»: плейсхолдеры не найдены.");

            return;
        }

        $mapping = collect($resolver->effectiveVariableMapping($placeholders, [], 'order', $party))
            ->reject(fn (string $path, string $placeholder): bool => $path === $placeholder)
            ->all();

        $settings['variables'] = $placeholders;
        $settings['variable_mapping'] = $mapping;
        $settings['variable_count'] = count($placeholders);
        $settings['parsed_at'] = now()->toIso8601String();
        $settings['pipeline_status'] = 'placeholders_ready';

        $template->forceFill(['settings' => $settings])->save();

        $unresolved = collect($mapping)
            ->filter(fn (string $path, string $placeholder): bool => $path === $placeholder)
            ->keys()
            ->values()
            ->all();

        $this->command?->info(sprintf(
            'Шаблон #%d «%s» (party=%s): %d плейсхолдеров, %d без сопоставления.',
            $template->id,
            $template->name,
            $party ?? '—',
            count($placeholders),
            count($unresolved),
        ));

        if ($unresolved !== [] && $this->command !== null) {
            $this->command->line('  Без сопоставления: '.implode(', ', $unresolved));
        }
    }
}
