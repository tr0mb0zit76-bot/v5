<?php

namespace App\Support;

use ZipArchive;

/**
 * После PhpWord setImageValue подпись/печать попадают в VML (type "#_x0000_t75").
 * Дописываем привязку к странице и смещения из CRM. Патчим document/header/footer; ZIP открываем на запись.
 */
final class DocxVmlOverlayStylePatcher
{
    /** Режим открытия ZIP для записи (если в сборке нет ZipArchive::RDWR — значение 2). */
    public static function zipOpenFlagsReadWrite(): int
    {
        if (defined('ZipArchive::RDWR')) {
            return (int) ZipArchive::RDWR;
        }

        return 2;
    }

    /**
     * @param  list<array{margin_left_mm: float, margin_top_mm: float}>  $overlayStyles
     */
    public static function patchDocx(string $absoluteDocxPath, array $overlayStyles): void
    {
        if ($overlayStyles === []) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($absoluteDocxPath, self::zipOpenFlagsReadWrite()) !== true) {
            return;
        }

        $overlayIdx = 0;

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

            $patched = self::patchWordprocessingMl($xml, $overlayStyles, $overlayIdx);
            if ($patched !== $xml) {
                $zip->addFromString($name, $patched);
            }
        }

        $zip->close();
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
    public static function patchWordprocessingMl(string $documentXml, array $overlayStyles, int &$overlayIdx): string
    {
        if ($overlayStyles === []) {
            return $documentXml;
        }

        $overlayCount = count($overlayStyles);

        $updated = preg_replace_callback(
            '/<v:shape([^>]*?)style="([^"]*?)"([^>]*)>/',
            static function (array $matches) use ($overlayStyles, &$overlayIdx, $overlayCount): string {
                $fullTag = $matches[0];
                if (! str_contains($fullTag, '#_x0000_t75')) {
                    return $fullTag;
                }

                if ($overlayIdx >= $overlayCount) {
                    return $fullTag;
                }

                $before = $matches[1];
                $style = $matches[2];
                $after = $matches[3];

                $style = preg_replace('/\bmargin-left\s*:\s*[^;"\']+/i', '', $style) ?? $style;
                $style = preg_replace('/\bmargin-top\s*:\s*[^;"\']+/i', '', $style) ?? $style;
                $style = trim((string) preg_replace('/;{2,}/', ';', $style), ';');

                $resolved = $overlayStyles[$overlayIdx];
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
                    $style .= ';mso-position-horizontal-relative:page';
                }

                if (! str_contains($style, 'mso-position-vertical-relative')) {
                    $style .= ';mso-position-vertical-relative:page';
                }

                $leftMm = number_format((float) $resolved['margin_left_mm'], 2, '.', '');
                $topMm = number_format((float) $resolved['margin_top_mm'], 2, '.', '');
                $style .= ';margin-left:'.$leftMm.'mm;margin-top:'.$topMm.'mm';

                return '<v:shape'.$before.'style="'.$style.'"'.$after.'>';
            },
            $documentXml
        );

        return is_string($updated) ? $updated : $documentXml;
    }
}
