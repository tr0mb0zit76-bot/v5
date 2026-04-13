<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'party',
        'type',
        'amount',
        'paid_amount',
        'remaining_amount',
        'planned_date',
        'actual_date',
        'status',
        'payment_method',
        'transaction_reference',
        'counterparty_id',
        'parent_payment_id',
        'is_partial',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'planned_date' => 'date',
        'actual_date' => 'date',
        'is_partial' => 'boolean',
    ];

    /**
     * Get the order that owns the payment schedule.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the counterparty (contractor) for the payment.
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'counterparty_id');
    }

    /**
     * Get the parent payment for partial payments.
     */
    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'parent_payment_id');
    }

    /**
     * Get the partial payments for this payment.
     */
    public function partialPayments(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class, 'parent_payment_id')
            ->where('is_partial', true);
    }

    /**
     * Check if payment is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->status === 'paid' || $this->remaining_amount <= 0;
    }

    /**
     * Check if payment has partial payments.
     */
    public function hasPartialPayments(): bool
    {
        return $this->partialPayments()->exists();
    }

    /**
     * Get total paid amount including partial payments.
     */
    public function getTotalPaidAttribute(): float
    {
        if ($this->is_partial) {
            return (float) $this->paid_amount;
        }
        
        $partialTotal = $this->partialPayments()->sum('paid_amount');
        return (float) $this->paid_amount + $partialTotal;
    }

    /**
     * Get payment progress percentage.
     */
    public function getPaymentProgressAttribute(): float
    {
        if ($this->amount <= 0) {
            return 0;
        }
        
        $totalPaid = $this->total_paid;
        return min(100, ($totalPaid / $this->amount) * 100);
    }
}
