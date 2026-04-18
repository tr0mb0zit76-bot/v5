<?php

namespace App\Support;

use RuntimeException;

/**
 * Единая точка для tempnam() / временных файлов при open_basedir без /tmp.
 */
final class ApplicationTempDirectory
{
    /**
     * @throws RuntimeException
     */
    public static function path(): string
    {
        $resolved = resolve_application_temp_dir(base_path());

        if ($resolved !== null) {
            return $resolved;
        }

        throw new RuntimeException(
            'Нет доступного каталога для временных файлов в storage/framework/phpword-tmp или storage/app/tmp. '.
            'Проверьте права на запись и open_basedir.'
        );
    }

    /**
     * @throws RuntimeException
     */
    public static function tempFile(string $prefix): string
    {
        $path = tempnam(self::path(), $prefix);

        if ($path === false) {
            throw new RuntimeException('Не удалось создать временный файл.');
        }

        return $path;
    }
}
