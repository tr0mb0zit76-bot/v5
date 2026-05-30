<?php

namespace App\Support;

use App\Models\PrintFormTemplate;

/**
 * Плейсhолдеры подписи/печати в DOCX (PhpWord setImageValue).
 */
final class PrintFormImageOverlayPlaceholders
{
    public const KEY_SIGNATURE = 'internal_signature';

    public const KEY_STAMP = 'internal_stamp';

    public const DEFAULT_SIGNATURE = 'signature';

    public const DEFAULT_STAMP = 'stamp';

    /**
     * @return list<string>
     */
    public static function overlayKeys(): array
    {
        return [self::KEY_SIGNATURE, self::KEY_STAMP];
    }

    public static function defaultPlaceholder(string $overlayKey): string
    {
        return match ($overlayKey) {
            self::KEY_SIGNATURE => self::DEFAULT_SIGNATURE,
            self::KEY_STAMP => self::DEFAULT_STAMP,
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $overlays
     */
    public static function placeholderNameForKey(string $overlayKey, array $overlays): string
    {
        $configured = trim((string) data_get($overlays, $overlayKey.'.placeholder', self::defaultPlaceholder($overlayKey)));

        return $configured !== '' ? $configured : self::defaultPlaceholder($overlayKey);
    }

    /**
     * @param  array<string, mixed>  $overlays
     * @return list<string>
     */
    public static function allReservedNames(array $overlays): array
    {
        return collect(self::overlayKeys())
            ->map(fn (string $key): string => self::placeholderNameForKey($key, $overlays))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ключи overlay с загруженным файлом, в порядке появления плейсhолдеров в DOCX.
     * Смещения VML привязываются к числу вставленных картинок — лишние ключи без файла не включаем.
     *
     * @return list<string>
     */
    public static function activeOverlayKeysInReadingOrder(PrintFormTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];

        $keys = PrintFormTemplateOverlayAppearanceOrder::imageOverlayKeysInReadingOrder($template);

        return array_values(array_filter(
            $keys,
            static function (string $key) use ($overlays): bool {
                $path = data_get($overlays, $key.'.path');

                return is_string($path) && trim($path) !== '';
            },
        ));
    }
}
