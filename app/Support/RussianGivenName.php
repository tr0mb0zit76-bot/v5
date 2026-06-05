<?php

namespace App\Support;

final class RussianGivenName
{
    /**
     * Имя из строки «Фамилия Имя [Отчество]».
     */
    public static function fromFullName(?string $fullName): ?string
    {
        $trimmed = trim((string) $fullName);

        if ($trimmed === '') {
            return null;
        }

        $parts = preg_split('/\s+/u', $trimmed) ?: [];
        $parts = array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== '',
        ));

        if (count($parts) >= 2) {
            return $parts[1];
        }

        return $parts[0] ?? null;
    }
}
