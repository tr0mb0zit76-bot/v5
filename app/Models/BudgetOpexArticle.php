<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'management_expense_category_id',
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

    /**
     * @return BelongsTo<ManagementExpenseCategory, $this>
     */
    public function managementExpenseCategory(): BelongsTo
    {
        return $this->belongsTo(ManagementExpenseCategory::class, 'management_expense_category_id');
    }
}
