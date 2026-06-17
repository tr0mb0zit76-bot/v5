<?php

namespace App\Support;

/**
 * Лимиты загрузки документов для фронта (предупреждение до отправки формы).
 *
 * @return array{
 *     bytes_per_page: int,
 *     max_pages_cap: int,
 *     fallback_pages_unknown: int,
 *     image_placeholder_pages: int,
 *     policy_max_bytes: int,
 *     absolute_max_bytes: int,
 *     server_upload_max_bytes: int,
 *     pdf_head_scan_bytes: int,
 *     pdf_tail_scan_bytes: int,
 *     estimate_budget_url: string,
 *     hint_ru: string
 * }
 */
final class DocumentUploadLimits
{
    public static function forSharedInertia(): array
    {
        $bpp = max(1024, (int) config('documents.bytes_per_page', 600 * 1024));
        $cap = max(1, (int) config('documents.max_pages_cap', 200));
        $policyAbs = $cap * $bpp;
        $serverHard = min(self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size'));
        // Сломанный php.ini (0, 1 байт и т.п.) не должен обнулять лимит в UI до «0.00 МиБ».
        if ($serverHard < self::minimumTrustedServerUploadBytes()) {
            $serverHard = $policyAbs;
        }
        $effectiveAbs = min($policyAbs, $serverHard);
        $kbPerPage = (int) round($bpp / 1024);

        return [
            'bytes_per_page' => $bpp,
            'max_pages_cap' => $cap,
            'fallback_pages_unknown' => max(1, (int) config('documents.fallback_pages_unknown', 12)),
            'image_placeholder_pages' => max(1, (int) config('documents.image_placeholder_pages', 18)),
            'policy_max_bytes' => $policyAbs,
            'absolute_max_bytes' => $effectiveAbs,
            'server_upload_max_bytes' => $serverHard,
            'pdf_head_scan_bytes' => max(256_000, (int) config('documents.pdf_head_scan_bytes', 4 * 1024 * 1024)),
            'pdf_tail_scan_bytes' => max(256_000, (int) config('documents.pdf_tail_scan_bytes', 4 * 1024 * 1024)),
            'estimate_budget_url' => route('documents.estimate-upload-budget', absolute: false),
            'hint_ru' => self::hintRu($kbPerPage, $cap, $policyAbs, $effectiveAbs),
        ];
    }

    private static function hintRu(int $kbPerPage, int $cap, int $policyAbs, int $effectiveAbs): string
    {
        $policyMib = $policyAbs / 1024 / 1024;
        $effectiveMib = $effectiveAbs / 1024 / 1024;

        if ($effectiveAbs < $policyAbs) {
            return sprintf(
                'По числу страниц: ~%d КиБ на страницу, до %d стр. (политика до ~%.0f МиБ). Сейчас PHP на сервере принимает файлы до ~%.0f МиБ — для сканов нужно поднять upload_max_filesize/post_max_size.',
                $kbPerPage,
                $cap,
                $policyMib,
                $effectiveMib,
            );
        }

        return sprintf(
            'Допустимый размер зависит от числа страниц: примерно %d КиБ на страницу, не более %d страниц (максимум около %.0f МиБ).',
            $kbPerPage,
            $cap,
            $effectiveMib,
        );
    }

    /**
     * Ниже этого порога считаем upload_max_filesize / post_max_size ошибочными для расчёта лимита.
     *
     * @return positive-int
     */
    private static function minimumTrustedServerUploadBytes(): int
    {
        return 512 * 1024;
    }

    /**
     * @return positive-int
     */
    private static function iniBytes(string $directive): int
    {
        $raw = ini_get($directive);
        if ($raw === false || $raw === '') {
            return \PHP_INT_MAX;
        }

        $raw = trim((string) $raw);
        if ($raw === '-1') {
            return \PHP_INT_MAX;
        }

        $unit = strtolower(substr($raw, -1));
        $numeric = $raw;

        if ($unit === 'g' || $unit === 'm' || $unit === 'k') {
            $numeric = substr($raw, 0, -1);
        }

        if (! is_numeric($numeric)) {
            return \PHP_INT_MAX;
        }

        $value = (float) $numeric;
        $mult = match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return max(1, (int) round($value * $mult));
    }
}
