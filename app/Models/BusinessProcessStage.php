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
        'auto_create_task',
        'task_title_template',
        'task_description_template',
        'task_due_days_offset',
        'task_priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
            'auto_create_task' => 'boolean',
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
