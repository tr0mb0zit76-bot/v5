<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class InlineStoredFileResponse
{
    public static function disposition(string $mime, string $filename): string
    {
        $asciiName = preg_replace('/[\r\n"]/', '', $filename) ?: 'file';
        $inline = str_starts_with($mime, 'image/')
            || $mime === 'application/pdf';
        $mode = $inline ? 'inline' : 'attachment';

        return sprintf('%s; filename="%s"', $mode, addcslashes($asciiName, '"\\'));
    }

    public static function fromDisk(string $disk, string $path, ?string $mime = null, ?string $originalName = null): Response
    {
        $mimeType = filled($mime) ? (string) $mime : 'application/octet-stream';
        $filename = filled($originalName) ? (string) $originalName : basename($path);

        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=60',
            'Content-Disposition' => self::disposition($mimeType, $filename),
        ]);
    }
}
