<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

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

            return $this->buildInlineDocxFileResponse($absolutePath, $generatedFile['download_name']);
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

            return $this->buildInlineDocxFileResponse($absolutePath, $downloadName);
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

    public function fromStoredPdf(Request $request, string $disk, string $path, string $downloadName): Response|BinaryFileResponse
    {
        $absolutePath = Storage::disk($disk)->path($path);

        if ($this->isBrowserPreviewRequested($request) || $request->boolean('preview')) {
            return response()->file($absolutePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
                'Cache-Control' => 'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
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

            return $this->buildInlineDocxContentResponse($contents, $downloadName);
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

    private function buildInlineDocxFileResponse(string $absolutePath, string $downloadName): BinaryFileResponse
    {
        return response()->file($absolutePath, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildInlineDocxContentResponse(string $contents, string $downloadName): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
