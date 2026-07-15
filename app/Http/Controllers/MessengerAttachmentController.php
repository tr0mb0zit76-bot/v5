<?php

namespace App\Http\Controllers;

use App\Models\ChatMessageAttachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessengerAttachmentController extends Controller
{
    public function show(Request $request, ChatMessageAttachment $attachment): StreamedResponse
    {
        abort_unless(
            $attachment->message()
                ->whereHas(
                    'conversation.participants',
                    fn (Builder $query) => $query->whereKey($request->user()->getKey()),
                )
                ->exists(),
            403,
        );

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
