<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetPlanSnapshotLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'snapshot_id',
        'month',
        'opex_article_id',
        'category_id',
        'article_name',
        'planned_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'planned_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<BudgetPlanSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(BudgetPlanSnapshot::class, 'snapshot_id');
    }

    /**
     * @return BelongsTo<BudgetOpexArticle, $this>
     */
    public function opexArticle(): BelongsTo
    {
        return $this->belongsTo(BudgetOpexArticle::class, 'opex_article_id');
    }

    /**
     * @return BelongsTo<ManagementExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ManagementExpenseCategory::class, 'category_id');
    }
}
