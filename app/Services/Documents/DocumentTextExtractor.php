<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use ZipArchive;

final class DocumentTextExtractor
{
    public function __construct(
        private readonly OcrServiceClient $ocrServiceClient,
    ) {}

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
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) => $this->extractImage($path, $extension),
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

        if ($text !== '') {
            return [
                'text' => $text,
                'method' => 'pdf',
                'warnings' => [],
            ];
        }

        $ocr = $this->tryOcr($path, 'pdf');
        if ($ocr !== null) {
            return $ocr;
        }

        return [
            'text' => '',
            'method' => 'pdf',
            'warnings' => ['В PDF не найден текстовый слой. Включите ORDER_INTAKE_OCR=local и OCR_SERVICE_URL для сканов.'],
        ];
    }

    /**
     * @return array{text: string, method: string, warnings: list<string>}
     */
    private function extractImage(string $path, string $extension): array
    {
        $ocr = $this->tryOcr($path, $extension);
        if ($ocr !== null && trim($ocr['text']) !== '') {
            return $ocr;
        }

        $warnings = $ocr['warnings'] ?? [];
        if ($ocr === null) {
            $warnings[] = 'Скан/фото: включите ORDER_INTAKE_OCR=local и поднимите OCR sidecar (см. docs/order-intake-ocr-service.md).';
        } else {
            $warnings[] = 'OCR не извлёк текст из изображения.';
        }

        return [
            'text' => '',
            'method' => 'image',
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return array{text: string, method: string, warnings: list<string>}|null
     */
    private function tryOcr(string $path, string $extension): ?array
    {
        $ocr = $this->ocrServiceClient->extractFromPath($path, $extension);
        if ($ocr === null) {
            return null;
        }

        return [
            'text' => trim($ocr['text']),
            'method' => (string) ($ocr['method'] ?? 'ocr'),
            'warnings' => is_array($ocr['warnings'] ?? null) ? $ocr['warnings'] : [],
        ];
    }

    private function decodePdfLiteralString(string $value): string
    {
        $value = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $value);

        return trim($value);
    }
}
