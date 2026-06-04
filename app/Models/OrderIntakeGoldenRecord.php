<?php

namespace App\Models;

use App\Enums\OrderIntakeGoldenRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIntakeGoldenRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_intake_draft_id',
        'user_id',
        'status',
        'source_kind',
        'user_instruction',
        'dialog_learnings',
        'proposed_snapshot',
        'applied_snapshot',
        'order_id',
        'committed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderIntakeGoldenRecordStatus::class,
            'dialog_learnings' => 'array',
            'proposed_snapshot' => 'array',
            'applied_snapshot' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderIntakeDraft, $this>
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(OrderIntakeDraft::class, 'order_intake_draft_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
