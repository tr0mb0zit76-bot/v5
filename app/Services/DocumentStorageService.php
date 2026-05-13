<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     *
     * @return array{original_name: string, file_path: string, file_size: int, mime_type: string|null, storage_driver: string}
     */
    public function storeOrderUpload(UploadedFile $file, string $directory = 'order-documents'): array
    {
        $originalName = $file->getClientOriginalName();
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $safeSuffix = $ext !== '' && preg_match('/^[a-z0-9]{1,10}$/i', $ext) === 1 ? '.'.$ext : '';
        $basename = Str::uuid()->toString().$safeSuffix;
        $path = trim($directory, '/').'/'.$basename;
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

        if ($driver === self::DRIVER_NEXTCLOUD) {
            $this->nextcloudStorage->delete((string) $path);

            return;
        }

        Storage::disk(self::DRIVER_LOCAL)->delete((string) $path);
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

    private function resolveDriver(?string $driver): string
    {
        $resolved = $driver ?? $this->configuredDriver;

        if ($resolved === self::DRIVER_NEXTCLOUD) {
            return self::DRIVER_NEXTCLOUD;
        }

        return self::DRIVER_LOCAL;
    }
}
