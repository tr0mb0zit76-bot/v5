<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementPayrollHalf extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'year',
        'month',
        'half',
        'period_start',
        'period_end',
        'payment_date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payment_date' => 'date',
        ];
    }

    /**
     * @return HasMany<ManagementPayrollHalfUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(ManagementPayrollHalfUser::class, 'payroll_half_id');
    }
}
