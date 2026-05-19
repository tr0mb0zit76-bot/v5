<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessProcessStage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_process_id',
        'name',
        'description',
        'sequence',
        'duration_days',
        'is_terminal',
        'terminal_outcome',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BusinessProcess, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(BusinessProcess::class, 'business_process_id');
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leadsOnStage(): HasMany
    {
        return $this->hasMany(Lead::class, 'business_process_stage_id');
    }
}
