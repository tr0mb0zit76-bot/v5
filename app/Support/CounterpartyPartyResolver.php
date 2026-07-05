<?php

namespace App\Support;

use App\Models\Contractor;
use Illuminate\Validation\ValidationException;

final class CounterpartyPartyResolver
{
    /**
     * @throws ValidationException
     */
    public static function resolveForContractor(Contractor $contractor, ?string $requestedParty = null): ExternalParty
    {
        $type = strtolower(trim((string) ($contractor->type ?? '')));

        if ($requestedParty !== null && $requestedParty !== '') {
            $party = ExternalParty::tryFrom($requestedParty);
            if ($party === null) {
                throw ValidationException::withMessages([
                    'external_party' => 'Укажите сторону: carrier или customer.',
                ]);
            }

            self::assertPartyMatchesContractorType($contractor, $party);

            return $party;
        }

        return match ($type) {
            'carrier' => ExternalParty::Carrier,
            'customer' => ExternalParty::Customer,
            default => throw ValidationException::withMessages([
                'external_party' => 'У контрагента тип «'.$type.'». Укажите сторону (перевозчик или заказчик) явно.',
            ]),
        };
    }

    public static function assertPartyMatchesContractorType(Contractor $contractor, ExternalParty $party): void
    {
        $type = strtolower(trim((string) ($contractor->type ?? '')));

        if ($type === 'both' || $type === 'contractor') {
            return;
        }

        if ($type === 'carrier' && $party !== ExternalParty::Carrier) {
            throw ValidationException::withMessages([
                'external_party' => 'Для перевозчика доступна только роль carrier.',
            ]);
        }

        if ($type === 'customer' && $party !== ExternalParty::Customer) {
            throw ValidationException::withMessages([
                'external_party' => 'Для заказчика доступна только роль customer.',
            ]);
        }
    }
}
