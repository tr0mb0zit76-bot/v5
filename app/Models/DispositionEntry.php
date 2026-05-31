<?php

namespace App\Models;

use App\Support\DispositionSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositionEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'date',
        'slot',
        'location',
        'comment',
        'recorded_at',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'date' => 'date',
            'recorded_at' => 'datetime',
            'recorded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function slotEnum(): DispositionSlot
    {
        return DispositionSlot::from($this->slot);
    }
}
