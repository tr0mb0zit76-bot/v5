<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DocumentStorageService
{
    public const DRIVER_LOCAL = 'local';

    public const DRIVER_NEXTCLOUD = 'nextcloud';

    private readonly string $configuredDriver;

    public function __construct(
        private readonly NextcloudWebDavStorage $nextcloudStorage,
    ) {
        $driver = (string) config('document_storage.driver', self::DRIVER_LOCAL);
        $this->configuredDriver = in_array($driver, [self::DRIVER_LOCAL, self::DRIVER_NEXTCLOUD], true)
            ? $driver
            : self::DRIVER_LOCAL;
    }

    public function configuredDriver(): string
    {
        return $this->configuredDriver;
    }

    /**
     * Сохраняет загруженный файл в текущий сконфигурированный драйвер (local или Nextcloud).
     * Путь `order_documents/{orderId}/…` совпадает с печатными формами заказа (удобно в Nextcloud).
     *
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}
     */
    public function storeOrderUpload(UploadedFile $file, ?int $orderId = null): array
    {
        $originalName = $file->getClientOriginalName();
        $directory = $orderId !== null
            ? 'order_documents/'.$orderId
            : 'order_documents/misc';
        $path = $this->resolveUniquePathInDirectory($directory, $originalName);
        $contents = $file->get();
        $driver = $this->configuredDriver();
        $this->put($path, $contents, $driver);
        $size = $file->getSize();
        if ($size === false) {
            $size = strlen($contents);
        }

        return [
            'original_name' => $originalName,
            'file_path' => $path,
            'file_size' => (int) $size,
            'mime_type' => $file->getMimeType(),
            'storage_driver' => $driver,
        ];
    }

    /**
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}
     */
    /**
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}
     */
    /**
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}
     */
    public function storeMailInboundAttachment(string $contents, string $originalName, int $mailboxUserId, int $messageId): array
    {
        $directory = 'mail_inbound/'.$mailboxUserId.'/'.$messageId;
        $path = $this->resolveUniquePathInDirectory($directory, $originalName);
        $driver = $this->configuredDriver();
        $this->put($path, $contents, $driver);

        return [
            'original_name' => basename(str_replace('\\', '/', $originalName)),
            'file_path' => $path,
            'file_size' => strlen($contents),
            'mime_type' => null,
            'storage_driver' => $driver,
        ];
    }

    public function storeMailOutboundUpload(UploadedFile $file, int $senderUserId, ?int $orderId = null): array
    {
        $originalName = $file->getClientOriginalName();
        $directory = $orderId !== null
            ? 'mail_outbound/'.$senderUserId.'/order_'.$orderId
            : 'mail_outbound/'.$senderUserId;
        $path = $this->resolveUniquePathInDirectory($directory, $originalName);
        $contents = $file->get();
        $driver = $this->configuredDriver();
        $this->put($path, $contents, $driver);
        $size = $file->getSize();
        if ($size === false) {
            $size = strlen($contents);
        }

        return [
            'original_name' => $originalName,
            'file_path' => $path,
            'file_size' => (int) $size,
            'mime_type' => $file->getMimeType(),
            'storage_driver' => $driver,
        ];
    }

    public function storeContractorUpload(UploadedFile $file, ?int $contractorId = null): array
    {
        $originalName = $file->getClientOriginalName();
        $directory = $contractorId !== null
            ? 'contractor_documents/'.$contractorId
            : 'contractor_documents/misc';
        $path = $this->resolveUniquePathInDirectory($directory, $originalName);
        $contents = $file->get();
        $driver = $this->configuredDriver();
        $this->put($path, $contents, $driver);
        $size = $file->getSize();
        if ($size === false) {
            $size = strlen($contents);
        }

        return [
            'original_name' => $originalName,
            'file_path' => $path,
            'file_size' => (int) $size,
            'mime_type' => $file->getMimeType(),
            'storage_driver' => $driver,
        ];
    }

    public function put(string $path, string $contents, ?string $driver = null): void
    {
        $driver = $this->resolveDriver($driver);

        if ($driver === self::DRIVER_NEXTCLOUD) {
            $this->nextcloudStorage->put($path, $contents);

            return;
        }

        Storage::disk(self::DRIVER_LOCAL)->put($path, $contents);
    }

    public function get(string $path, ?string $driver = null): string
    {
        $driver = $this->resolveDriver($driver);

        if ($driver === self::DRIVER_NEXTCLOUD) {
            return $this->nextcloudStorage->get($path);
        }

        return (string) Storage::disk(self::DRIVER_LOCAL)->get($path);
    }

    public function delete(?string $path, ?string $driver = null): void
    {
        if (blank($path)) {
            return;
        }

        $driver = $this->resolveDriver($driver);

        try {
            if ($driver === self::DRIVER_NEXTCLOUD) {
                $this->nextcloudStorage->delete((string) $path);

                return;
            }

            Storage::disk(self::DRIVER_LOCAL)->delete((string) $path);
        } catch (Throwable $exception) {
            Log::warning('document_storage.delete_failed', [
                'path' => $path,
                'driver' => $driver,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function size(string $path, ?string $driver = null, ?string $knownContents = null): int
    {
        $driver = $this->resolveDriver($driver);

        if ($driver === self::DRIVER_NEXTCLOUD) {
            if ($knownContents !== null) {
                return strlen($knownContents);
            }

            return strlen($this->nextcloudStorage->get($path));
        }

        return (int) Storage::disk(self::DRIVER_LOCAL)->size($path);
    }

    public function exists(string $path, ?string $driver = null): bool
    {
        $driver = $this->resolveDriver($driver);

        if ($driver === self::DRIVER_NEXTCLOUD) {
            return $this->nextcloudStorage->exists($path);
        }

        return Storage::disk(self::DRIVER_LOCAL)->exists($path);
    }

    /**
     * Путь к файлу заказа с «человеческим» именем (как в Nextcloud), с учётом коллизий в каталоге.
     */
    public function resolveOrderDocumentPath(int $orderId, string $preferredFilename, ?string $driver = null): string
    {
        return $this->resolveUniquePathInDirectory(
            'order_documents/'.$orderId,
            $preferredFilename,
            $driver,
        );
    }

    /**
     * Вставляет суффикс перед расширением: «договор.pdf» + «-signed» → «договор-signed.pdf».
     */
    public function filenameWithVariant(string $filename, string $variant): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        if ($basename === '') {
            $basename = 'document';
        }

        if ($extension !== '') {
            return $basename.$variant.'.'.$extension;
        }

        return $basename.$variant;
    }

    public function sanitizeStorageFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $basename = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename) ?? '';
        $basename = preg_replace('/[\/\\\\:*?"<>|]/u', '-', $basename) ?? '';
        $basename = preg_replace('/-+/', '-', $basename) ?? '';
        $basename = preg_replace('/\s+/u', ' ', $basename) ?? '';
        $basename = trim($basename, " \t\n\r\0\x0B.-");

        while (str_ends_with($basename, '.')) {
            $basename = rtrim($basename, '.');
        }

        if ($basename === '' || $basename === '.' || $basename === '..') {
            $basename = 'document';
        }

        $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        $nameWithoutExtension = trim((string) pathinfo($basename, PATHINFO_FILENAME), " \t\n\r\0\x0B.-");
        $maxBasenameLength = 200;

        if ($extension !== '' && preg_match('/^[a-z0-9]{1,10}$/i', $extension) !== 1) {
            $extension = '';
        }

        if ($nameWithoutExtension === '') {
            $nameWithoutExtension = 'document';
        }

        if ($extension !== '' && mb_strlen($nameWithoutExtension) > $maxBasenameLength) {
            $nameWithoutExtension = mb_substr($nameWithoutExtension, 0, $maxBasenameLength);
        }

        if ($extension !== '') {
            return $nameWithoutExtension.'.'.$extension;
        }

        if (mb_strlen($nameWithoutExtension) > $maxBasenameLength) {
            $nameWithoutExtension = mb_substr($nameWithoutExtension, 0, $maxBasenameLength);
        }

        return $nameWithoutExtension;
    }

    private function resolveUniquePathInDirectory(string $directory, string $preferredFilename, ?string $driver = null): string
    {
        $sanitized = $this->sanitizeStorageFilename($preferredFilename);
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $candidate = $sanitized;
        $attempt = 1;

        while ($this->exists($directory.'/'.$candidate, $driver)) {
            $attempt++;
            $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
            $basename = pathinfo($sanitized, PATHINFO_FILENAME);

            if ($extension !== '') {
                $candidate = sprintf('%s (%d).%s', $basename, $attempt, $extension);
            } else {
                $candidate = sprintf('%s (%d)', $basename, $attempt);
            }

            if ($attempt > 99) {
                $suffix = Str::uuid()->toString();
                $candidate = $extension !== ''
                    ? sprintf('%s-%s.%s', $basename, $suffix, $extension)
                    : sprintf('%s-%s', $basename, $suffix);

                break;
            }
        }

        return $directory.'/'.$candidate;
    }

    private function resolveDriver(?string $driver): string
    {
        $resolved = $driver ?? $this->configuredDriver;

        if ($resolved === self::DRIVER_NEXTCLOUD) {
            return self::DRIVER_NEXTCLOUD;
        }

        return self::DRIVER_LOCAL;
    }
}
