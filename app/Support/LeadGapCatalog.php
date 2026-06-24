<?php

namespace App\Support;

/**
 * Метаданные пробелов карточки лида для UI и ассистентов.
 */
final class LeadGapCatalog
{
    /**
     * @return array<string, array{label: string, tab: string, kind?: string}>
     */
    public static function definitions(): array
    {
        return [
            'no_counterparty' => ['label' => 'Выбрать контрагента', 'tab' => 'main'],
            'no_route' => ['label' => 'Заполнить маршрут', 'tab' => 'route'],
            'no_cargo' => ['label' => 'Добавить груз', 'tab' => 'cargo'],
            'no_client_price' => ['label' => 'Указать цену клиенту', 'tab' => 'finance'],
            'no_offer' => ['label' => 'Подготовить КП', 'tab' => 'commercial'],
            'proposal_not_sent' => ['label' => 'Отметить отправку КП', 'tab' => 'commercial'],
            'no_lpr' => ['label' => 'Указать ЛПР в квалификации', 'tab' => 'main'],
            'no_open_task' => ['label' => 'Создать задачу по лиду', 'tab' => 'main', 'kind' => 'next_step'],
            'no_next_contact' => ['label' => 'Запланировать следующий контакт', 'tab' => 'activities'],
            'close_outcome_missing' => ['label' => 'Указать причину закрытия', 'tab' => 'main'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function checkToGap(): array
    {
        return [
            'has_counterparty' => 'no_counterparty',
            'has_route' => 'no_route',
            'has_cargo' => 'no_cargo',
            'has_client_price' => 'no_client_price',
            'has_offer' => 'no_offer',
            'proposal_sent' => 'proposal_not_sent',
            'has_lpr' => 'no_lpr',
            'has_open_task' => 'no_open_task',
            'has_next_contact' => 'no_next_contact',
            'close_outcome_set' => 'close_outcome_missing',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function positiveLabels(): array
    {
        return [
            'has_counterparty' => 'Контрагент выбран',
            'has_route' => 'Маршрут заполнен',
            'has_cargo' => 'Груз описан',
            'has_client_price' => 'Цена клиенту указана',
            'has_offer' => 'КП подготовлено',
            'proposal_sent' => 'КП отправлено клиенту',
            'has_lpr' => 'ЛПР зафиксирован',
            'has_open_task' => 'Есть открытая задача',
            'has_next_contact' => 'Запланирован следующий контакт',
            'close_outcome_set' => 'Причина закрытия указана',
        ];
    }

    /**
     * @return array{label: string, tab: string, kind?: string}|null
     */
    public static function gapMeta(string $code): ?array
    {
        return self::definitions()[$code] ?? null;
    }
}
