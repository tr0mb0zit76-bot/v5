<?php

namespace App\Services;

use App\Support\ApplicationTempDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Скачивание DOCX или тот же файл в браузере для просмотра (query: preview=1, Content-Disposition: inline).
 */
class PrintFormDraftResponseBuilder
{
    public function __construct(
        private readonly DocxPdfPreviewService $docxPdfPreviewService,
    ) {}

    /**
     * @param  array{disk: string, path: string, download_name: string}  $generatedFile
     */
    public function fromGeneratedFile(Request $request, array $generatedFile): Response|BinaryFileResponse
    {
        $absolutePath = Storage::disk($generatedFile['disk'])->path($generatedFile['path']);
        $docxContents = Storage::disk($generatedFile['disk'])->get($generatedFile['path']);

        if ($this->isBrowserPreviewRequested($request)) {
            $pdfResponse = $this->buildPdfPreviewResponseFromDocx($docxContents, $generatedFile['download_name']);
            if ($pdfResponse !== null) {
                return $pdfResponse;
            }

            return $this->buildBrowserPreviewResponse($absolutePath, $generatedFile['download_name']);
        }

        if ($request->boolean('preview')) {
            return response()->file($absolutePath, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'inline; filename="'.$generatedFile['download_name'].'"',
                'Cache-Control' => 'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }

        return response()->download(
            $absolutePath,
            $generatedFile['download_name']
        );
    }

    public function fromStoredDocx(Request $request, string $disk, string $path, string $downloadName): Response|BinaryFileResponse
    {
        $absolutePath = Storage::disk($disk)->path($path);
        $docxContents = Storage::disk($disk)->get($path);

        if ($this->isBrowserPreviewRequested($request)) {
            $pdfResponse = $this->buildPdfPreviewResponseFromDocx($docxContents, $downloadName);
            if ($pdfResponse !== null) {
                return $pdfResponse;
            }

            return $this->buildBrowserPreviewResponse($absolutePath, $downloadName);
        }

        if ($request->boolean('preview')) {
            return response()->file($absolutePath, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return Storage::disk($disk)->download($path, $downloadName);
    }

    public function fromStoredDocxContent(Request $request, string $contents, string $downloadName): Response
    {
        if ($this->isBrowserPreviewRequested($request)) {
            $pdfResponse = $this->buildPdfPreviewResponseFromDocx($contents, $downloadName);
            if ($pdfResponse !== null) {
                return $pdfResponse;
            }

            try {
                $temporaryPath = ApplicationTempDirectory::tempFile('docx-preview-');
            } catch (\Throwable) {
                return response('Не удалось подготовить предпросмотр документа.', 500);
            }

            file_put_contents($temporaryPath, $contents);

            try {
                return $this->buildBrowserPreviewResponse($temporaryPath, $downloadName);
            } finally {
                @unlink($temporaryPath);
            }
        }

        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $downloadName),
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function isBrowserPreviewRequested(Request $request): bool
    {
        return $request->boolean('preview')
            && strtolower($request->query('preview_mode', '')) === 'browser';
    }

    public function previewPdfFromDocxContent(string $docxContents, string $downloadName): ?string
    {
        return $this->docxPdfPreviewService->convertToPdf($docxContents, $downloadName);
    }

    private function buildPdfPreviewResponseFromDocx(string $docxContents, string $downloadName): ?Response
    {
        $pdf = $this->previewPdfFromDocxContent($docxContents, $downloadName);
        if ($pdf === null) {
            return null;
        }

        $pdfName = preg_replace('/\.docx$/i', '.pdf', $downloadName) ?? 'preview.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdfName.'"',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildBrowserPreviewResponse(string $absolutePath, string $downloadName): Response
    {
        $previewText = $this->extractDocxPreviewText($absolutePath);

        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Предпросмотр документа</title>'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f8fafc;color:#0f172a}'
            .'main{max-width:980px;margin:0 auto;padding:24px}h1{font-size:20px;margin:0 0 8px}p{margin:0 0 16px;color:#475569}'
            .'pre{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;white-space:pre-wrap;word-break:break-word;line-height:1.45}'
            .'</style></head><body><main><h1>Предпросмотр данных</h1>'
            .'<p>Файл: '.e($downloadName).'</p><pre>'.e($previewText).'</pre></main></body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function extractDocxPreviewText(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            return 'Файл не найден.';
        }

        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            return 'Не удалось открыть DOCX-файл.';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return 'В документе не найден текстовый слой.';
        }

        $xml = str_replace(['</w:p>', '</w:tr>', '<w:tab/>'], ["\n", "\n", "\t"], $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : 'Документ успешно сгенерирован, но извлечь текст для предпросмотра не удалось.';
    }
}
