<?php

namespace App\Support;

final class AiInteractionPromptFingerprint
{
    public static function fromText(string $text): ?string
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }

    public static function normalize(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', mb_strtolower(trim($text))) ?? '';

        return mb_substr($collapsed, 0, 4000);
    }
}
