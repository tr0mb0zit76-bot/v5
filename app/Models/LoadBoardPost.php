<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoadBoardPost extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'order_id',
        'customer_id',
        'seller_id',
        'buyer_id',
        'accepted_offer_id',
        'accepted_by',
        'accepted_at',
        'status',
        'priority',
        'title',
        'loading_location',
        'unloading_location',
        'loading_date',
        'unloading_date',
        'cargo_name',
        'ati_cargo_name',
        'cargo_weight',
        'cargo_volume',
        'cargo_type_id',
        'cargo_type',
        'cargo_type_label',
        'pack_type_id',
        'package_type',
        'pack_type_label',
        'package_count',
        'loading_type_id',
        'loading_type_code',
        'loading_type_label',
        'loading_type_items',
        'truck_body_type_id',
        'truck_body_type_code',
        'truck_body_type_label',
        'truck_body_type_items',
        'trailer_type_id',
        'trailer_type_code',
        'trailer_type_label',
        'trailer_type_items',
        'length',
        'width',
        'height',
        'diameter',
        'is_hazardous',
        'hazard_class',
        'needs_temperature',
        'temp_min',
        'temp_max',
        'is_oversized',
        'is_fragile',
        'hs_code',
        'ati_cargo_payload',
        'transport_type',
        'customer_rate',
        'customer_rate_currency',
        'target_carrier_rate',
        'payment_form',
        'requirements',
        'seller_comment',
        'metadata',
        'published_at',
        'taken_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loading_date' => 'date',
            'unloading_date' => 'date',
            'customer_rate' => 'decimal:2',
            'target_carrier_rate' => 'decimal:2',
            'cargo_weight' => 'decimal:2',
            'cargo_volume' => 'decimal:2',
            'cargo_type_id' => 'integer',
            'pack_type_id' => 'integer',
            'package_count' => 'integer',
            'loading_type_id' => 'integer',
            'loading_type_items' => 'array',
            'truck_body_type_id' => 'integer',
            'truck_body_type_items' => 'array',
            'trailer_type_id' => 'integer',
            'trailer_type_items' => 'array',
            'metadata' => 'array',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'diameter' => 'decimal:2',
            'is_hazardous' => 'boolean',
            'needs_temperature' => 'boolean',
            'temp_min' => 'decimal:2',
            'temp_max' => 'decimal:2',
            'is_oversized' => 'boolean',
            'is_fragile' => 'boolean',
            'ati_cargo_payload' => 'array',
            'accepted_at' => 'datetime',
            'published_at' => 'datetime',
            'taken_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<LoadBoardOffer, $this>
     */
    public function acceptedOffer(): BelongsTo
    {
        return $this->belongsTo(LoadBoardOffer::class, 'accepted_offer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @return HasMany<LoadBoardOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(LoadBoardOffer::class);
    }

    /**
     * @return HasOne<ProcurementCase, $this>
     */
    public function procurementCase(): HasOne
    {
        return $this->hasOne(ProcurementCase::class);
    }
}
