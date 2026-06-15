<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PaymentSchedulePaymentEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'contractor_id',
        'payment_schedule_id',
        'party',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_reference',
        'notes',
        'recorded_by',
        'reversed_at',
        'reversed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        if (Schema::hasColumn($query->getModel()->getTable(), 'reversed_at')) {
            return $query->whereNull('reversed_at');
        }

        return $query;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<PaymentSchedule, $this>
     */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }
}
