<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImprovementExperiment extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const VERDICT_ADOPT_B = 'adopt_b';

    public const VERDICT_KEEP_A = 'keep_a';

    public const VERDICT_INCONCLUSIVE = 'inconclusive';

    public const ASSIGNMENT_MANAGERS = 'managers';

    public const ASSIGNMENT_LEADS = 'leads';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'hypothesis_id',
        'name',
        'status',
        'variant_a',
        'variant_b',
        'metric_key',
        'assignment_mode',
        'starts_on',
        'ends_on',
        'cohort',
        'result_snapshot',
        'verdict',
        'verdict_note',
        'created_by',
        'decided_by',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant_a' => 'array',
            'variant_b' => 'array',
            'cohort' => 'array',
            'result_snapshot' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImprovementHypothesis, $this>
     */
    public function hypothesis(): BelongsTo
    {
        return $this->belongsTo(ImprovementHypothesis::class, 'hypothesis_id');
    }

    /**
     * @return HasOne<ImprovementAdoption, $this>
     */
    public function adoption(): HasOne
    {
        return $this->hasOne(ImprovementAdoption::class, 'experiment_id');
    }

    /**
     * @return HasMany<ImprovementExperimentAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ImprovementExperimentAssignment::class, 'experiment_id');
    }
}
