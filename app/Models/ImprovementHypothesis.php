<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementHypothesis extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IN_EXPERIMENT = 'in_experiment';

    public const STATUS_ADOPTED = 'adopted';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'signal_id',
        'pipeline_run_id',
        'category',
        'text',
        'short_reason',
        'impact',
        'confidence',
        'ease',
        'score',
        'status',
        'source',
        'fingerprint',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'impact' => 'integer',
            'confidence' => 'integer',
            'ease' => 'integer',
            'score' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImprovementSignal, $this>
     */
    public function signal(): BelongsTo
    {
        return $this->belongsTo(ImprovementSignal::class, 'signal_id');
    }

    /**
     * @return BelongsTo<ImprovementPipelineRun, $this>
     */
    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(ImprovementPipelineRun::class, 'pipeline_run_id');
    }

    /**
     * @return HasMany<ImprovementExperiment, $this>
     */
    public function experiments(): HasMany
    {
        return $this->hasMany(ImprovementExperiment::class, 'hypothesis_id');
    }
}
