<?php

namespace App\Models;

use Database\Factories\OrderLegFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderLeg extends Model
{
    /** @use HasFactory<OrderLegFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'sequence',
        'type',
        'description',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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
     * @return HasMany<RoutePoint, $this>
     */
    public function routePoints(): HasMany
    {
        return $this->hasMany(RoutePoint::class)->orderBy('sequence');
    }

    /**
     * @return HasOne<LegContractorAssignment, $this>
     */
    public function contractorAssignment(): HasOne
    {
        return $this->hasOne(LegContractorAssignment::class, 'order_leg_id');
    }

    /**
     * @return HasOne<LegCost, $this>
     */
    public function cost(): HasOne
    {
        return $this->hasOne(LegCost::class, 'order_leg_id');
    }
}
