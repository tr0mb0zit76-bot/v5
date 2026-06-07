<?php

namespace App\Support;

use App\Models\PrintFormBasicTerm;
use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Динамические строки базовых условий в DOCX (PhpWord cloneRow).
 *
 * Заказчик: ${cp_basic_terms_row_index}, ${cp_basic_terms_row_text} — якорь cp_basic_terms_row_text.
 * Перевозчик: ${dp_basic_terms_row_index}, ${dp_basic_terms_row_text} — якорь dp_basic_terms_row_text.
 */
final class PrintFormBasicTermsTableCloner
{
    private function __construct(
        private readonly string $prefix,
    ) {}

    public static function forParty(string $party): ?self
    {
        $prefix = PrintFormBasicTerm::placeholderPrefixForParty($party);

        return $prefix !== null ? new self($prefix) : null;
    }

    /**
     * @return list<string>
     */
    public static function placeholderHelpForParty(string $party): array
    {
        $prefix = PrintFormBasicTerm::placeholderPrefixForParty($party);

        if ($prefix === null) {
            return [];
        }

        return [
            'prefix' => $prefix,
            'anchor' => $prefix.'_basic_terms_row_text',
            'macros' => [
                $prefix.'_basic_terms_row_index',
                $prefix.'_basic_terms_row_text',
            ],
        ];
    }

    public function cloneRowAnchor(): string
    {
        return $this->prefix.'_basic_terms_row_text';
    }

    /**
     * @return list<string>
     */
    public function rowMacroNames(): array
    {
        return [
            $this->prefix.'_basic_terms_row_index',
            $this->prefix.'_basic_terms_row_text',
        ];
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public function apply(TemplateProcessor $processor, array $rows): void
    {
        if (! $this->templateHasTermsTable($processor)) {
            return;
        }

        if ($rows === []) {
            $this->removeTemplateRow($processor);

            return;
        }

        $prepared = array_map(
            fn (array $row): array => $this->prepareRowValues($processor, $row),
            $rows,
        );

        $anchor = PrintFormTemplateProcessorPreparer::resolveProcessorMacro($processor, $this->cloneRowAnchor())
            ?? $this->cloneRowAnchor();

        $processor->cloneRowAndSetValues($anchor, $prepared);
    }

    public function templateHasTermsTable(TemplateProcessor $processor): bool
    {
        return PrintFormTemplateProcessorPreparer::processorHasMacro($processor, $this->cloneRowAnchor());
    }

    public static function isBasicTermsPlaceholder(string $placeholder): bool
    {
        $trimmed = trim($placeholder);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^(cp|dp)_basic_terms_row_/i', $trimmed)) {
            return true;
        }

        return (bool) preg_match('/^(cp|dp)_basic_terms_row_[a-z0-9_]+#\d+$/i', $trimmed);
    }

    /**
     * @param  iterable<mixed>  $placeholders
     * @return list<string>
     */
    public static function partiesFromPlaceholders(iterable $placeholders): array
    {
        $parties = [];

        foreach ($placeholders as $placeholder) {
            if (! is_string($placeholder)) {
                continue;
            }

            if (preg_match('/^cp_basic_terms_row_/i', $placeholder)) {
                $parties[] = PrintFormBasicTerm::PARTY_CUSTOMER;
            }

            if (preg_match('/^dp_basic_terms_row_/i', $placeholder)) {
                $parties[] = PrintFormBasicTerm::PARTY_CARRIER;
            }
        }

        return array_values(array_unique($parties));
    }

    private function removeTemplateRow(TemplateProcessor $processor): void
    {
        try {
            $anchor = PrintFormTemplateProcessorPreparer::resolveProcessorMacro($processor, $this->cloneRowAnchor())
                ?? $this->cloneRowAnchor();
            $processor->deleteRow($anchor);
        } catch (PhpWordException) {
            // Строка уже удалена или разметка Word не позволяет.
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function prepareRowValues(TemplateProcessor $processor, array $row): array
    {
        $out = [];

        foreach ($this->rowMacroNames() as $macro) {
            $value = trim((string) ($row[$macro] ?? ''));

            if ($value !== '' && str_ends_with($macro, '_text')) {
                $value = $processor->replaceCarriageReturns($value);
            }

            $out[$macro] = $value;
        }

        return $out;
    }
}
