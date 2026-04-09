<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPeriod extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'period_start',
        'period_end',
        'period_type',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'closed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    /**
     * @return HasMany<SalaryAccrual, $this>
     */
    public function accruals(): HasMany
    {
        return $this->hasMany(SalaryAccrual::class, 'period_id');
    }

    /**
     * @return HasMany<SalaryPayout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(SalaryPayout::class, 'period_id');
    }
}
