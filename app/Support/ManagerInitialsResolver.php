<?php

namespace App\Support;

use App\Models\User;

final class ManagerInitialsResolver
{
    public static function fromUser(?User $user): string
    {
        if ($user === null) {
            return 'XX';
        }

        return self::fromName((string) $user->name);
    }

    public static function fromName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'XX';
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($parts) || $parts === []) {
            return 'XX';
        }

        $dottedParts = array_values(array_filter(
            $parts,
            static fn (string $part): bool => str_contains($part, '.'),
        ));

        if ($dottedParts !== []) {
            $letters = '';

            foreach ($dottedParts as $part) {
                if (preg_match_all('/\p{L}/u', $part, $matches) < 1) {
                    continue;
                }

                $letters .= implode('', $matches[0] ?? []);
            }

            if ($letters !== '') {
                return mb_strtoupper(mb_substr($letters, 0, 2, 'UTF-8'), 'UTF-8');
            }
        }

        if (count($parts) >= 2) {
            $first = self::firstLetter($parts[0]);
            $second = self::firstLetter($parts[1]);

            if ($first !== '' && $second !== '') {
                return mb_strtoupper($first.$second, 'UTF-8');
            }
        }

        $word = $parts[0];
        $letters = self::firstLetter($word).mb_substr($word, 1, 1, 'UTF-8');

        return mb_strtoupper($letters !== '' ? $letters : 'XX', 'UTF-8');
    }

    private static function firstLetter(string $segment): string
    {
        if (preg_match('/\p{L}/u', $segment, $match) !== 1) {
            return '';
        }

        return $match[0];
    }
}
