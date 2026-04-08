<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegCost extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_leg_id',
        'amount',
        'currency',
        'payment_form',
        'payment_schedule',
        'status',
        'calculated_at',
        'calculated_by',
        'leg_contractor_assignment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'calculated_at' => 'datetime',
            'payment_schedule' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OrderLeg, $this>
     */
    public function leg(): BelongsTo
    {
        return $this->belongsTo(OrderLeg::class, 'order_leg_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * @return BelongsTo<LegContractorAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LegContractorAssignment::class, 'leg_contractor_assignment_id');
    }
}
