<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryAccrual extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'period_id',
        'user_id',
        'order_id',
        'order_date_snapshot',
        'delta_snapshot',
        'salary_amount',
        'customer_rate_snapshot',
        'paid_customer_amount_at_accrual',
        'payable_amount_computed',
        'paid_amount_fact',
        'unpaid_amount',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date_snapshot' => 'date',
            'delta_snapshot' => 'decimal:2',
            'salary_amount' => 'decimal:2',
            'customer_rate_snapshot' => 'decimal:2',
            'paid_customer_amount_at_accrual' => 'decimal:2',
            'payable_amount_computed' => 'decimal:2',
            'paid_amount_fact' => 'decimal:2',
            'unpaid_amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SalaryPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return HasMany<SalaryPayoutAllocation, $this>
     */
    public function payoutAllocations(): HasMany
    {
        return $this->hasMany(SalaryPayoutAllocation::class, 'accrual_id');
    }
}
