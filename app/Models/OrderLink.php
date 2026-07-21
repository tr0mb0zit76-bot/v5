<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLink extends Model
{
    public const TYPE_EXPEDITION_CHAIN = 'expedition_chain';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'linked_order_id',
        'link_type',
        'created_by',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function linkedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'linked_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
