<?php

namespace App\Models;

use Database\Factories\LeadCargoItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadCargoItem extends Model
{
    /** @use HasFactory<LeadCargoItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'name',
        'description',
        'weight_kg',
        'volume_m3',
        'package_type',
        'package_count',
        'dangerous_goods',
        'dangerous_class',
        'hs_code',
        'cargo_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'volume_m3' => 'decimal:2',
            'dangerous_goods' => 'boolean',
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
