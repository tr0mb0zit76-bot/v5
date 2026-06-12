<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementExpenseCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'kind',
        'flow',
        'is_system',
        'is_active',
        'include_in_budget',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'include_in_budget' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ManagementExpenseCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ManagementExpenseCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
