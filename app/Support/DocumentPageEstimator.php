<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Грубая оценка числа страниц/листов для расчёта допустимого размера загрузки.
 */
final class DocumentPageEstimator
{
    public static function estimate(UploadedFile $file): int
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return self::fallbackUnknown();
        }

        $ext = strtolower($file->getClientOriginalExtension());

        return match (true) {
            $ext === 'pdf' => self::estimatePdf($path),
            $ext === 'docx' => self::estimateDocx($path),
            in_array($ext, ['xlsx', 'xlsm'], true) => self::estimateXlsxSheets($path),
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) => self::imagePlaceholderPages(),
            default => self::fallbackUnknown(),
        };
    }

    public static function fallbackUnknown(): int
    {
        return max(1, (int) config('documents.fallback_pages_unknown', 12));
    }

    public static function imagePlaceholderPages(): int
    {
        return max(1, (int) config('documents.image_placeholder_pages', 18));
    }

    private static function estimatePdf(string $path): int
    {
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return self::fallbackUnknown();
        }

        $headN = (int) config('documents.pdf_head_scan_bytes', 4 * 1024 * 1024);
        $tailN = (int) config('documents.pdf_tail_scan_bytes', 4 * 1024 * 1024);

        $head = (string) file_get_contents($path, false, null, 0, min($size, $headN));
        $tailStart = max(0, $size - min($size, $tailN));
        $tail = $size > $headN ? (string) file_get_contents($path, false, null, $tailStart, $size - $tailStart) : '';
        $blob = $head.$tail;

        $fromPageObjects = 0;
        if (preg_match_all('/\/Type\s*\/Page\b(?!\w)/', $blob, $m)) {
            $fromPageObjects = count($m[0]);
        }

        $fromCount = 0;
        if (preg_match_all('/\/Type\s*\/Pages\b[\s\S]{0,8000}?\/Count\s+(\d+)/', $blob, $m2)) {
            foreach ($m2[1] as $c) {
                $fromCount = max($fromCount, (int) $c);
            }
        }

        $n = max($fromPageObjects, $fromCount, 1);

        return min($n, (int) config('documents.max_pages_cap', 200));
    }

    private static function estimateDocx(string $path): int
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return self::fallbackUnknown();
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return self::fallbackUnknown();
        }

        $pageBreaks = 0;
        if (preg_match_all('/<w:br[^>]*w:type="page"[^>]*\/>/', $xml, $m)) {
            $pageBreaks += count($m[0]);
        }
        if (preg_match_all('/w:lastRenderedPageBreak/', $xml, $m2)) {
            $pageBreaks += count($m2[0]);
        }

        $pages = max(1, 1 + $pageBreaks);

        return min($pages, (int) config('documents.max_pages_cap', 200));
    }

    private static function estimateXlsxSheets(string $path): int
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return self::fallbackUnknown();
        }

        $xml = $zip->getFromName('xl/workbook.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return self::fallbackUnknown();
        }

        $sheets = 0;
        if (preg_match_all('/<sheet[^>]+>/', $xml, $m)) {
            $sheets = count($m[0]);
        }

        $pages = max(1, $sheets);

        return min($pages, (int) config('documents.max_pages_cap', 200));
    }
}
