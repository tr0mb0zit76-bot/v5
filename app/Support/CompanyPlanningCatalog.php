<?php

declare(strict_types=1);

namespace App\Support;

final class CompanyPlanningCatalog
{
    /** @var list<string> */
    public const INITIATIVE_STATUSES = [
        'draft',
        'active',
        'on_hold',
        'completed',
        'cancelled',
    ];

    /** @var list<string> */
    public const MILESTONE_STATUSES = [
        'planned',
        'in_progress',
        'completed',
        'cancelled',
    ];

    /** @var list<string> */
    public const PRIORITIES = [
        'low',
        'normal',
        'high',
        'critical',
    ];

    /** @var list<string> */
    public const RISK_LEVELS = [
        'low',
        'normal',
        'high',
        'critical',
    ];

    /** @var list<string> */
    public const DIRECTIONS = [
        'sales',
        'operations',
        'finance',
        'hr',
        'it',
        'legal',
        'general',
    ];

    /** @var list<string> */
    public const DEPENDENCY_TYPES = [
        'finish_to_start',
    ];

    /**
     * @return array<string, string>
     */
    public static function initiativeStatusLabels(): array
    {
        return [
            'draft' => 'Черновик',
            'active' => 'В работе',
            'on_hold' => 'На паузе',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function milestoneStatusLabels(): array
    {
        return [
            'planned' => 'Запланирован',
            'in_progress' => 'В работе',
            'completed' => 'Выполнен',
            'cancelled' => 'Отменён',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priorityLabels(): array
    {
        return [
            'low' => 'Низкий',
            'normal' => 'Обычный',
            'high' => 'Высокий',
            'critical' => 'Критичный',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function riskLevelLabels(): array
    {
        return [
            'low' => 'Низкий',
            'normal' => 'Средний',
            'high' => 'Высокий',
            'critical' => 'Критичный',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function directionLabels(): array
    {
        return [
            'sales' => 'Продажи',
            'operations' => 'Операции',
            'finance' => 'Финансы',
            'hr' => 'Персонал',
            'it' => 'IT',
            'legal' => 'Юридический блок',
            'general' => 'Общее',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function dependencyTypeLabels(): array
    {
        return [
            'finish_to_start' => 'После завершения',
        ];
    }
}
