<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayoutAllocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'payout_id',
        'accrual_id',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SalaryPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(SalaryPayout::class, 'payout_id');
    }

    /**
     * @return BelongsTo<SalaryAccrual, $this>
     */
    public function accrual(): BelongsTo
    {
        return $this->belongsTo(SalaryAccrual::class, 'accrual_id');
    }
}
