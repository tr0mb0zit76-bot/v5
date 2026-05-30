<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicSlaDocumentController extends Controller
{
    public function show(string $document): BinaryFileResponse
    {
        /** @var array<string, array{panel?: string, label?: string, public_path?: string}> $catalog */
        $catalog = config('showcase.sla_documents', []);

        if (! isset($catalog[$document])) {
            abort(404);
        }

        $publicRelative = (string) ($catalog[$document]['public_path'] ?? '');
        if ($publicRelative === '') {
            abort(404);
        }

        $absolute = public_path($publicRelative);
        if (! is_file($absolute)) {
            abort(404);
        }

        $filename = basename($publicRelative);

        return response()->file($absolute, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
