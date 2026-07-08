<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementCase extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'load_board_post_id',
        'lead_id',
        'order_id',
        'order_owner_id',
        'buyer_id',
        'dispatcher_id',
        'buying_own_company_id',
        'status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LoadBoardPost, $this>
     */
    public function loadBoardPost(): BelongsTo
    {
        return $this->belongsTo(LoadBoardPost::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function orderOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'order_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function buyingOwnCompany(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'buying_own_company_id');
    }
}
