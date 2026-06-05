<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\User;
use Closure;

final class ContractorDuplicateGuard
{
    public static function findByInn(?string $inn, ?int $ignoreId = null): ?Contractor
    {
        $normalized = ContractorIdentity::normalizeInn($inn);

        if ($normalized === null) {
            return null;
        }

        $query = Contractor::query()->where('inn', $normalized);

        if ($ignoreId !== null && $ignoreId > 0) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->first();
    }

    public static function findByName(?string $name, ?int $ignoreId = null): ?Contractor
    {
        $normalized = ContractorIdentity::normalizeName($name);

        if ($normalized === '') {
            return null;
        }

        $query = Contractor::query()->where('name', $normalized);

        if ($ignoreId !== null && $ignoreId > 0) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->first();
    }

    public static function isVisibleTo(?User $user, Contractor $contractor): bool
    {
        if ($user === null) {
            return false;
        }

        return Contractor::query()
            ->visibleTo($user)
            ->whereKey($contractor->id)
            ->exists();
    }

    public static function message(Contractor $existing, ?User $user, string $matchedBy = 'inn'): string
    {
        $visible = self::isVisibleTo($user, $existing);
        $innLabel = $existing->inn !== null && $existing->inn !== '' ? $existing->inn : '—';
        $matchedLabel = $matchedBy === 'name' ? 'названием' : 'ИНН';

        if ($visible) {
            return sprintf(
                'Контрагент с таким %s уже есть в вашем реестре: «%s» (ИНН %s). Откройте существующую карточку вместо создания новой.',
                $matchedLabel,
                $existing->name,
                $innLabel,
            );
        }

        if ($existing->owner_id === null) {
            return sprintf(
                'Контрагент «%s» (ИНН %s) уже есть в базе, но без назначенного владельца — поэтому не отображается в вашем реестре. Обратитесь к администратору, чтобы назначить владельца или открыть карточку.',
                $existing->name,
                $innLabel,
            );
        }

        return sprintf(
            'Контрагент «%s» (ИНН %s) уже есть в базе и закреплён за другим пользователем — поэтому не отображается в вашем реестре.',
            $existing->name,
            $innLabel,
        );
    }

    /**
     * @return array{
     *     duplicate: bool,
     *     matched_by: string|null,
     *     message: string|null,
     *     contractor_id: int|null,
     *     contractor_name: string|null,
     *     can_open: bool,
     *     open_url: string|null
     * }
     */
    public static function checkPayload(?string $inn, ?string $name, ?User $user, ?int $ignoreId = null): array
    {
        $existing = self::findByInn($inn, $ignoreId);

        if ($existing === null) {
            $existing = self::findByName($name, $ignoreId);
            $matchedBy = $existing !== null ? 'name' : null;
        } else {
            $matchedBy = 'inn';
        }

        if ($existing === null) {
            return [
                'duplicate' => false,
                'matched_by' => null,
                'message' => null,
                'contractor_id' => null,
                'contractor_name' => null,
                'can_open' => false,
                'open_url' => null,
            ];
        }

        $canOpen = self::isVisibleTo($user, $existing);

        return [
            'duplicate' => true,
            'matched_by' => $matchedBy,
            'message' => self::message($existing, $user, $matchedBy ?? 'inn'),
            'contractor_id' => $canOpen ? $existing->id : null,
            'contractor_name' => $existing->name,
            'can_open' => $canOpen,
            'open_url' => $canOpen ? route('contractors.show', $existing) : null,
        ];
    }

    public static function failIfInnTaken(mixed $inn, ?User $user, ?int $ignoreId, Closure $fail): void
    {
        $existing = self::findByInn(is_string($inn) || is_numeric($inn) ? (string) $inn : null, $ignoreId);

        if ($existing !== null) {
            $fail(self::message($existing, $user, 'inn'));
        }
    }

    public static function failIfNameTaken(mixed $name, ?User $user, ?int $ignoreId, Closure $fail): void
    {
        $existing = self::findByName(is_string($name) ? $name : null, $ignoreId);

        if ($existing !== null) {
            $fail(self::message($existing, $user, 'name'));
        }
    }
}
