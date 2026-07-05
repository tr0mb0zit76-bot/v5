<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalUserInvite extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_contact_id',
        'contractor_id',
        'external_party',
        'token_hash',
        'created_by',
        'user_id',
        'expires_at',
        'consumed_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ContractorContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContractorContact::class, 'contractor_contact_id');
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isOpen(): bool
    {
        return ! $this->isRevoked() && ! $this->isConsumed() && ! $this->isExpired();
    }
}
