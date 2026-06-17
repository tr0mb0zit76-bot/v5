<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

final class DocumentUploadBudget
{
    /**
     * Максимально допустимый размер файла в байтах по оценке страниц.
     */
    public static function maxBytes(UploadedFile $file): int
    {
        $pages = DocumentPageEstimator::estimate($file);
        $cap = max(1, (int) config('documents.max_pages_cap', 200));
        $pages = max(1, min($pages, $cap));
        $perPage = max(1024, (int) config('documents.bytes_per_page', 600 * 1024));

        return $pages * $perPage;
    }

    /**
     * Верхний предел по политике (страницы × размер), без учёта php.ini.
     */
    public static function policyMaxBytes(): int
    {
        $cap = max(1, (int) config('documents.max_pages_cap', 200));
        $perPage = max(1024, (int) config('documents.bytes_per_page', 600 * 1024));

        return $cap * $perPage;
    }

    /**
     * Лимит для файла на диске (после оптимизации на sidecar).
     */
    public static function maxBytesForPath(string $path, string $originalName): int
    {
        $pages = DocumentPageEstimator::estimatePath($path, $originalName);
        $cap = max(1, (int) config('documents.max_pages_cap', 200));
        $pages = max(1, min($pages, $cap));
        $perPage = max(1024, (int) config('documents.bytes_per_page', 600 * 1024));

        return $pages * $perPage;
    }

    /**
     * Верхняя граница для правила Laravel max (килобайты), чтобы отсечь заведомо огромные POST до кастомной проверки.
     */
    public static function absoluteMaxKilobytes(): int
    {
        $cap = max(1, (int) config('documents.max_pages_cap', 200));
        $perPage = max(1024, (int) config('documents.bytes_per_page', 600 * 1024));

        return (int) ceil(($cap * $perPage) / 1024);
    }
}
