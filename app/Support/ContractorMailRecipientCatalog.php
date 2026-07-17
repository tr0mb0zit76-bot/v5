<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\ContractorContact;
use Illuminate\Support\Facades\Schema;

final class ContractorMailRecipientCatalog
{
    /**
     * Адреса для исходящей почты: контакты с e-mail + запасные поля карточки контрагента.
     *
     * @return list<array{key: string, contact_id: int|null, name: string, email: string, is_primary: bool, source: string}>
     */
    public static function forContractorId(?int $contractorId): array
    {
        if ($contractorId === null || $contractorId < 1 || ! Schema::hasTable('contractors')) {
            return [];
        }

        return self::forContractor(Contractor::query()->find($contractorId));
    }

    /**
     * @return list<array{key: string, contact_id: int|null, name: string, email: string, is_primary: bool, source: string}>
     */
    public static function forContractor(?Contractor $contractor): array
    {
        if (! $contractor instanceof Contractor) {
            return [];
        }

        $seen = [];
        $rows = [];

        if (Schema::hasTable('contractor_contacts') && Schema::hasColumn('contractor_contacts', 'email')) {
            $contacts = $contractor->relationLoaded('contacts')
                ? $contractor->contacts
                : $contractor->contacts()->orderByDesc('is_primary')->orderBy('full_name')->get();

            foreach ($contacts as $contact) {
                if (! $contact instanceof ContractorContact) {
                    continue;
                }

                $email = self::normalizeEmail($contact->email);
                if ($email === null || isset($seen[$email])) {
                    continue;
                }

                $seen[$email] = true;
                $name = trim((string) ($contact->full_name ?: ''));
                $rows[] = [
                    'key' => 'contact:'.(int) $contact->id,
                    'contact_id' => (int) $contact->id,
                    'name' => $name !== '' ? $name : $email,
                    'email' => $email,
                    'is_primary' => (bool) $contact->is_primary,
                    'source' => 'contact',
                ];
            }
        }

        $contactPersonEmail = self::normalizeEmail($contractor->contact_person_email ?? null);
        if ($contactPersonEmail !== null && ! isset($seen[$contactPersonEmail])) {
            $seen[$contactPersonEmail] = true;
            $name = trim((string) ($contractor->contact_person ?? ''));
            $rows[] = [
                'key' => 'contact_person',
                'contact_id' => null,
                'name' => $name !== '' ? $name : 'Контактное лицо',
                'email' => $contactPersonEmail,
                'is_primary' => $rows === [],
                'source' => 'contact_person',
            ];
        }

        $companyEmail = self::normalizeEmail($contractor->email ?? null);
        if ($companyEmail !== null && ! isset($seen[$companyEmail])) {
            $seen[$companyEmail] = true;
            $name = trim((string) ($contractor->name ?? ''));
            $rows[] = [
                'key' => 'company',
                'contact_id' => null,
                'name' => $name !== '' ? $name : 'E-mail компании',
                'email' => $companyEmail,
                'is_primary' => $rows === [],
                'source' => 'company',
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function emailsForContractorId(?int $contractorId): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): string => $row['email'],
            self::forContractorId($contractorId),
        )));
    }

    private static function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
