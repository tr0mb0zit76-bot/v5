<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImprovementExperimentAssignment extends Model
{
    public const VARIANT_A = 'a';

    public const VARIANT_B = 'b';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'experiment_id',
        'lead_id',
        'variant',
        'outcome',
        'assigned_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImprovementExperiment, $this>
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(ImprovementExperiment::class, 'experiment_id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
