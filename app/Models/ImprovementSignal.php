<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementSignal extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_LINKED = 'linked';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'domain',
        'kind',
        'severity',
        'title',
        'payload',
        'period_from',
        'period_to',
        'source',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'period_from' => 'date',
            'period_to' => 'date',
        ];
    }

    /**
     * @return HasMany<ImprovementHypothesis, $this>
     */
    public function hypotheses(): HasMany
    {
        return $this->hasMany(ImprovementHypothesis::class, 'signal_id');
    }
}
