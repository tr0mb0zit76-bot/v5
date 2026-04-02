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
        'description',
        'weight',
        'volume',
        'cargo_type',
        'cargo_type_id',
        'packing_type',
        'package_count',
        'pack_type_id',
        'pallet_count',
        'belt_count',
        'length',
        'width',
        'height',
        'is_hazardous',
        'hazard_class',
        'hs_code',
        'needs_temperature',
        'temp_min',
        'temp_max',
        'needs_hydraulic',
        'needs_manipulator',
        'special_instructions',
        'photos',
        'documents',
        'ati_load_id',
        'ati_published_at',
        'ati_response',
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
            'volume' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'temp_min' => 'decimal:2',
            'temp_max' => 'decimal:2',
            'is_hazardous' => 'boolean',
            'needs_temperature' => 'boolean',
            'needs_hydraulic' => 'boolean',
            'needs_manipulator' => 'boolean',
            'photos' => 'array',
            'documents' => 'array',
            'ati_response' => 'array',
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
