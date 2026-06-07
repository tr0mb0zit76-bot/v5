<?php

namespace App\Services\Mcp;

use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Services\DocxPlaceholderExtractor;
use App\Services\PrintForm\PrintFormBasicTermsService;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\DocxPrintFormPlaceholderPreprocessor;
use App\Support\PrintFormBasicTermsTableCloner;
use App\Support\PrintFormTemplateDiskSource;
use App\Support\PrintFormTemplateProcessorPreparer;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpWord\TemplateProcessor;

final class PrintFormTemplatesMcpService
{
    public function __construct(
        private readonly PrintFormBasicTermsService $basicTermsService,
        private readonly PrintFormTemplateOrderEligibility $eligibility,
    ) {}

    /**
     * @return array{
     *     templates: list<array<string, mixed>>,
     *     template: array<string, mixed>|null,
     *     basic_terms: array<string, mixed>,
     *     diagnostics: list<array{level: string, code: string, message: string}>
     * }
     */
    public function insights(?string $code = null, ?string $query = null, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $code = $this->normalizeOptional($code);
        $query = $this->normalizeOptional($query);

        $templates = $this->searchTemplates($code, $query, $limit);
        $focus = $code !== null
            ? collect($templates)->first(fn (array $row): bool => strtolower((string) ($row['code'] ?? '')) === strtolower($code))
            : ($templates[0] ?? null);

        $basicTerms = $this->basicTermsSnapshot();
        $diagnostics = [];

        if ($code !== null && $focus === null) {
            $diagnostics[] = $this->diagnostic(
                'error',
                'template_not_found',
                "Шаблон с кодом «{$code}» не найден.",
            );
        }

        if (is_array($focus)) {
            $diagnostics = array_merge($diagnostics, $this->diagnosticsForTemplate($focus, $basicTerms));
        }

        return [
            'templates' => $templates,
            'template' => $focus,
            'basic_terms' => $basicTerms,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchTemplates(?string $code, ?string $query, int $limit): array
    {
        if (! Schema::hasTable('print_form_templates')) {
            return [];
        }

        $builder = PrintFormTemplate::query()
            ->orderByDesc('is_default')
            ->orderBy('name');

        if ($code !== null) {
            $builder->where('code', $code);
        } elseif ($query !== null) {
            $builder->where(function ($inner) use ($query): void {
                $inner->where('code', 'like', '%'.$query.'%')
                    ->orWhere('name', 'like', '%'.$query.'%');
            });
        }

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (PrintFormTemplate $template): array => $this->serializeTemplate($template))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(PrintFormTemplate $template): array
    {
        $variables = collect($template->settings['variables'] ?? [])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->values()
            ->all();

        $basicTermsMacros = collect($variables)
            ->filter(fn (string $variable): bool => PrintFormBasicTermsTableCloner::isBasicTermsPlaceholder($variable))
            ->values()
            ->all();

        $phpWord = $this->phpWordMacroSnapshot($template);

        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'party' => $template->party,
            'party_label' => $this->partyLabel((string) ($template->party ?? '')),
            'entity_type' => $template->entity_type ?? 'order',
            'document_type' => $template->document_type,
            'is_active' => (bool) $template->is_active,
            'has_source_file' => filled($template->file_path),
            'version' => (int) $template->version,
            'pipeline_status' => data_get($template->settings, 'pipeline_status'),
            'variable_count' => count($variables),
            'variables' => $variables,
            'basic_terms_macros' => $basicTermsMacros,
            'has_customer_basic_terms_table' => $this->eligibility->templateHasBasicTermsForParty($template, PrintFormBasicTerm::PARTY_CUSTOMER),
            'has_carrier_basic_terms_table' => $this->eligibility->templateHasBasicTermsForParty($template, PrintFormBasicTerm::PARTY_CARRIER),
            'expected_basic_terms_prefix' => $this->expectedBasicTermsPrefix((string) ($template->party ?? '')),
            'phpword_variables' => $phpWord['variables'],
            'phpword_has_carrier_basic_terms_anchor' => $phpWord['has_carrier_anchor'],
            'phpword_has_customer_basic_terms_anchor' => $phpWord['has_customer_anchor'],
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{variables: list<string>, has_carrier_anchor: bool, has_customer_anchor: bool}
     */
    private function phpWordMacroSnapshot(PrintFormTemplate $template): array
    {
        if (! filled($template->file_path) || ! filled($template->file_disk)) {
            return [
                'variables' => [],
                'has_carrier_anchor' => false,
                'has_customer_anchor' => false,
            ];
        }

        try {
            $prep = PrintFormTemplateDiskSource::ensureMutableTempCopy(
                PrintFormTemplateDiskSource::prepareLocalPathForPhpWord(
                    (string) $template->file_disk,
                    (string) $template->file_path,
                ),
            );

            $settingsVariables = is_array($template->settings['variables'] ?? null)
                ? $template->settings['variables']
                : [];

            $extracted = app(DocxPlaceholderExtractor::class)->extractFromDisk(
                (string) $template->file_disk,
                (string) $template->file_path,
            );

            $placeholderNames = collect($settingsVariables)
                ->merge($extracted)
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->unique()
                ->values()
                ->all();

            DocxPrintFormPlaceholderPreprocessor::preprocess($prep['path'], $placeholderNames);

            $processor = new TemplateProcessor($prep['path']);
            $processor->setMacroChars('${', '}');

            PrintFormTemplateProcessorPreparer::repairTextMacros($processor, $placeholderNames);

            $variables = collect($processor->getVariables())
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->values()
                ->all();

            foreach ($prep['tempFiles'] as $tmpPath) {
                if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            }

            return [
                'variables' => $variables,
                'has_carrier_anchor' => PrintFormTemplateProcessorPreparer::processorHasMacro(
                    $processor,
                    'dp_basic_terms_row_text',
                ),
                'has_customer_anchor' => PrintFormTemplateProcessorPreparer::processorHasMacro(
                    $processor,
                    'cp_basic_terms_row_text',
                ),
            ];
        } catch (\Throwable) {
            return [
                'variables' => [],
                'has_carrier_anchor' => false,
                'has_customer_anchor' => false,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function basicTermsSnapshot(): array
    {
        if (! $this->basicTermsService->tablesReady()) {
            return [
                'tables_ready' => false,
                'customer' => ['global_count' => 0, 'items' => []],
                'carrier' => ['global_count' => 0, 'items' => []],
            ];
        }

        return [
            'tables_ready' => true,
            'customer' => $this->partyBasicTermsPayload(PrintFormBasicTerm::PARTY_CUSTOMER),
            'carrier' => $this->partyBasicTermsPayload(PrintFormBasicTerm::PARTY_CARRIER),
        ];
    }

    /**
     * @return array{global_count: int, items: list<array{sort_order: int, body: string}>}
     */
    private function partyBasicTermsPayload(string $party): array
    {
        $rows = $this->basicTermsService->listRows($party);

        return [
            'global_count' => count($rows),
            'items' => collect($rows)
                ->map(fn (array $row): array => [
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'body' => (string) ($row['body'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $basicTerms
     * @return list<array{level: string, code: string, message: string}>
     */
    private function diagnosticsForTemplate(array $template, array $basicTerms): array
    {
        $diagnostics = [];
        $party = (string) ($template['party'] ?? '');
        $expectedPrefix = (string) ($template['expected_basic_terms_prefix'] ?? '');
        $macros = $template['basic_terms_macros'] ?? [];
        $hasCarrierTable = (bool) ($template['has_carrier_basic_terms_table'] ?? false);
        $hasCustomerTable = (bool) ($template['has_customer_basic_terms_table'] ?? false);

        if (! ($template['has_source_file'] ?? false)) {
            $diagnostics[] = $this->diagnostic(
                'error',
                'missing_docx',
                'У шаблона нет загруженного DOCX — печать и базовые условия недоступны.',
            );
        }

        if ($expectedPrefix === '') {
            $diagnostics[] = $this->diagnostic(
                'error',
                'party_not_supported',
                'Сторона шаблона «'.($template['party_label'] ?? $party).'» не поддерживает cloneRow базовых условий. Нужна «Форма перевозчика» (dp_*) или «Форма заказчика» (cp_*).',
            );

            return $diagnostics;
        }

        $partyKey = $party === PrintFormBasicTerm::PARTY_CARRIER ? 'carrier' : 'customer';
        $globalCount = (int) data_get($basicTerms, "{$partyKey}.global_count", 0);
        $hasTable = $party === PrintFormBasicTerm::PARTY_CARRIER ? $hasCarrierTable : $hasCustomerTable;
        $anchor = $expectedPrefix.'_basic_terms_row_text';
        $indexMacro = $expectedPrefix.'_basic_terms_row_index';

        if (! $hasTable) {
            $diagnostics[] = $this->diagnostic(
                'error',
                'missing_basic_terms_placeholder',
                "В DOCX не найден якорь \${{$anchor}} (и строка таблицы с \${{$indexMacro}}). CRM не клонирует пункты — добавьте оба макроса в одну строку таблицы Word и перезагрузите файл.",
            );
        } else {
            $diagnostics[] = $this->diagnostic(
                'ok',
                'basic_terms_placeholder_found',
                "Якорь cloneRow «{$anchor}» найден среди переменных шаблона.",
            );
        }

        $phpWordHasAnchor = $party === PrintFormBasicTerm::PARTY_CARRIER
            ? (bool) ($template['phpword_has_carrier_basic_terms_anchor'] ?? false)
            : (bool) ($template['phpword_has_customer_basic_terms_anchor'] ?? false);

        if ($hasTable && ! $phpWordHasAnchor) {
            $diagnostics[] = $this->diagnostic(
                'error',
                'phpword_missing_basic_terms_anchor',
                "CRM видит «{$anchor}» в списке переменных, но PhpWord не находит цельный макрос для cloneRow. После деплоя с авто-склейкой должно заработать; иначе пересохраните макрос одной строкой без форматирования внутри.",
            );
        } elseif ($phpWordHasAnchor) {
            $diagnostics[] = $this->diagnostic(
                'ok',
                'phpword_basic_terms_anchor_ready',
                "PhpWord готов к cloneRow по «{$anchor}».",
            );
        }

        if ($globalCount === 0) {
            $diagnostics[] = $this->diagnostic(
                'warning',
                'no_global_basic_terms',
                'В настройках «Шаблоны → Базовые условия» нет общих пунктов для '.$this->partyLabel($party).'.',
            );
        } else {
            $diagnostics[] = $this->diagnostic(
                'ok',
                'global_basic_terms_present',
                "В настройках сохранено {$globalCount} общих пунктов для ".$this->partyLabel($party).'.',
            );
        }

        $wrongPrefixMacros = collect($macros)
            ->filter(function (string $macro) use ($expectedPrefix): bool {
                $prefix = str_starts_with(strtolower($macro), 'cp_') ? 'cp' : (str_starts_with(strtolower($macro), 'dp_') ? 'dp' : '');

                return $prefix !== '' && $prefix !== $expectedPrefix;
            })
            ->values()
            ->all();

        if ($wrongPrefixMacros !== []) {
            $diagnostics[] = $this->diagnostic(
                'warning',
                'wrong_basic_terms_prefix',
                'В шаблоне есть макросы другой стороны: '.implode(', ', $wrongPrefixMacros).". Для «{$template['party_label']}» нужен префикс {$expectedPrefix}_.",
            );
        }

        if ($phpWordHasAnchor && $globalCount > 0 && ($template['has_source_file'] ?? false)) {
            $diagnostics[] = $this->diagnostic(
                'ok',
                'should_render',
                'При генерации черновика пункты должны подставиться (если в заказе нет пустого переопределения).',
            );
        }

        return $diagnostics;
    }

    /**
     * @return array{level: string, code: string, message: string}
     */
    private function diagnostic(string $level, string $code, string $message): array
    {
        return [
            'level' => $level,
            'code' => $code,
            'message' => $message,
        ];
    }

    private function partyLabel(string $party): string
    {
        return match ($party) {
            PrintFormBasicTerm::PARTY_CUSTOMER => 'заказчика',
            PrintFormBasicTerm::PARTY_CARRIER => 'перевозчика',
            'internal' => 'внутренней формы',
            default => $party !== '' ? $party : 'не указана',
        };
    }

    private function expectedBasicTermsPrefix(string $party): string
    {
        return PrintFormBasicTerm::placeholderPrefixForParty($party) ?? '';
    }

    private function normalizeOptional(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<string>  $items
     * @return array{party: string, contractor_id: int|null, saved_count: int}
     */
    public function upsertBasicTerms(string $party, ?int $contractorId, array $items): array
    {
        if (! in_array($party, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
            throw new \InvalidArgumentException('Недопустимая сторона для базовых условий.');
        }

        $normalized = collect($items)
            ->map(fn (mixed $body): string => trim((string) $body))
            ->filter(fn (string $body): bool => $body !== '')
            ->values()
            ->all();

        $this->basicTermsService->sync($party, $contractorId, $normalized);

        return [
            'party' => $party,
            'contractor_id' => $contractorId,
            'saved_count' => count($normalized),
        ];
    }

    /**
     * @return array{
     *     tables_ready: bool,
     *     scope: string,
     *     contractor_id: int|null,
     *     hint: string,
     *     parties: array<string, array{party: string, party_label: string, count: int, items: list<array{sort_order: int, body: string}>}>
     * }
     */
    public function readBasicTerms(?string $party = null, ?int $contractorId = null): array
    {
        if (! $this->basicTermsService->tablesReady()) {
            return [
                'tables_ready' => false,
                'scope' => $contractorId !== null ? 'contractor' : 'global',
                'contractor_id' => $contractorId,
                'hint' => 'Таблица базовых условий недоступна — выполните миграции.',
                'parties' => [],
            ];
        }

        $parties = $party !== null
            ? [$party]
            : [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER];

        $payload = [];

        foreach ($parties as $partyKey) {
            if (! in_array($partyKey, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
                continue;
            }

            $side = $partyKey === PrintFormBasicTerm::PARTY_CARRIER ? 'carrier' : 'customer';
            $rows = $this->basicTermsService->listRows($partyKey, $contractorId);
            $payload[$side] = [
                'party' => $partyKey,
                'party_label' => $partyKey === PrintFormBasicTerm::PARTY_CARRIER ? 'Перевозчик' : 'Заказчик',
                'count' => count($rows),
                'items' => collect($rows)
                    ->map(fn (array $row): array => [
                        'sort_order' => (int) ($row['sort_order'] ?? 0),
                        'body' => (string) ($row['body'] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return [
            'tables_ready' => true,
            'scope' => $contractorId !== null ? 'contractor' : 'global',
            'contractor_id' => $contractorId,
            'hint' => 'Для зеркалирования норм: прочитайте carrier, составьте аналоги для customer, сохраните upsert_print_form_basic_terms. Каждый пункт — отдельная строка; если в печати нужна точка в начале — включите «. » в текст пункта.',
            'parties' => $payload,
        ];
    }
}
