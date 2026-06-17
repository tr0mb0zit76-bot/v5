<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;
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

        return self::estimatePath($path, $file->getClientOriginalExtension());
    }

    public static function estimatePath(string $path, string $extension): int
    {
        if (! is_readable($path)) {
            return self::fallbackUnknown();
        }

        $ext = strtolower($extension);

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
        $fromFpdi = self::estimatePdfWithFpdi($path);
        if ($fromFpdi !== null) {
            return min($fromFpdi, (int) config('documents.max_pages_cap', 200));
        }

        $fromPdfinfo = self::estimatePdfWithPdfinfo($path);
        if ($fromPdfinfo !== null) {
            return min($fromPdfinfo, (int) config('documents.max_pages_cap', 200));
        }

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

        return self::estimatePdfFromBlob($blob);
    }

    /**
     * Точный подсчёт страниц через FPDI (чтение xref); при ошибке — null.
     */
    private static function estimatePdfWithFpdi(string $path): ?int
    {
        try {
            $pdf = new Fpdi;
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $count = $pdf->setSourceFile($path);

            return $count > 0 ? $count : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Подсчёт через poppler pdfinfo (если установлен на сервере).
     */
    private static function estimatePdfWithPdfinfo(string $path): ?int
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $pdfinfo = trim((string) shell_exec('command -v pdfinfo 2>/dev/null'));
        if ($pdfinfo === '') {
            return null;
        }

        $output = shell_exec('pdfinfo '.escapeshellarg($path).' 2>/dev/null');
        if (! is_string($output) || $output === '') {
            return null;
        }

        if (preg_match('/^Pages:\s*(\d+)/m', $output, $matches) !== 1) {
            return null;
        }

        $pages = (int) $matches[1];

        return $pages > 0 ? $pages : null;
    }

    /**
     * Запасной подсчёт по фрагменту PDF (синхронизирован с documentUploadClientCheck.js).
     */
    private static function estimatePdfFromBlob(string $blob): int
    {
        $fromPageObjects = 0;
        if (preg_match_all('/\/Type\s*\/Page\b(?!\w)/', $blob, $m)) {
            $fromPageObjects = count($m[0]);
        }

        $fromCount = 0;
        if (preg_match_all('/\/Count\s+(\d+)/', $blob, $m2)) {
            foreach ($m2[1] as $c) {
                $fromCount = max($fromCount, (int) $c);
            }
        }

        $fromMediaBox = 0;
        if (preg_match_all('/\/MediaBox\s*\[/', $blob, $m3)) {
            $fromMediaBox = count($m3[0]);
        }

        $n = max($fromPageObjects, $fromCount, $fromMediaBox, 1);

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
