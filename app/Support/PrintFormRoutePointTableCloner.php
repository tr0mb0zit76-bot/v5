<?php

namespace App\Support;

use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Динамические строки точек маршрута в DOCX (PhpWord cloneRow).
 *
 * В шаблоне — одна строка таблицы с плейсхолдерами ${route_point_row_…}; якорь — route_point_row_address.
 */
final class PrintFormRoutePointTableCloner
{
    public const CLONE_ROW_ANCHOR = 'route_point_row_address';

    /** @var list<string> */
    public const ROW_MACRO_NAMES = [
        'route_point_row_index',
        'route_point_row_stage',
        'route_point_row_type',
        'route_point_row_type_label',
        'route_point_row_city',
        'route_point_row_address',
        'route_point_row_party_name',
        'route_point_row_contact_phone',
        'route_point_row_planned_date',
        'route_point_row_time_range',
        'route_point_row_special_conditions',
        'route_point_row_summary',
    ];

    /** @var list<string> */
    private const MULTILINE_MACROS = [
        'route_point_row_special_conditions',
        'route_point_row_summary',
    ];

    /**
     * @param  list<array<string, string>>  $rows
     */
    public function apply(TemplateProcessor $processor, array $rows): void
    {
        if (! $this->templateHasRoutePointTable($processor)) {
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

    public function templateHasRoutePointTable(TemplateProcessor $processor): bool
    {
        return PrintFormTemplateProcessorPreparer::processorHasMacro($processor, self::CLONE_ROW_ANCHOR);
    }

    public static function isRoutePointTablePlaceholder(string $placeholder): bool
    {
        $trimmed = trim($placeholder);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, 'route_point_row_')) {
            return true;
        }

        return (bool) preg_match('/^route_point_row_[a-z0-9_]+#\d+$/i', $trimmed);
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
