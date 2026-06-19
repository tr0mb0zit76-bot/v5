<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetScenario extends Model
{
    public const PLAN_TYPE_COMPANY = 'company';

    public const PLAN_TYPE_SALES_PAYROLL = 'sales_payroll';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'plan_type',
        'parent_scenario_id',
        'inputs',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inputs' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return BelongsTo<BudgetScenario, $this>
     */
    public function parentScenario(): BelongsTo
    {
        return $this->belongsTo(BudgetScenario::class, 'parent_scenario_id');
    }

    /**
     * @return HasMany<BudgetScenario, $this>
     */
    public function childScenarios(): HasMany
    {
        return $this->hasMany(BudgetScenario::class, 'parent_scenario_id');
    }

    /**
     * @return HasMany<BudgetPlanSnapshot, $this>
     */
    public function planSnapshots(): HasMany
    {
        return $this->hasMany(BudgetPlanSnapshot::class, 'scenario_id');
    }
}
