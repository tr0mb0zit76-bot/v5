<?php

namespace App\Support;

use ZipArchive;

/**
 * После PhpWord {@see TemplateProcessor::setImageValue} подпись/печать попадают в VML (type "#_x0000_t75").
 * Дописываем привязку и смещения; для колонтитулов — «не в ячейке» и плотная строка абзаца, чтобы таблица не разъезжалась.
 * Опционально чистим ведущие запятые/пробелы после пустых плейсхолдеров ({@see DocxOrphanSeparatorCleaner}).
 */
final class DocxVmlOverlayStylePatcher
{
    /** Режим открытия ZIP для записи. На PHP 8.3+ есть {@see ZipArchive::RDWR}; иначе 0 (read-write). Не использовать 2 — это ZIP_EXCL и на Windows ломает открытие существующего DOCX. */
    public static function zipOpenFlagsReadWrite(): int
    {
        if (defined('ZipArchive::RDWR')) {
            return (int) ZipArchive::RDWR;
        }

        return 0;
    }

    /**
     * @param  list<array{margin_left_mm: float, margin_top_mm: float}>  $overlayStyles
     * @param  int  $skipOverlayAssignmentCount  VML-картинки до подписи/печати (например QR), не получают их смещения
     */
    public static function patchDocx(
        string $absoluteDocxPath,
        array $overlayStyles,
        bool $cleanOrphanSeparators = true,
        int $skipOverlayAssignmentCount = 0,
    ): void {
        if ($overlayStyles === [] && ! $cleanOrphanSeparators) {
            return;
        }

        $zip = new ZipArchive;
        $stagingPath = null;

        if ($zip->open($absoluteDocxPath, self::zipOpenFlagsReadWrite()) !== true) {
            $stagingPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-docx-vml-'.uniqid('', true).'.docx';
            if (! @copy($absoluteDocxPath, $stagingPath) || $zip->open($stagingPath, self::zipOpenFlagsReadWrite()) !== true) {
                if (is_string($stagingPath) && is_file($stagingPath)) {
                    @unlink($stagingPath);
                }

                return;
            }
        }

        $overlayIdx = 0;
        $remainingOverlaySkips = max(0, $skipOverlayAssignmentCount);

        $partNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && self::isWordprocessingPartPath($name)) {
                $partNames[] = $name;
            }
        }

        $partNames = array_values(array_unique($partNames));
        usort($partNames, [self::class, 'compareWordprocessingPartPath']);

        foreach ($partNames as $name) {
            $xml = $zip->getFromName($name);
            if (! is_string($xml) || $xml === '') {
                continue;
            }

            $originalXml = $xml;
            $xml = DocxHeaderFooterOverlayParagraphCompactor::patch($xml, $name);
            $xml = self::patchWordprocessingMl($xml, $overlayStyles, $overlayIdx, $name, $remainingOverlaySkips);
            if ($cleanOrphanSeparators) {
                $xml = DocxOrphanSeparatorCleaner::cleanWordprocessingMl($xml);
            }

            if ($xml !== $originalXml) {
                $zip->deleteName($name);
                $zip->addFromString($name, $xml);
            }
        }

        $zip->close();

        if (is_string($stagingPath) && is_file($stagingPath)) {
            @copy($stagingPath, $absoluteDocxPath);
            @unlink($stagingPath);
        }
    }

    /**
     * Порядок обработки: колонтитулы сверху → тело → снизу, чтобы индексы подпись/печать чаще совпадали с порядком вставки PhpWord.
     */
    private static function compareWordprocessingPartPath(string $a, string $b): int
    {
        $rank = static function (string $p): array {
            if (str_starts_with($p, 'word/header')) {
                preg_match('/header(\d+)\.xml$/', $p, $m);

                return [0, (int) ($m[1] ?? 0)];
            }
            if ($p === 'word/document.xml') {
                return [1, 0];
            }
            if (str_starts_with($p, 'word/footer')) {
                preg_match('/footer(\d+)\.xml$/', $p, $m);

                return [2, (int) ($m[1] ?? 0)];
            }

            return [9, 0];
        };

        return $rank($a) <=> $rank($b);
    }

    private static function isWordprocessingPartPath(string $name): bool
    {
        if ($name === 'word/document.xml') {
            return true;
        }

        return (bool) preg_match('#^word/header[0-9]+\\.xml$#', $name)
            || (bool) preg_match('#^word/footer[0-9]+\\.xml$#', $name);
    }

    /**
     * @param  list<array{margin_left_mm: float, margin_top_mm: float}>  $overlayStyles
     */
    public static function patchWordprocessingMl(
        string $documentXml,
        array $overlayStyles,
        int &$overlayIdx,
        string $partPath = 'word/document.xml',
        int &$remainingOverlaySkips = 0,
    ): string {
        $overlayCount = count($overlayStyles);
        $isHeaderFooter = str_starts_with($partPath, 'word/header') || str_starts_with($partPath, 'word/footer');

        $updated = preg_replace_callback(
            '/<v:shape([^>]*?)style="([^"]*?)"([^>]*)>/',
            static function (array $matches) use ($overlayStyles, &$overlayIdx, $overlayCount, $isHeaderFooter, &$remainingOverlaySkips): string {
                $fullTag = $matches[0];
                if (! str_contains($fullTag, '#_x0000_t75')) {
                    return $fullTag;
                }

                $skipOverlayAssignment = $remainingOverlaySkips > 0;
                if ($skipOverlayAssignment) {
                    $remainingOverlaySkips--;

                    return $fullTag;
                }

                if (! $isHeaderFooter && $overlayCount === 0) {
                    return $fullTag;
                }

                if (! $isHeaderFooter && $overlayIdx >= $overlayCount) {
                    return $fullTag;
                }

                $before = $matches[1];
                $style = $matches[2];
                $after = $matches[3];

                if ($isHeaderFooter && ! preg_match('/\bo:allowincell=/i', $before.$after)) {
                    $before .= ' o:allowincell="f"';
                }

                $style = preg_replace('/\bmargin-left\s*:\s*[^;"\']+/i', '', $style) ?? $style;
                $style = preg_replace('/\bmargin-top\s*:\s*[^;"\']+/i', '', $style) ?? $style;
                $style = trim((string) preg_replace('/;{2,}/', ';', $style), ';');

                if ($isHeaderFooter) {
                    $style = preg_replace('/\bmso-position-horizontal-relative\s*:\s*page\b/i', 'mso-position-horizontal-relative:margin', $style) ?? $style;
                    $style = preg_replace('/\bmso-position-vertical-relative\s*:\s*page\b/i', 'mso-position-vertical-relative:margin', $style) ?? $style;
                }

                $resolved = $overlayIdx < $overlayCount
                    ? $overlayStyles[$overlayIdx]
                    : ['margin_left_mm' => 0.0, 'margin_top_mm' => 0.0];

                $overlayIdx++;

                if (! str_contains($style, 'position:absolute')) {
                    $style = 'position:absolute;'.$style;
                }

                if (! str_contains($style, 'z-index')) {
                    $style .= ';z-index:251659264';
                }

                if (! str_contains($style, 'mso-wrap-style')) {
                    $style .= ';mso-wrap-style:none';
                }

                if (! str_contains($style, 'mso-position-horizontal-relative')) {
                    $style .= $isHeaderFooter
                        ? ';mso-position-horizontal-relative:margin'
                        : ';mso-position-horizontal-relative:page';
                }

                if (! str_contains($style, 'mso-position-vertical-relative')) {
                    $style .= $isHeaderFooter
                        ? ';mso-position-vertical-relative:margin'
                        : ';mso-position-vertical-relative:page';
                }

                if ($isHeaderFooter && ! str_contains($style, 'mso-behind-text')) {
                    $style .= ';mso-behind-text:yes';
                }

                $leftMm = number_format((float) $resolved['margin_left_mm'], 2, '.', '');
                $topMm = number_format((float) $resolved['margin_top_mm'], 2, '.', '');
                $style .= ';margin-left:'.$leftMm.'mm;margin-top:'.$topMm.'mm';
                $style = trim((string) preg_replace('/;{2,}/', ';', $style), ';');

                return '<v:shape'.$before.'style="'.$style.'"'.$after.'>';
            },
            $documentXml
        );

        return is_string($updated) ? $updated : $documentXml;
    }
}
