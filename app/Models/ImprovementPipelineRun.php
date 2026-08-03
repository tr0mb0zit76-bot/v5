<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementPipelineRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_NO_DATA = 'no_data';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'signals_used',
        'hypotheses_created',
        'duration_ms',
        'error_summary',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return HasMany<ImprovementHypothesis, $this>
     */
    public function hypotheses(): HasMany
    {
        return $this->hasMany(ImprovementHypothesis::class, 'pipeline_run_id');
    }
}
