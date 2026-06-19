<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPlanSnapshot extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scenario_id',
        'period_label',
        'period_start',
        'period_end',
        'approved_at',
        'approved_by_user_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'approved_at' => 'datetime',
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return HasMany<BudgetPlanSnapshotLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetPlanSnapshotLine::class, 'snapshot_id');
    }
}
