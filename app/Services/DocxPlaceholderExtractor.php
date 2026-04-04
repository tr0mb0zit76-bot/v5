<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocxPlaceholderExtractor
{
    /**
     * @return list<string>
     */
    public function extractFromDisk(string $disk, string $path): array
    {
        $absolutePath = Storage::disk($disk)->path($path);

        return $this->extractFromFile($absolutePath);
    }

    /**
     * @return list<string>
     */
    public function extractFromFile(string $absolutePath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            return [];
        }

        $placeholders = collect();

        foreach ($this->candidateXmlFiles($zip) as $xmlFile) {
            $contents = $zip->getFromName($xmlFile);

            if (! is_string($contents) || $contents === '') {
                continue;
            }

            $placeholders = $placeholders->merge($this->extractFromXml($contents));
        }

        $zip->close();

        return $placeholders
            ->filter(fn (string $placeholder): bool => $placeholder !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function candidateXmlFiles(ZipArchive $zip): array
    {
        $files = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            if (
                str_starts_with($name, 'word/header')
                || str_starts_with($name, 'word/footer')
                || in_array($name, [
                    'word/document.xml',
                    'word/footnotes.xml',
                    'word/endnotes.xml',
                ], true)
            ) {
                $files[] = $name;
            }
        }

        return $files;
    }

    /**
     * @return Collection<int, string>
     */
    private function extractFromXml(string $xml): Collection
    {
        $plainText = $this->normalizeXmlToPlainText($xml);

        preg_match_all('/\$\{([^}]+)\}/u', $plainText, $dollarMatches);
        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/u', $plainText, $braceMatches);

        return collect([
            ...($dollarMatches[1] ?? []),
            ...($braceMatches[1] ?? []),
        ])
            ->map(fn (string $value): string => trim($value))
            ->filter();
    }

    private function normalizeXmlToPlainText(string $xml): string
    {
        $withoutTags = preg_replace('/<[^>]+>/u', '', $xml) ?? '';

        return html_entity_decode($withoutTags, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
