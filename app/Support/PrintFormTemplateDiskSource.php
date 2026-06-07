<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * PhpWord TemplateProcessor копирует исходный DOCX через PHP copy().
 * Нужен реальный существующий локальный путь: учитываем legacy-путь до смены root диска «local» на app/private
 * и шаблоны на адаптерах без корректного path() у локального адаптера Flysystem.
 */
final class PrintFormTemplateDiskSource
{
    /**
     * @return array{path: string, tempFiles: list<string>}
     */
    public static function prepareLocalPathForPhpWord(string $disk, string $relativePath): array
    {
        $relativePath = ltrim((string) $relativePath, '/');
        if ($relativePath === '') {
            throw new \RuntimeException('У шаблона печатной формы не задан file_path.');
        }

        $filesystem = Storage::disk($disk);

        try {
            $candidate = $filesystem->path($relativePath);
        } catch (\Throwable) {
            $candidate = '';
        }

        if ($candidate !== '' && is_file($candidate)) {
            return ['path' => $candidate, 'tempFiles' => []];
        }

        if ($disk === 'local') {
            $legacy = storage_path('app/'.$relativePath);
            if (is_file($legacy)) {
                return ['path' => $legacy, 'tempFiles' => []];
            }
        }

        if (! $filesystem->exists($relativePath)) {
            throw new \RuntimeException(sprintf(
                'Файл шаблона печатной формы не найден на диске «%s»: %s',
                $disk,
                $relativePath
            ));
        }

        $contents = $filesystem->get($relativePath);
        $tmpBase = tempnam(sys_get_temp_dir(), 'crm-print-tpl-');
        if ($tmpBase === false) {
            throw new \RuntimeException('Не удалось создать временный файл для шаблона.');
        }

        @unlink($tmpBase);
        $ext = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'docx';
        }

        $absolute = $tmpBase.'.'.$ext;
        if (file_put_contents($absolute, $contents) === false) {
            throw new \RuntimeException('Не удалось записать временный файл шаблона.');
        }

        return ['path' => $absolute, 'tempFiles' => [$absolute]];
    }

    /**
     * Предобработка и PhpWord перезаписывают DOCX — не мутируем файл шаблона на диске.
     *
     * @param  array{path: string, tempFiles: list<string>}  $prep
     * @return array{path: string, tempFiles: list<string>}
     */
    public static function ensureMutableTempCopy(array $prep): array
    {
        if ($prep['tempFiles'] !== []) {
            return $prep;
        }

        $source = $prep['path'];
        if (! is_file($source)) {
            return $prep;
        }

        $tmpBase = tempnam(sys_get_temp_dir(), 'crm-print-tpl-mut-');
        if ($tmpBase === false) {
            throw new \RuntimeException('Не удалось создать временную копию шаблона.');
        }

        @unlink($tmpBase);
        $ext = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'docx';
        }

        $absolute = $tmpBase.'.'.$ext;
        if (! @copy($source, $absolute)) {
            throw new \RuntimeException('Не удалось скопировать шаблон во временный файл.');
        }

        return ['path' => $absolute, 'tempFiles' => [$absolute]];
    }
}
