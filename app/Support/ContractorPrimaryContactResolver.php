<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\ContractorContact;
use Illuminate\Support\Facades\Schema;

final class ContractorPrimaryContactResolver
{
    /**
     * Основной контакт контрагента для заявок и печати.
     *
     * @return array{full_name: ?string, phone: ?string, email: ?string}
     */
    public static function resolve(?Contractor $contractor): array
    {
        if (! $contractor instanceof Contractor) {
            return [
                'full_name' => null,
                'phone' => null,
                'email' => null,
            ];
        }

        $primary = self::resolvePrimaryContactRow($contractor);

        if ($primary instanceof ContractorContact) {
            $fullName = self::nullableString($primary->full_name);
            $phone = self::nullableString($primary->phone);
            $email = self::nullableString($primary->email);

            return [
                'full_name' => $fullName,
                'phone' => $phone ?? self::nullableString($contractor->contact_person_phone) ?? self::nullableString($contractor->phone),
                'email' => $email ?? self::nullableString($contractor->contact_person_email),
            ];
        }

        return [
            'full_name' => self::nullableString($contractor->contact_person),
            'phone' => self::nullableString($contractor->contact_person_phone) ?? self::nullableString($contractor->phone),
            'email' => self::nullableString($contractor->contact_person_email) ?? self::nullableString($contractor->email),
        ];
    }

    private static function resolvePrimaryContactRow(Contractor $contractor): ?ContractorContact
    {
        if (! Schema::hasTable('contractor_contacts')) {
            return null;
        }

        if ($contractor->relationLoaded('contacts')) {
            $contacts = $contractor->contacts;

            $primary = $contacts->first(static fn (ContractorContact $contact): bool => (bool) $contact->is_primary);

            return $primary ?? $contacts->first();
        }

        return $contractor->contacts()
            ->orderByDesc('is_primary')
            ->orderBy('full_name')
            ->first();
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
