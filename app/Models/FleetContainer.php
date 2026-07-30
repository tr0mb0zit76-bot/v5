<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetContainer extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_contractor_id',
        'container_number',
        'size_code',
        'container_type',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_contractor_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'owner_contractor_id');
    }

    /**
     * @return HasMany<FleetContainerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(FleetContainerDocument::class);
    }
}
