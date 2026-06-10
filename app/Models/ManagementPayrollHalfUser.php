<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementPayrollHalfUser extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'payroll_half_id',
        'user_id',
        'accrued_amount',
        'paid_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accrued_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ManagementPayrollHalf, $this>
     */
    public function payrollHalf(): BelongsTo
    {
        return $this->belongsTo(ManagementPayrollHalf::class, 'payroll_half_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
