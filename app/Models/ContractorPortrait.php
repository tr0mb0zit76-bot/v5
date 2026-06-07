<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorPortrait extends Model
{
    protected $primaryKey = 'contractor_id';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'communication_style',
        'price_sensitivity',
        'preferred_channel',
        'decision_cadence',
        'relationship_trust',
        'success_criteria',
        'typical_objections',
        'internal_notes',
        'coverage_pct',
        'portrait_updated_at',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'typical_objections' => 'array',
            'coverage_pct' => 'integer',
            'portrait_updated_at' => 'datetime',
        ];
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
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
