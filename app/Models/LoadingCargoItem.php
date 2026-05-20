<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadingCargoItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'loading_cargo_group_id',
        'client_key',
        'name',
        'package_type',
        'quantity',
        'length_mm',
        'width_mm',
        'height_mm',
        'weight_kg',
        'can_rotate',
        'stackable',
        'max_stack',
        'can_tilt',
        'color',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'can_rotate' => 'boolean',
            'stackable' => 'boolean',
            'can_tilt' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LoadingCargoGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(LoadingCargoGroup::class, 'loading_cargo_group_id');
    }
}
