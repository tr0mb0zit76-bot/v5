<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class SalaryCoefficient extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'manager_id',
        'base_salary',
        'bonus_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary' => 'integer',
            'bonus_percent' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public static function getForManagerOnDate(int $managerId, ?string $date): ?self
    {
        if (! Schema::hasTable('salary_coefficients') || blank($date)) {
            return null;
        }

        return self::query()
            ->where('manager_id', $managerId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
