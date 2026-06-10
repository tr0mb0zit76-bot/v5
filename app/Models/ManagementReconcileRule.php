<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementReconcileRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'keyword',
        'direction',
        'allocation_type',
        'category_id',
        'user_id',
        'order_number',
        'payment_schedule_id',
        'notes',
        'priority',
        'times_applied',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'times_applied' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
    public function payrollUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
