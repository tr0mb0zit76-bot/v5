<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadBoardRateObservation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'load_board_post_id',
        'load_board_offer_id',
        'carrier_id',
        'corridor_key',
        'loading_location',
        'unloading_location',
        'truck_body_type_code',
        'cargo_weight',
        'customer_rate',
        'customer_rate_currency',
        'carrier_rate',
        'carrier_rate_currency',
        'margin_abs',
        'margin_pct',
        'source',
        'outcome',
        'observed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cargo_weight' => 'decimal:2',
            'customer_rate' => 'decimal:2',
            'carrier_rate' => 'decimal:2',
            'margin_abs' => 'decimal:2',
            'margin_pct' => 'decimal:2',
            'observed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LoadBoardPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(LoadBoardPost::class, 'load_board_post_id');
    }

    /**
     * @return BelongsTo<LoadBoardOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(LoadBoardOffer::class, 'load_board_offer_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'carrier_id');
    }
}
