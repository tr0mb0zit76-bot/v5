<?php

namespace App\Models;

use Database\Factories\LeadRoutePointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadRoutePoint extends Model
{
    /** @use HasFactory<LeadRoutePointFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'type',
        'sequence',
        'address',
        'normalized_data',
        'planned_date',
        'contact_person',
        'contact_phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'normalized_data' => 'array',
            'planned_date' => 'date',
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
