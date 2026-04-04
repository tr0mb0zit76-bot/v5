<?php

namespace App\Models;

use Database\Factories\LeadOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadOffer extends Model
{
    /** @use HasFactory<LeadOfferFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'status',
        'number',
        'offer_date',
        'price',
        'currency',
        'payload',
        'generated_file_path',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offer_date' => 'date',
            'price' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
