<?php

namespace App\Support;

use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

/**
 * Склеивает плейсхолдеры, разбитые Word на несколько w:r/w:t, до {@see TemplateProcessor}.
 * Без этого PhpWord::fixBrokenMacros() в колонтитулах может повредить XML — DOCX не открывается.
 */
final class DocxPrintFormPlaceholderPreprocessor
{
    /**
     * @param  list<string>  $placeholderNames
     */
    public static function preprocess(string $absoluteDocxPath, array $placeholderNames): void
    {
        if ($placeholderNames === [] || ! is_file($absoluteDocxPath)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($absoluteDocxPath, DocxVmlOverlayStylePatcher::zipOpenFlagsReadWrite()) !== true) {
            return;
        }

        $partNames = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && self::isTargetPartPath($name)) {
                $partNames[] = $name;
            }
        }

        $partNames = array_values(array_unique($partNames));
        if ($partNames === []) {
            $zip->close();

            return;
        }

        $innerNames = self::normalizeInnerNames($placeholderNames);

        foreach ($partNames as $partName) {
            $xml = $zip->getFromName($partName);
            if (! is_string($xml) || $xml === '') {
                continue;
            }

            $updated = DocxTextRunPlaceholderMerger::mergeAllSplitDollarMacrosInXml($xml);
            foreach ($innerNames as $inner) {
                for ($pass = 0; $pass < 32; $pass++) {
                    $before = $updated;
                    $updated = DocxTextRunPlaceholderMerger::mergePlaceholderAcrossAdjacentRuns($updated, '${', '}', $inner);
                    $updated = DocxTextRunPlaceholderMerger::mergePlaceholderAcrossAdjacentRuns($updated, '{{', '}}', $inner);
                    if ($updated === $before) {
                        break;
                    }
                }
            }

            if ($updated !== $xml) {
                $zip->deleteName($partName);
                $zip->addFromString($partName, $updated);
            }
        }

        $zip->close();
    }

    /**
     * @param  list<string>  $placeholderNames
     * @return list<string>
     */
    private static function normalizeInnerNames(array $placeholderNames): array
    {
        return collect($placeholderNames)
            ->filter(static fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(static fn (string $name): string => trim(explode('#', trim($name))[0]))
            ->unique()
            ->values()
            ->all();
    }

    private static function isTargetPartPath(string $name): bool
    {
        if ($name === 'word/document.xml') {
            return true;
        }

        return (bool) preg_match('#^word/document[0-9]+\\.xml$#', $name)
            || (bool) preg_match('#^word/header[0-9]+\\.xml$#', $name)
            || (bool) preg_match('#^word/footer[0-9]+\\.xml$#', $name);
    }
}
