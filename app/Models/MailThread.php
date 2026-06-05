<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailThread extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject',
        'lead_id',
        'order_id',
        'contractor_id',
        'lead_offer_id',
        'last_message_at',
        'last_outbound_at',
        'last_inbound_at',
        'created_by',
        'mailbox_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'last_inbound_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MailMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class)->orderByDesc('sent_at')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
    public function mailboxUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mailbox_user_id');
    }

    /**
     * @return BelongsTo<LeadOffer, $this>
     */
    public function leadOffer(): BelongsTo
    {
        return $this->belongsTo(LeadOffer::class);
    }
}
