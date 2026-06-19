<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementStatementLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'bank_account_id',
        'line_hash',
        'row_number',
        'operation_date',
        'direction',
        'amount',
        'currency',
        'exchange_rate',
        'description',
        'status',
        'source',
        'match_type',
        'match_confidence',
        'match_notes',
        'suggested_order_id',
        'suggested_payment_schedule_id',
        'suggested_category_id',
        'suggested_user_id',
        'allocation_category_id',
        'allocation_order_id',
        'allocation_payment_schedule_id',
        'allocation_user_id',
        'allocation_amount',
        'allocated_by',
        'allocated_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'allocation_amount' => 'decimal:2',
            'allocated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ManagementStatementImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ManagementStatementImport::class, 'import_id');
    }

    /**
     * @return BelongsTo<ManagementBankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ManagementBankAccount::class, 'bank_account_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function suggestedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'suggested_order_id');
    }

    /**
     * @return BelongsTo<PaymentSchedule, $this>
     */
    public function suggestedPaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'suggested_payment_schedule_id');
    }

    /**
     * @return BelongsTo<ManagementExpenseCategory, $this>
     */
    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(ManagementExpenseCategory::class, 'suggested_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suggestedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_user_id');
    }

    /**
     * @return BelongsTo<ManagementExpenseCategory, $this>
     */
    public function allocationCategory(): BelongsTo
    {
        return $this->belongsTo(ManagementExpenseCategory::class, 'allocation_category_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function allocationOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'allocation_order_id');
    }

    /**
     * @return BelongsTo<PaymentSchedule, $this>
     */
    public function allocationPaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'allocation_payment_schedule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocation_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    /**
     * @return HasMany<ManagementStatementLineSplit, $this>
     */
    public function splits(): HasMany
    {
        return $this->hasMany(ManagementStatementLineSplit::class, 'management_statement_line_id');
    }
}
