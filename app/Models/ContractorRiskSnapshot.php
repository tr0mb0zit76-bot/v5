<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractorRiskSnapshot extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'contractor_id',
        'inn',
        'model_version',
        'normalized_data',
        'scoring_result',
        'checko_from_cache',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'normalized_data' => 'array',
            'scoring_result' => 'array',
            'checko_from_cache' => 'boolean',
            'expires_at' => 'datetime',
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
     * @return HasMany<ContractorRiskAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(ContractorRiskAssessment::class);
    }
}
