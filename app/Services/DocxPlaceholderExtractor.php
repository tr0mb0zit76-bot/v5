<?php

namespace App\Services;

use App\Models\PrintFormTemplate;
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
        $relativePath = ltrim($path, '/');
        $filesystem = Storage::disk($disk);

        try {
            $absolutePath = $filesystem->path($relativePath);
        } catch (\Throwable) {
            $absolutePath = '';
        }

        if ($absolutePath === '' || ! is_file($absolutePath)) {
            if ($disk === 'local') {
                $legacy = storage_path('app/'.$relativePath);
                if (is_file($legacy)) {
                    return $this->extractFromFile($legacy);
                }
            }

            if (! $filesystem->exists($relativePath)) {
                return [];
            }

            $contents = $filesystem->get($relativePath);
            $tmpBase = tempnam(sys_get_temp_dir(), 'crm-docx-ph-');
            if ($tmpBase === false) {
                return [];
            }

            @unlink($tmpBase);
            $ext = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = 'docx';
            }

            $absolutePath = $tmpBase.'.'.$ext;
            try {
                if (file_put_contents($absolutePath, $contents) === false) {
                    return [];
                }

                return $this->extractFromFile($absolutePath);
            } finally {
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        return $this->extractFromFile($absolutePath);
    }

    /**
     * Итоговый список плейсхолдеров шаблона: сохранённый в settings + свежий парс DOCX.
     * Нужен для UI сопоставления — settings могли устареть после правки DOCX или seeder.
     *
     * @return list<string>
     */
    public function placeholdersForTemplate(PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $stored = collect($settings['variables'] ?? [])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        if (! filled($template->file_path) || ! filled($template->file_disk)) {
            return $stored;
        }

        $disk = (string) $template->file_disk;
        $path = (string) $template->file_path;

        if (! Storage::disk($disk)->exists($path)) {
            return $stored;
        }

        return $this->mergePlaceholderLists($stored, $this->extractFromDisk($disk, $path));
    }

    /**
     * @param  list<string>  $stored
     * @param  list<string>  $fromFile
     * @return list<string>
     */
    public function mergePlaceholderLists(array $stored, array $fromFile): array
    {
        return collect($stored)
            ->merge($fromFile)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
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
