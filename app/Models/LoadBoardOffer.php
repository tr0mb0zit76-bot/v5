<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadBoardOffer extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'load_board_post_id',
        'carrier_id',
        'created_by',
        'status',
        'source',
        'carrier_rate',
        'carrier_rate_currency',
        'payment_form',
        'available_date',
        'carrier_contact',
        'conditions',
        'comment',
        'selected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carrier_rate' => 'decimal:2',
            'available_date' => 'date',
            'selected_at' => 'datetime',
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
     * @return BelongsTo<Contractor, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'carrier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
