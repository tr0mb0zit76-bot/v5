<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetSalesTarget extends Model
{
    public const METRIC_REVENUE = 'revenue';

    public const METRIC_MARGIN = 'margin';

    public const METRIC_LEADS = 'leads';

    public const METRIC_ORDERS = 'orders';

    /**
     * @return list<string>
     */
    public static function metrics(): array
    {
        return [
            self::METRIC_REVENUE,
            self::METRIC_MARGIN,
            self::METRIC_LEADS,
            self::METRIC_ORDERS,
        ];
    }

    /**
     * @return array<string, array{label: string, unit: string}>
     */
    public static function metricCatalog(): array
    {
        return [
            self::METRIC_REVENUE => ['label' => 'Выручка', 'unit' => '₽'],
            self::METRIC_MARGIN => ['label' => 'Маржа', 'unit' => '₽'],
            self::METRIC_LEADS => ['label' => 'Лиды (won)', 'unit' => 'шт'],
            self::METRIC_ORDERS => ['label' => 'Заказы (закрытые)', 'unit' => 'шт'],
        ];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scenario_id',
        'user_id',
        'period_month',
        'metric',
        'planned_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'planned_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<BudgetScenario, $this>
     */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(BudgetScenario::class, 'scenario_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
