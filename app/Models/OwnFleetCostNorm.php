<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnFleetCostNorm extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'cn_fuel_price_rub_per_liter',
        'cn_fuel_consumption_l_per_100km',
        'cn_driver_rub_per_km',
        'cn_other_rub_per_km',
        'ru_fuel_price_rub_per_liter',
        'ru_fuel_consumption_l_per_100km',
        'ru_driver_rub_per_km',
        'ru_other_rub_per_km',
        'depreciation_rub_per_km',
        'margin_percent',
        'margin_absolute_rub',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cn_fuel_price_rub_per_liter' => 'decimal:4',
            'cn_fuel_consumption_l_per_100km' => 'decimal:4',
            'cn_driver_rub_per_km' => 'decimal:4',
            'cn_other_rub_per_km' => 'decimal:4',
            'ru_fuel_price_rub_per_liter' => 'decimal:4',
            'ru_fuel_consumption_l_per_100km' => 'decimal:4',
            'ru_driver_rub_per_km' => 'decimal:4',
            'ru_other_rub_per_km' => 'decimal:4',
            'depreciation_rub_per_km' => 'decimal:4',
            'margin_percent' => 'decimal:2',
            'margin_absolute_rub' => 'decimal:2',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
