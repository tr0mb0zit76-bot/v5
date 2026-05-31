<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use ZipArchive;

final class DocumentTextExtractor
{
    /**
     * @return array{text: string, method: string, warnings: list<string>}
     */
    public function extractFromUpload(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return [
                'text' => '',
                'method' => 'none',
                'warnings' => ['Не удалось прочитать загруженный файл.'],
            ];
        }

        $extension = strtolower($file->getClientOriginalExtension());

        return match (true) {
            $extension === 'docx' => $this->extractDocx($path),
            $extension === 'pdf' => $this->extractPdf($path),
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) => [
                'text' => '',
                'method' => 'image',
                'warnings' => ['Скан/фото пока не распознаётся автоматически — загрузите PDF или DOCX с текстовым слоем.'],
            ],
            default => [
                'text' => '',
                'method' => 'unsupported',
                'warnings' => ['Формат .'.$extension.' пока не поддерживается. Используйте PDF или DOCX.'],
            ],
        };
    }

    /**
     * @return array{text: string, method: string, warnings: list<string>}
     */
    private function extractDocx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [
                'text' => '',
                'method' => 'docx',
                'warnings' => ['Не удалось открыть DOCX как архив.'],
            ];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return [
                'text' => '',
                'method' => 'docx',
                'warnings' => ['В DOCX не найден word/document.xml.'],
            ];
        }

        $text = html_entity_decode(strip_tags(str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml)));
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return [
            'text' => trim((string) $text),
            'method' => 'docx',
            'warnings' => [],
        ];
    }

    /**
     * @return array{text: string, method: string, warnings: list<string>}
     */
    private function extractPdf(string $path): array
    {
        $content = file_get_contents($path);
        if (! is_string($content) || $content === '') {
            return [
                'text' => '',
                'method' => 'pdf',
                'warnings' => ['PDF пуст или недоступен.'],
            ];
        }

        $parts = [];

        if (preg_match_all('/\((?:\\\\.|[^\\\\\)])*\)\s*Tj/s', $content, $matches) > 0) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\((.*)\)\s*Tj/s', $match, $inner) !== 1) {
                    continue;
                }

                $decoded = $this->decodePdfLiteralString($inner[1]);
                if ($decoded !== '') {
                    $parts[] = $decoded;
                }
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $arrayMatches) > 0) {
            foreach ($arrayMatches[1] as $arrayBody) {
                if (preg_match_all('/\((?:\\\\.|[^\\\\\)])*\)/s', $arrayBody, $innerMatches) > 0) {
                    foreach ($innerMatches[0] as $token) {
                        if (preg_match('/\((.*)\)/s', $token, $inner) !== 1) {
                            continue;
                        }

                        $decoded = $this->decodePdfLiteralString($inner[1]);
                        if ($decoded !== '') {
                            $parts[] = $decoded;
                        }
                    }
                }
            }
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');

        $warnings = [];
        if ($text === '') {
            $warnings[] = 'В PDF не найден текстовый слой. Возможно, это скан — нужен DOCX или OCR (позже).';
        }

        return [
            'text' => $text,
            'method' => 'pdf',
            'warnings' => $warnings,
        ];
    }

    private function decodePdfLiteralString(string $value): string
    {
        $value = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $value);

        return trim($value);
    }
}
