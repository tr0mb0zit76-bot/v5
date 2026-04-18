<?php

declare(strict_types=1);

use PhpOffice\PhpWord\Settings;

/**
 * Каталог для tempnam() при open_basedir без /tmp: только внутри проекта (storage).
 *
 * @param  non-falsy-string  $basePath  Корень проекта (каталог с artisan)
 * @return non-falsy-string|null Первый существующий и доступный для записи путь
 */
function resolve_application_temp_dir(string $basePath): ?string
{
    $candidates = [
        $basePath.'/storage/framework/phpword-tmp',
        $basePath.'/storage/app/tmp',
    ];

    foreach ($candidates as $candidate) {
        if (! is_dir($candidate)) {
            @mkdir($candidate, 0775, true);
        }

        if (is_dir($candidate) && is_writable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * PhpWord: tempnam() и Settings::getTempDir(). Вызывать сразу после vendor/autoload (index.php, artisan).
 *
 * @param  non-falsy-string  $basePath  Корень проекта
 */
function configure_phpword_temp_dir(string $basePath): void
{
    $dir = resolve_application_temp_dir($basePath);

    if ($dir === null) {
        return;
    }

    putenv('TMPDIR='.$dir);

    if (class_exists(Settings::class)) {
        Settings::setTempDir($dir);
    }
}
