<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneCEpdRegistryEntry extends Model
{
    public const TYPE_ETRN = 'etrn';

    public const TYPE_EXPEDITION_ORDER = 'expedition_order';

    public const TYPE_EXPEDITION_RECEIPT = 'expedition_receipt';

    public const TYPE_E_ORDER = 'e_order';

    public const TYPE_E_WORK_ORDER = 'e_work_order';

    public const TYPE_UNKNOWN = 'unknown';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'publication_code',
        'document_ref',
        'document_ref_type',
        'type_ref_key',
        'document_type',
        'document_type_label',
        'epd_number',
        'epd_date',
        'ib_number',
        'ib_date',
        'current_step',
        'current_step_done',
        'participant_role',
        'organization_ref',
        'shipper_ref',
        'shipper_name',
        'shipper_inn',
        'consignee_ref',
        'consignee_name',
        'consignee_inn',
        'carrier_ref',
        'carrier_name',
        'carrier_inn',
        'posted',
        'deletion_mark',
        'order_id',
        'linked_by',
        'linked_at',
        'raw_payload',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'epd_date' => 'date',
            'ib_date' => 'datetime',
            'current_step_done' => 'boolean',
            'posted' => 'boolean',
            'deletion_mark' => 'boolean',
            'linked_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'raw_payload' => 'array',
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
    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toGridRow(): array
    {
        return [
            'id' => (int) $this->id,
            'publication_code' => (string) $this->publication_code,
            'document_ref' => (string) $this->document_ref,
            'document_type' => (string) $this->document_type,
            'document_type_label' => (string) ($this->document_type_label ?: $this->document_type),
            'epd_number' => $this->epd_number,
            'epd_date' => $this->epd_date?->toDateString(),
            'ib_number' => $this->ib_number,
            'ib_date' => $this->ib_date?->toIso8601String(),
            'current_step' => $this->current_step,
            'current_step_done' => (bool) $this->current_step_done,
            'shipper_name' => $this->shipper_name,
            'shipper_inn' => $this->shipper_inn,
            'consignee_name' => $this->consignee_name,
            'consignee_inn' => $this->consignee_inn,
            'carrier_name' => $this->carrier_name,
            'carrier_inn' => $this->carrier_inn,
            'posted' => (bool) $this->posted,
            'deletion_mark' => (bool) $this->deletion_mark,
            'order_id' => $this->order_id !== null ? (int) $this->order_id : null,
            'order_number' => $this->order?->order_number,
            'linked_at' => $this->linked_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
