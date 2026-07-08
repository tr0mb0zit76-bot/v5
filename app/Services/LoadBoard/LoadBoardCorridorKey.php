<?php

namespace App\Services\LoadBoard;

use App\Models\LoadBoardPost;

class LoadBoardCorridorKey
{
    public static function forPost(LoadBoardPost $post): ?string
    {
        $loading = self::normalizeLocation($post->loading_location);
        $unloading = self::normalizeLocation($post->unloading_location);

        if ($loading === null && $unloading === null) {
            return null;
        }

        $body = strtolower(trim((string) ($post->truck_body_type_code ?: $post->transport_type ?: '')));
        $weightBucket = self::weightBucket($post->cargo_weight);

        return hash('sha256', implode('|', [
            $loading ?? '',
            $unloading ?? '',
            $body,
            $weightBucket,
        ]));
    }

    public static function weightBucket(mixed $weight): string
    {
        if ($weight === null || $weight === '') {
            return 'unknown';
        }

        $numeric = (float) $weight;

        return match (true) {
            $numeric <= 5 => '0-5',
            $numeric <= 10 => '5-10',
            $numeric <= 20 => '10-20',
            default => '20+',
        };
    }

    private static function normalizeLocation(?string $value): ?string
    {
        $trimmed = mb_strtolower(trim((string) $value));

        if ($trimmed === '' || mb_strlen($trimmed) < 2) {
            return null;
        }

        return mb_substr($trimmed, 0, 80);
    }
}
