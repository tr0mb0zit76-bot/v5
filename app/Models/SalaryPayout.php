<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPayout extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'period_id',
        'user_id',
        'amount',
        'payout_date',
        'type',
        'comment',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payout_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SalaryPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<SalaryPayoutAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SalaryPayoutAllocation::class, 'payout_id');
    }
}
