<?php

namespace App\Models;

use Database\Factories\FinancialTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTerm extends Model
{
    /** @use HasFactory<FinancialTermFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'client_price',
        'client_currency',
        'client_payment_terms',
        'contractors_costs',
        'total_cost',
        'margin',
        'additional_costs',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contractors_costs' => 'array',
            'additional_costs' => 'array',
            'client_price' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'margin' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
