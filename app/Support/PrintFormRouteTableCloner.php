<?php

namespace App\Support;

use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Динамические строки маршрута по плечам в DOCX (PhpWord cloneRow).
 *
 * В шаблоне — одна строка таблицы с плейсхолдерами ${route_row_…}; якорь — route_row_stage.
 */
final class PrintFormRouteTableCloner
{
    public const CLONE_ROW_ANCHOR = 'route_row_stage';

    /** @var list<string> */
    public const ROW_MACRO_NAMES = [
        'route_row_index',
        'route_row_stage',
        'route_row_loading_addresses',
        'route_row_unloading_addresses',
        'route_row_loading_cities',
        'route_row_unloading_cities',
        'route_row_summary',
    ];

    /** @var list<string> */
    private const MULTILINE_MACROS = [
        'route_row_summary',
        'route_row_loading_addresses',
        'route_row_unloading_addresses',
    ];

    /**
     * @param  list<array<string, string>>  $rows
     */
    public function apply(TemplateProcessor $processor, array $rows): void
    {
        if (! $this->templateHasRouteTable($processor)) {
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

    public function templateHasRouteTable(TemplateProcessor $processor): bool
    {
        return PrintFormTemplateProcessorPreparer::processorHasMacro($processor, self::CLONE_ROW_ANCHOR);
    }

    public static function isRouteTablePlaceholder(string $placeholder): bool
    {
        $trimmed = trim($placeholder);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, 'route_row_')) {
            return true;
        }

        return (bool) preg_match('/^route_row_[a-z0-9_]+#\d+$/i', $trimmed);
    }

    private function removeTemplateRow(TemplateProcessor $processor): void
    {
        try {
            $anchor = PrintFormTemplateProcessorPreparer::resolveProcessorMacro($processor, self::CLONE_ROW_ANCHOR)
                ?? self::CLONE_ROW_ANCHOR;
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
