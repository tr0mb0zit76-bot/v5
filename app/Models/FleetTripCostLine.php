<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetTripCostLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'fleet_trip_id',
        'cost_category',
        'amount',
        'currency',
        'comment',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fleet_trip_id' => 'integer',
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FleetTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(FleetTrip::class, 'fleet_trip_id');
    }
}
