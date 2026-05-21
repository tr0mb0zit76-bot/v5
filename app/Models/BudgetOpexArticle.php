<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetOpexArticle extends Model
{
    public const COST_FIXED_MONTHLY = 'fixed_monthly';

    public const COST_PERCENT_OF_MARGIN = 'percent_of_margin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cost_type',
        'amount_monthly',
        'percent_of_margin',
        'ramp_months',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_monthly' => 'decimal:2',
            'percent_of_margin' => 'decimal:2',
            'ramp_months' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function isPercentOfMargin(): bool
    {
        return $this->cost_type === self::COST_PERCENT_OF_MARGIN;
    }
}
