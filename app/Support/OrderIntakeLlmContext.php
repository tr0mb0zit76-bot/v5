<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\User;
use App\Services\OrderIntakeLearnedPhrasesService;

final class OrderIntakeLlmContext
{
    public static function wrapUserInstruction(User $user, string $instruction): string
    {
        $learned = app(OrderIntakeLearnedPhrasesService::class)->contextBlockForUser($user);

        $blocks = [
            self::ownCompaniesBlock(),
            self::glossaryBlock(),
            $learned,
            '---',
            'Текст заявки от пользователя:',
            OrderIntakePhraseNormalizer::normalizeInstruction($instruction, $user),
        ];

        return implode("\n", array_filter($blocks, fn (string $line): bool => $line !== ''));
    }

    private static function ownCompaniesBlock(): string
    {
        $companies = Contractor::query()
            ->where('is_own_company', true)
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'inn']);

        if ($companies->isEmpty()) {
            return "Справочник «свои компании» в CRM пуст.\n";
        }

        $lines = ['Справочник «свои компании» в CRM (поле own_company — исполнитель перевозки, НЕ заказчик):'];

        foreach ($companies as $company) {
            $inn = filled($company->inn) ? ' · ИНН '.$company->inn : '';
            $lines[] = '- id='.$company->id.' · '.$company->name.$inn;
        }

        $lines[] = 'Если в тексте «наша компания», «своя компания», «от лица …» — укажи own_company.name из этого списка (ближайшее совпадение).';

        return implode("\n", $lines);
    }

    private static function glossaryBlock(): string
    {
        return <<<'TEXT'
Словарь CRM (нормализуй в JSON):
- «оплата через месяц», «через месяц после выгрузки» → customer_payment_terms: «30 календарных дней» (или «30 календарных дней после выгрузки»).
- «предоплата 50%» — оставь текстом в customer_payment_terms.
- Заказчик перевозки — customer; перевозчик — carrier; «наша/своя компания» — own_company.
TEXT;
    }
}
