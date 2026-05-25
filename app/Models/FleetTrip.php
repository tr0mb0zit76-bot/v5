<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetTrip extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'order_leg_stage',
        'carrier_slot',
        'fleet_vehicle_id',
        'fleet_driver_id',
        'status',
        'estimated_cost',
        'total_cost',
        'planned_km',
        'actual_km',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'carrier_slot' => 'integer',
            'fleet_vehicle_id' => 'integer',
            'fleet_driver_id' => 'integer',
            'estimated_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'planned_km' => 'integer',
            'actual_km' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<FleetVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    /**
     * @return BelongsTo<FleetDriver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'fleet_driver_id');
    }

    /**
     * @return HasMany<FleetTripCostLine, $this>
     */
    public function costLines(): HasMany
    {
        return $this->hasMany(FleetTripCostLine::class);
    }
}
