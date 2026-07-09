<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDocumentEdoAcknowledgement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'party',
        'document_type',
        'slot_key',
        'contractor_id',
        'received_via_edo',
        'document_number',
        'document_date',
        'confirmed_by',
        'confirmed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contractor_id' => 'integer',
            'received_via_edo' => 'boolean',
            'document_date' => 'date',
            'confirmed_at' => 'datetime',
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
    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
