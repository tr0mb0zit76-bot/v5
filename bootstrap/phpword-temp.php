<?php

declare(strict_types=1);
use PhpOffice\PhpWord\Settings;

/**
 * PhpWord вызывает tempnam(Settings::getTempDir(), …). По умолчанию это /tmp — при open_basedir без /tmp падает.
 * Выполняется сразу после vendor/autoload.php (см. public/index.php и artisan), до bootstrap Laravel.
 *
 * @param  non-falsy-string  $basePath  Корень проекта (каталог с artisan)
 */
function configure_phpword_temp_dir(string $basePath): void
{
    $candidates = [
        $basePath.'/storage/framework/phpword-tmp',
        $basePath.'/storage/app/tmp',
    ];

    $dir = null;
    foreach ($candidates as $candidate) {
        if (! is_dir($candidate)) {
            @mkdir($candidate, 0775, true);
        }
        if (is_dir($candidate) && is_writable($candidate)) {
            $dir = $candidate;

            break;
        }
    }

    if ($dir === null) {
        return;
    }

    putenv('TMPDIR='.$dir);

    if (class_exists(Settings::class)) {
        Settings::setTempDir($dir);
    }
}
