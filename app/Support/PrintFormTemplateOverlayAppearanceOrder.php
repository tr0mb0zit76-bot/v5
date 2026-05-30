<?php

namespace App\Support;

use App\Models\PrintFormTemplate;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Порядок вставки PhpWord совпадает с порядком плейсхолдеров в шаблоне (сверху вниз по частям документа),
 * а buildOverlayFloatingStyles раньше всегда отдавал [подпись, печать] — при «печать выше подписи» смещения путались.
 */
final class PrintFormTemplateOverlayAppearanceOrder
{
    /**
     * @return list{'internal_signature'|'internal_stamp'}
     */
    public static function imageOverlayKeysInReadingOrder(PrintFormTemplate $template): array
    {
        $default = ['internal_signature', 'internal_stamp'];
        $path = (string) $template->file_path;
        if ($path === '') {
            return $default;
        }

        $disk = (string) ($template->file_disk ?: 'local');
        if (! Storage::disk($disk)->exists($path)) {
            return $default;
        }

        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        $sigName = PrintFormImageOverlayPlaceholders::placeholderNameForKey(
            PrintFormImageOverlayPlaceholders::KEY_SIGNATURE,
            $overlays,
        );
        $stampName = PrintFormImageOverlayPlaceholders::placeholderNameForKey(
            PrintFormImageOverlayPlaceholders::KEY_STAMP,
            $overlays,
        );
        if ($sigName === '' || $stampName === '') {
            return $default;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'crm-tpl-overlay-order-');
        if ($tmp === false) {
            return $default;
        }

        try {
            $bytes = Storage::disk($disk)->get($path);
            if (! is_string($bytes) || $bytes === '') {
                return $default;
            }

            file_put_contents($tmp, $bytes);

            $zip = new ZipArchive;
            $openRead = self::zipOpenFlagsReadOnly();
            if ($zip->open($tmp, $openRead) !== true) {
                return $default;
            }

            $partNames = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (is_string($n) && self::isWordprocessingPartPath($n)) {
                    $partNames[] = $n;
                }
            }
            $partNames = array_values(array_unique($partNames));
            usort($partNames, [self::class, 'compareWordprocessingPartPath']);

            $combined = '';
            foreach ($partNames as $part) {
                $xml = $zip->getFromName($part);
                if (is_string($xml) && $xml !== '') {
                    $combined .= "\n".$xml;
                }
            }
            $zip->close();

            $sigPos = self::firstPlaceholderOffsetAmong($combined, [$sigName]);
            $stampPos = self::firstPlaceholderOffsetAmong($combined, [$stampName]);
            if ($sigPos === null || $stampPos === null) {
                return $default;
            }

            return $sigPos <= $stampPos ? $default : ['internal_stamp', 'internal_signature'];
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private static function zipOpenFlagsReadOnly(): int
    {
        if (defined('ZipArchive::RDONLY')) {
            return (int) ZipArchive::RDONLY;
        }

        return 0;
    }

    private static function isWordprocessingPartPath(string $name): bool
    {
        if ($name === 'word/document.xml') {
            return true;
        }

        return (bool) preg_match('#^word/header[0-9]+\\.xml$#', $name)
            || (bool) preg_match('#^word/footer[0-9]+\\.xml$#', $name);
    }

    private static function compareWordprocessingPartPath(string $a, string $b): int
    {
        $rank = static function (string $p): array {
            if (str_starts_with($p, 'word/header')) {
                preg_match('/header(\d+)\.xml$/', $p, $m);

                return [0, (int) ($m[1] ?? 0)];
            }
            if ($p === 'word/document.xml') {
                return [1, 0];
            }
            if (str_starts_with($p, 'word/footer')) {
                preg_match('/footer(\d+)\.xml$/', $p, $m);

                return [2, (int) ($m[1] ?? 0)];
            }

            return [9, 0];
        };

        return $rank($a) <=> $rank($b);
    }

    /**
     * @param  list<string>  $placeholderNames
     */
    private static function firstPlaceholderOffsetAmong(string $xml, array $placeholderNames): ?int
    {
        $best = null;

        foreach ($placeholderNames as $placeholderName) {
            foreach (['${'.$placeholderName.'}', '{{'.$placeholderName.'}}'] as $token) {
                $p = strpos($xml, $token);
                if ($p !== false && ($best === null || $p < $best)) {
                    $best = $p;
                }
            }
        }

        return $best;
    }
}
