<?php

namespace App\Support;

use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Динамические строки таблицы грузов в DOCX (PhpWord cloneRow).
 *
 * В шаблоне — одна строка таблицы с плейсхолдерами ${cargo_row_…}; якорь клонирования — cargo_row_name.
 */
final class PrintFormCargoTableCloner
{
    public const CLONE_ROW_ANCHOR = 'cargo_row_name';

    /** @var list<string> */
    public const ROW_MACRO_NAMES = [
        'cargo_row_index',
        'cargo_row_name',
        'cargo_row_summary',
        'cargo_row_text',
        'cargo_row_weight',
        'cargo_row_volume',
        'cargo_row_packages',
        'cargo_row_packages_label',
        'cargo_row_pack_type',
        'cargo_row_hs_code',
        'cargo_row_dimensions',
    ];

    /** @var list<string> */
    private const MULTILINE_MACROS = [
        'cargo_row_text',
    ];

    /**
     * @param  list<array<string, string>>  $rows
     */
    public function apply(TemplateProcessor $processor, array $rows): void
    {
        if (! $this->templateHasCargoTable($processor)) {
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

        $processor->cloneRowAndSetValues(
            PrintFormTemplateProcessorPreparer::resolveProcessorMacro($processor, self::CLONE_ROW_ANCHOR) ?? self::CLONE_ROW_ANCHOR,
            $prepared,
        );
    }

    public function templateHasCargoTable(TemplateProcessor $processor): bool
    {
        return PrintFormTemplateProcessorPreparer::processorHasMacro($processor, self::CLONE_ROW_ANCHOR);
    }

    public static function isCargoTablePlaceholder(string $placeholder): bool
    {
        $trimmed = trim($placeholder);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, 'cargo_row_')) {
            return true;
        }

        return (bool) preg_match('/^cargo_row_[a-z0-9_]+#\d+$/i', $trimmed);
    }

    private function removeTemplateRow(TemplateProcessor $processor): void
    {
        try {
            $anchor = PrintFormTemplateProcessorPreparer::resolveProcessorMacro($processor, self::CLONE_ROW_ANCHOR)
                ?? self::CLONE_ROW_ANCHOR;
            $processor->deleteRow($anchor);
        } catch (PhpWordException) {
            // Строка уже удалена или разметка Word не позволяет — оставляем пустой шаблон.
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function prepareRowValues(TemplateProcessor $processor, array $row): array
    {
        $out = [];

        foreach (self::ROW_MACRO_NAMES as $macro) {
            $value = trim((string) ($row[$macro] ?? ''));

            if ($value !== '' && in_array($macro, self::MULTILINE_MACROS, true)) {
                $value = $processor->replaceCarriageReturns($value);
            }

            $out[$macro] = $value;
        }

        return $out;
    }
}
