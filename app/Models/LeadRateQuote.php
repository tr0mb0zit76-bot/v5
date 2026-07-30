<?php

namespace App\Models;

use Database\Factories\LeadRateQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadRateQuote extends Model
{
    /** @use HasFactory<LeadRateQuoteFactory> */
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_SELECTED = 'selected';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PHONE = 'phone';

    public const SOURCE_ATI = 'ati';

    public const SOURCE_LOAD_BOARD = 'load_board';

    public const SOURCE_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'contractor_id',
        'load_board_offer_id',
        'created_by',
        'carrier_name',
        'rate',
        'currency',
        'payment_form',
        'valid_until',
        'source',
        'status',
        'comment',
        'selected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'valid_until' => 'date',
            'selected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<LoadBoardOffer, $this>
     */
    public function loadBoardOffer(): BelongsTo
    {
        return $this->belongsTo(LoadBoardOffer::class, 'load_board_offer_id');
    }
}
