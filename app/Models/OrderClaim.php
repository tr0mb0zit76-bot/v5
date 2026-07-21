<?php

namespace App\Models;

use App\Enums\OrderClaimParty;
use App\Enums\OrderClaimStatus;
use App\Enums\OrderClaimType;
use Database\Factories\OrderClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderClaim extends Model
{
    /** @use HasFactory<OrderClaimFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'contractor_id',
        'number',
        'party',
        'type',
        'status',
        'title',
        'description',
        'amount_risk',
        'currency',
        'responsible_id',
        'created_by',
        'due_at',
        'resolved_at',
        'resolution_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party' => OrderClaimParty::class,
            'type' => OrderClaimType::class,
            'status' => OrderClaimStatus::class,
            'amount_risk' => 'decimal:2',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
