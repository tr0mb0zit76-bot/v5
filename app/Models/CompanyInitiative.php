<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyInitiative extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'goal',
        'expected_result',
        'status',
        'priority',
        'direction',
        'starts_on',
        'ends_on',
        'owner_id',
        'created_by',
        'planned_budget_amount',
        'budget_currency',
        'management_expense_category_id',
        'budget_notes',
        'progress_percent',
        'risk_level',
        'risk_summary',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'planned_budget_amount' => 'decimal:2',
            'progress_percent' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ManagementExpenseCategory, $this>
     */
    public function managementExpenseCategory(): BelongsTo
    {
        return $this->belongsTo(ManagementExpenseCategory::class, 'management_expense_category_id');
    }

    /**
     * @return HasMany<CompanyInitiativeMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(CompanyInitiativeMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CompanyInitiativeDependency, $this>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(CompanyInitiativeDependency::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
