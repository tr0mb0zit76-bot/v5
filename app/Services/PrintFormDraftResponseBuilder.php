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
    /**
     * @param  array{disk: string, path: string, download_name: string}  $generatedFile
     */
    public function fromGeneratedFile(Request $request, array $generatedFile): Response|BinaryFileResponse
    {
        $absolutePath = Storage::disk($generatedFile['disk'])->path($generatedFile['path']);

        if ($request->boolean('preview')) {
            return response()->file($absolutePath, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'inline; filename="'.$generatedFile['download_name'].'"',
                'Cache-Control' => 'no-store, private',
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

        if ($request->boolean('preview')) {
            return response()->file($absolutePath, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return Storage::disk($disk)->download($path, $downloadName);
    }
}
