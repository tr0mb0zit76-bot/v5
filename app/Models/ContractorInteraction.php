<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorInteraction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'contractor_contact_id',
        'contacted_at',
        'channel',
        'outcome_code',
        'next_contact_at',
        'subject',
        'summary',
        'result',
        'objection_tags',
        'merge_to_portrait',
        'mail_message_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'next_contact_at' => 'datetime',
            'objection_tags' => 'array',
            'merge_to_portrait' => 'boolean',
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
