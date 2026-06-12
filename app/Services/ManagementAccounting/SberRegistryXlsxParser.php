<?php

namespace App\Services\ManagementAccounting;

use RuntimeException;
use ZipArchive;

class SberRegistryXlsxParser
{
    public const BANK_REGISTRY_V1 = 'bank_registry_v1';

    /** @deprecated Use BANK_REGISTRY_V1 */
    public const FORMAT = self::BANK_REGISTRY_V1;

    /**
     * @return array{
     *     account_number: ?string,
     *     period_from: ?string,
     *     period_to: ?string,
     *     lines: list<array{
     *         row_number: int,
     *         operation_date: string,
     *         description: string,
     *         direction: string,
     *         amount: float
     *     }>
     * }
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Файл выписки не найден.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть XLSX-файл.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Лист sheet1 не найден в XLSX.');
        }

        $rows = $this->parseSheetRows($sheetXml, $sharedStrings);
        $accountNumber = $this->detectAccountNumber($rows);
        $period = $this->detectPeriod($rows);

        $lines = [];
        $started = false;

        foreach ($rows as $rowNumber => $cells) {
            $joined = mb_strtolower(implode(' ', array_filter($cells, static fn (string $v): bool => $v !== '')));

            if (! $started) {
                if ($this->isHeaderRow($cells)) {
                    $started = true;
                }

                continue;
            }

            if ($this->isTotalRow($joined)) {
                break;
            }

            $parsed = $this->parseDataRow($rowNumber, $cells);
            if ($parsed !== null) {
                $lines[] = $parsed;
            }
        }

        if ($lines === []) {
            throw new RuntimeException('В выписке не найдено операций. Проверьте формат «Реестр банковских документов».');
        }

        if ($period['from'] === null || $period['to'] === null) {
            foreach ($lines as $line) {
                $date = $line['operation_date'];
                $period['from'] = $period['from'] === null || $date < $period['from'] ? $date : $period['from'];
                $period['to'] = $period['to'] === null || $date > $period['to'] ? $date : $period['to'];
            }
        }

        return [
            'account_number' => $accountNumber,
            'period_from' => $period['from'],
            'period_to' => $period['to'],
            'lines' => $lines,
        ];
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        if ($document === false) {
            return [];
        }

        $strings = [];
        foreach ($document->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;

                continue;
            }

            $parts = [];
            foreach ($item->r as $run) {
                $parts[] = (string) ($run->t ?? '');
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /**
     * @return array<int, list<string>>
     */
    private function parseSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $document = simplexml_load_string($sheetXml);
        if ($document === false) {
            return [];
        }

        $rows = [];
        $sheetData = $document->sheetData ?? null;
        if ($sheetData === null) {
            return [];
        }

        foreach ($sheetData->row as $row) {
            $rowIndex = (int) ($row['r'] ?? 0);
            $cells = [];

            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $col = preg_replace('/\d+/', '', $ref) ?? '';
                $colIndex = $this->columnIndex($col);
                $value = $this->cellValue($cell, $sharedStrings);
                $cells[$colIndex] = trim($value);
            }

            if ($cells !== []) {
                ksort($cells);
                $rows[$rowIndex] = array_values($cells);
            }
        }

        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  \SimpleXMLElement  $cell
     */
    private function cellValue($cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        $value = (string) ($cell->v ?? '');

        if ($type === 's' && $value !== '' && isset($sharedStrings[(int) $value])) {
            return $sharedStrings[(int) $value];
        }

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        return $value;
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $joined = mb_strtolower(implode(' ', $cells));

        return str_contains($joined, 'дата')
            && (str_contains($joined, 'поступление') || str_contains($joined, 'списание'));
    }

    private function isTotalRow(string $joined): bool
    {
        return str_contains($joined, 'итого');
    }

    /**
     * @param  list<string>  $cells
     * @return ?array{
     *     row_number: int,
     *     operation_date: string,
     *     description: string,
     *     direction: string,
     *     amount: float
     * }
     */
    private function parseDataRow(int $rowNumber, array $cells): ?array
    {
        if (count($cells) < 4) {
            return null;
        }

        $dateRaw = $cells[1] ?? '';
        $description = trim((string) ($cells[2] ?? ''));
        $creditRaw = $cells[3] ?? '';
        $debitRaw = $cells[4] ?? '';

        $operationDate = $this->parseDate($dateRaw);
        if ($operationDate === null || $description === '') {
            return null;
        }

        $credit = $this->parseAmount($creditRaw);
        $debit = $this->parseAmount($debitRaw);

        if ($credit > 0 && $debit <= 0) {
            return [
                'row_number' => $rowNumber,
                'operation_date' => $operationDate,
                'description' => $description,
                'direction' => 'in',
                'amount' => $credit,
            ];
        }

        if ($debit > 0 && $credit <= 0) {
            return [
                'row_number' => $rowNumber,
                'operation_date' => $operationDate,
                'description' => $description,
                'direction' => 'out',
                'amount' => $debit,
            ];
        }

        return null;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $value, $matches) === 1) {
            $year = (int) $matches[3];
            if ($year < 100) {
                $year += 2000;
            }

            return sprintf('%04d-%02d-%02d', $year, (int) $matches[2], (int) $matches[1]);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function parseAmount(string $value): float
    {
        $normalized = str_replace([' ', "\xc2\xa0"], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return 0.0;
        }

        return round((float) $normalized, 2);
    }

    /**
     * @param  array<int, list<string>>  $rows
     * @return array{from: ?string, to: ?string}
     */
    private function detectPeriod(array $rows): array
    {
        $from = null;
        $to = null;

        foreach ($rows as $cells) {
            $joined = implode(' ', $cells);

            if (preg_match('/(?:с|от)\s*(\d{1,2}\.\d{1,2}\.\d{2,4})\s*(?:по|до|-)\s*(\d{1,2}\.\d{1,2}\.\d{2,4})/ui', $joined, $matches) === 1) {
                $from = $this->parseDate($matches[1]);
                $to = $this->parseDate($matches[2]);

                break;
            }
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @param  array<int, list<string>>  $rows
     */
    private function detectAccountNumber(array $rows): ?string
    {
        foreach ($rows as $cells) {
            foreach ($cells as $cell) {
                if (preg_match('/\b(407\d{17})\b/', $cell, $matches) === 1) {
                    return $matches[1];
                }
            }
        }

        return null;
    }
}
