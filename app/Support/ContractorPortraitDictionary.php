<?php

namespace App\Support;

final class ContractorPortraitDictionary
{
    public const UNKNOWN = 'unknown';

    /**
     * @return list<string>
     */
    public static function communicationStyles(): array
    {
        return ['analytical', 'driver', 'amiable', 'expressive', self::UNKNOWN];
    }

    /**
     * @return list<string>
     */
    public static function priceSensitivities(): array
    {
        return ['low', 'medium', 'high', self::UNKNOWN];
    }

    /**
     * @return list<string>
     */
    public static function preferredChannels(): array
    {
        return ['phone', 'email', 'messenger', 'meeting', self::UNKNOWN];
    }

    /**
     * @return list<string>
     */
    public static function decisionCadences(): array
    {
        return ['fast', 'committee', 'slow', self::UNKNOWN];
    }

    /**
     * @return list<string>
     */
    public static function relationshipTrusts(): array
    {
        return ['new', 'stable', 'strained', self::UNKNOWN];
    }

    /**
     * @return list<string>
     */
    public static function contactRolesInDeal(): array
    {
        return ['decision_maker', 'influencer', 'gatekeeper', 'executor', 'finance', self::UNKNOWN];
    }

    /**
     * @return list<string>
     */
    public static function interactionOutcomes(): array
    {
        return ['reached', 'no_answer', 'callback', 'objection', 'agreed_next', 'refused'];
    }

    /**
     * @return list<string>
     */
    public static function objectionTags(): array
    {
        return ['price', 'timing', 'competitor', 'documents', 'capacity', 'trust'];
    }

    public static function label(string $group, ?string $value): string
    {
        $value = $value ?: self::UNKNOWN;

        return match ($group) {
            'communication_style' => match ($value) {
                'analytical' => 'Аналитик',
                'driver' => 'Драйвер',
                'amiable' => 'Дружелюбный',
                'expressive' => 'Экспрессивный',
                default => 'Не указано',
            },
            'price_sensitivity' => match ($value) {
                'low' => 'Низкая',
                'medium' => 'Средняя',
                'high' => 'Высокая',
                default => 'Не указано',
            },
            'preferred_channel' => match ($value) {
                'phone' => 'Телефон',
                'email' => 'Email',
                'messenger' => 'Мессенджер',
                'meeting' => 'Встреча',
                default => 'Не указано',
            },
            'decision_cadence' => match ($value) {
                'fast' => 'Быстро',
                'committee' => 'Через комитет',
                'slow' => 'Медленно',
                default => 'Не указано',
            },
            'relationship_trust' => match ($value) {
                'new' => 'Новые отношения',
                'stable' => 'Стабильные',
                'strained' => 'Напряжённые',
                default => 'Не указано',
            },
            'role_in_deal' => match ($value) {
                'decision_maker' => 'ЛПР',
                'influencer' => 'Влияет на решение',
                'gatekeeper' => 'Фильтр / секретарь',
                'executor' => 'Исполнитель',
                'finance' => 'Финансы / бухгалтерия',
                default => 'Не указано',
            },
            'outcome_code' => match ($value) {
                'reached' => 'Связались',
                'no_answer' => 'Нет ответа',
                'callback' => 'Перезвонить',
                'objection' => 'Возражение',
                'agreed_next' => 'Договорились о следующем шаге',
                'refused' => 'Отказ',
                default => 'Не указано',
            },
            'objection_tag' => match ($value) {
                'price' => 'Цена',
                'timing' => 'Сроки',
                'competitor' => 'Конкурент',
                'documents' => 'Документы',
                'capacity' => 'Мощности',
                'trust' => 'Доверие',
                default => $value,
            },
            default => $value,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsFor(string $group): array
    {
        $values = match ($group) {
            'communication_style' => self::communicationStyles(),
            'price_sensitivity' => self::priceSensitivities(),
            'preferred_channel' => self::preferredChannels(),
            'decision_cadence' => self::decisionCadences(),
            'relationship_trust' => self::relationshipTrusts(),
            'role_in_deal' => self::contactRolesInDeal(),
            'outcome_code' => self::interactionOutcomes(),
            'objection_tag' => self::objectionTags(),
            default => [],
        };

        return array_map(
            fn (string $value): array => [
                'value' => $value,
                'label' => self::label($group, $value),
            ],
            $values,
        );
    }
}
