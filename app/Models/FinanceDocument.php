<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'document_type',
        'status',
        'number',
        'issue_date',
        'due_date',
        'amount',
        'payment_basis',
        'metadata',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
