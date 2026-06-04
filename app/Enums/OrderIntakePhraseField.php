<?php

namespace App\Enums;

enum OrderIntakePhraseField: string
{
    case PaymentTerms = 'payment_terms';
    case OwnCompany = 'own_company';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::PaymentTerms => 'Условия оплаты',
            self::OwnCompany => 'Своя компания',
            self::General => 'Прочее',
        };
    }
}
