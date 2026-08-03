<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImprovementAdoption extends Model
{
    public const TARGET_MANUAL_NOTE = 'manual_note';

    public const TARGET_TASK = 'task';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'experiment_id',
        'hypothesis_id',
        'target_type',
        'target_id',
        'summary',
        'meta',
        'adopted_by',
        'adopted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adopted_at' => 'datetime',
            'meta' => 'array',
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
     * @return BelongsTo<ImprovementHypothesis, $this>
     */
    public function hypothesis(): BelongsTo
    {
        return $this->belongsTo(ImprovementHypothesis::class, 'hypothesis_id');
    }
}
