<?php

namespace App\Models;

use Database\Factories\CargoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cargo extends Model
{
    /** @use HasFactory<CargoFactory> */
    use HasFactory;

    protected $table = 'cargos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'title',
        'ati_cargo_name',
        'description',
        'weight',
        'weight_value',
        'weight_unit',
        'volume',
        'cargo_type',
        'cargo_type_id',
        'cargo_type_label',
        'packing_type',
        'package_count',
        'pack_type_id',
        'pack_type_label',
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
        'pallet_count',
        'belt_count',
        'length',
        'width',
        'height',
        'diameter',
        'is_hazardous',
        'hazard_class',
        'hs_code',
        'needs_temperature',
        'temp_min',
        'temp_max',
        'needs_hydraulic',
        'needs_manipulator',
        'is_oversized',
        'is_fragile',
        'special_instructions',
        'photos',
        'documents',
        'ati_load_id',
        'ati_published_at',
        'ati_response',
        'ati_cargo_payload',
        'source_text',
        'source_file',
        'parsed_by_ai',
        'parsed_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'weight_value' => 'decimal:3',
            'volume' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'diameter' => 'decimal:2',
            'temp_min' => 'decimal:2',
            'temp_max' => 'decimal:2',
            'is_hazardous' => 'boolean',
            'needs_temperature' => 'boolean',
            'needs_hydraulic' => 'boolean',
            'needs_manipulator' => 'boolean',
            'is_oversized' => 'boolean',
            'is_fragile' => 'boolean',
            'photos' => 'array',
            'documents' => 'array',
            'ati_response' => 'array',
            'ati_cargo_payload' => 'array',
            'loading_type_items' => 'array',
            'truck_body_type_items' => 'array',
            'trailer_type_items' => 'array',
            'parsed_by_ai' => 'boolean',
            'parsed_at' => 'datetime',
            'ati_published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
