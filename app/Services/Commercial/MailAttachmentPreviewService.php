<?php

namespace App\Services\Commercial;

use App\Services\DocxPdfPreviewService;
use Symfony\Component\HttpFoundation\Response;

final class MailAttachmentPreviewService
{
    /** @var list<string> */
    private const OFFICE_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'rtf', 'ppt', 'pptx'];

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function __construct(
        private readonly DocxPdfPreviewService $docxPdfPreview,
    ) {}

    public function isPreviewable(string $filename, ?string $mime = null): bool
    {
        return $this->previewKind($filename, $mime) !== null;
    }

    public function previewKind(string $filename, ?string $mime = null): ?string
    {
        $extension = $this->extension($filename);

        if ($extension === 'pdf' || ($mime !== null && str_contains(strtolower($mime), 'pdf'))) {
            return 'pdf';
        }

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)
            || ($mime !== null && str_starts_with(strtolower($mime), 'image/'))) {
            return 'image';
        }

        if (in_array($extension, self::OFFICE_EXTENSIONS, true) && $this->docxPdfPreview->isEnabled()) {
            return 'office';
        }

        return null;
    }

    public function buildPreviewResponse(string $contents, string $filename, ?string $mime = null): Response
    {
        $kind = $this->previewKind($filename, $mime);

        if ($kind === null) {
            abort(415, 'Предпросмотр для этого типа файла недоступен.');
        }

        if ($kind === 'pdf') {
            return $this->inlinePdfResponse($contents, $filename);
        }

        if ($kind === 'image') {
            return $this->inlineImageResponse($contents, $filename, $mime);
        }

        $pdf = $this->docxPdfPreview->convertOfficeDocumentToPdf($contents, $filename);

        if (! is_string($pdf) || $pdf === '') {
            abort(503, 'Не удалось сформировать PDF-предпросмотр (Gotenberg).');
        }

        $previewName = pathinfo($filename, PATHINFO_FILENAME).'-preview.pdf';

        return $this->inlinePdfResponse($pdf, $previewName);
    }

    private function inlinePdfResponse(string $contents, string $filename): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function inlineImageResponse(string $contents, string $filename, ?string $mime): Response
    {
        $contentType = trim((string) $mime);

        if ($contentType === '' || ! str_starts_with(strtolower($contentType), 'image/')) {
            $contentType = match ($this->extension($filename)) {
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };
        }

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function extension(string $filename): string
    {
        return strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    }
}
