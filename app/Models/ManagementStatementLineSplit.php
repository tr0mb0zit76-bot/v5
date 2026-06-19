<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementStatementLineSplit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'management_statement_line_id',
        'allocation_type',
        'payment_schedule_id',
        'order_id',
        'category_id',
        'user_id',
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
     * @return BelongsTo<ManagementStatementLine, $this>
     */
    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(ManagementStatementLine::class, 'management_statement_line_id');
    }

    /**
     * @return BelongsTo<PaymentSchedule, $this>
     */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<ManagementExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ManagementExpenseCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
