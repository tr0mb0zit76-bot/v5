<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetDriver extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'carrier_contractor_id',
        'full_name',
        'passport_series',
        'passport_number',
        'passport_issued_by',
        'passport_issued_at',
        'phone',
        'license_number',
        'license_categories',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carrier_contractor_id' => 'integer',
            'passport_issued_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'carrier_contractor_id');
    }

    /**
     * @return HasMany<FleetDriverDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(FleetDriverDocument::class);
    }
}
