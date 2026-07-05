<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPortalInvite extends Model
{
    public const PURPOSE_CARRIER_FLEET = 'carrier_fleet';

    public const PURPOSE_CUSTOMER_DOCUMENTS = 'customer_documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'contractor_id',
        'stage',
        'carrier_slot',
        'purpose',
        'token_hash',
        'created_by',
        'expires_at',
        'used_at',
        'revoked_at',
        'last_opened_at',
        'submitted_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'contractor_id' => 'integer',
            'carrier_slot' => 'integer',
            'created_by' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'submitted_payload' => 'array',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->used_at !== null;
    }

    public function isOpenForSubmission(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isSubmitted();
    }
}
