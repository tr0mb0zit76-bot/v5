<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryCoefficient extends Model
{
    protected $table = 'salary_coefficients';

    protected $fillable = [
        'manager_id',
        'base_salary',
        'bonus_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'base_salary' => 'integer',
        'bonus_percent' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'effective_from',
        'effective_to',
        'created_at',
        'updated_at',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Получить активные коэффициенты для менеджера на дату
     */
    public static function getForManagerOnDate(int $managerId, string $date): ?self
    {
        return self::where('manager_id', $managerId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Получить историю изменений для менеджера
     */
    public static function getHistoryForManager(int $managerId): array
    {
        return self::where('manager_id', $managerId)
            ->orderBy('effective_from', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Деактивировать все записи менеджера
     */
    public static function deactivateAllForManager(int $managerId): int
    {
        return self::where('manager_id', $managerId)
            ->update(['is_active' => false]);
    }

    /**
     * Активировать запись и деактивировать остальные
     */
    public function activate(): self
    {
        // Деактивируем все другие записи этого менеджера
        self::where('manager_id', $this->manager_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        // Активируем текущую
        $this->update(['is_active' => true]);

        return $this;
    }
}
