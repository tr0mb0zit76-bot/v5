<?php

namespace App\Models;

use Database\Factories\RoutePointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutePoint extends Model
{
    /** @use HasFactory<RoutePointFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_leg_id',
        'address_id',
        'type',
        'sequence',
        'address',
        'normalized_data',
        'kladr_id',
        'latitude',
        'longitude',
        'planned_date',
        'planned_time_from',
        'planned_time_to',
        'actual_date',
        'actual_time',
        'contact_person',
        'contact_phone',
        'instructions',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_date' => 'date',
            'actual_date' => 'date',
            'normalized_data' => 'array',
            'metadata' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * @return BelongsTo<OrderLeg, $this>
     */
    public function leg(): BelongsTo
    {
        return $this->belongsTo(OrderLeg::class, 'order_leg_id');
    }
}
