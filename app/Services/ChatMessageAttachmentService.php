<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ChatMessageAttachmentService
{
    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, ChatMessageAttachment>
     */
    public function storeForMessage(ChatMessage $message, User $uploader, array $files): Collection
    {
        $disk = 'local';
        $storedPaths = [];

        try {
            return collect($files)->map(function (UploadedFile $file) use (
                $disk,
                $message,
                $uploader,
                &$storedPaths,
            ): ChatMessageAttachment {
                $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
                [$width, $height] = $this->validatedImageDimensions($file, $extension);
                $storedName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
                $directory = sprintf(
                    'chat-attachments/%d/%d',
                    $message->conversation_id,
                    $message->id,
                );
                $path = $file->storeAs($directory, $storedName, $disk);

                if (! is_string($path)) {
                    throw new RuntimeException('Не удалось сохранить вложение сообщения.');
                }

                $storedPaths[] = $path;
                $hash = hash_file('sha256', $file->getRealPath());

                if (! is_string($hash)) {
                    throw new RuntimeException('Не удалось вычислить контрольную сумму вложения.');
                }

                return $message->attachments()->create([
                    'uploaded_by' => $uploader->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'width' => $width,
                    'height' => $height,
                    'sha256' => $hash,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedPaths);

            throw $exception;
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function validatedImageDimensions(UploadedFile $file, string $extension): array
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

        if (! in_array($extension, $imageExtensions, true)) {
            return [null, null];
        }

        $dimensions = @getimagesize($file->getRealPath());

        if (! is_array($dimensions)) {
            $mimeType = strtolower((string) $file->getMimeType());

            if (in_array($extension, ['heic', 'heif'], true)
                && in_array($mimeType, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true)) {
                return [null, null];
            }

            throw ValidationException::withMessages([
                'attachments' => 'Файл изображения повреждён или его содержимое не соответствует расширению.',
            ]);
        }

        return [(int) $dimensions[0], (int) $dimensions[1]];
    }
}
