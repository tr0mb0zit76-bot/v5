<?php

namespace App\Http\Controllers;

use App\Models\ChatMessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessengerAttachmentController extends Controller
{
    public function show(Request $request, ChatMessageAttachment $attachment): StreamedResponse
    {
        $storage = Storage::disk($attachment->disk);
        abort_unless($storage->exists($attachment->path), 404);

        $disposition = $request->boolean('download') || ! $attachment->isImage()
            ? 'attachment'
            : 'inline';

        return $storage->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
            $disposition,
        );
    }
}
