<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderOneCDocument extends Model
{
    public const TYPE_REALIZATION = 'realization';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATED = 'created';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'document_type',
        'status',
        'external_ref',
        'external_number',
        'external_date',
        'amount',
        'counterparty_inn',
        'counterparty_kpp',
        'request_payload',
        'response_payload',
        'last_error',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_date' => 'datetime',
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toWizardSummary(): array
    {
        return [
            'id' => (int) $this->id,
            'document_type' => (string) $this->document_type,
            'status' => (string) $this->status,
            'external_ref' => $this->external_ref,
            'external_number' => $this->external_number,
            'external_date' => $this->external_date?->toIso8601String(),
            'amount' => $this->amount !== null ? (string) $this->amount : null,
            'counterparty_inn' => $this->counterparty_inn,
            'counterparty_kpp' => $this->counterparty_kpp,
            'last_error' => $this->last_error,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
