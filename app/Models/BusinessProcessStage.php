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
        'stage_goal',
        'success_criteria',
        'sales_script_id',
        'sequence',
        'duration_days',
        'is_terminal',
        'terminal_outcome',
        'auto_create_task',
        'task_title_template',
        'task_description_template',
        'task_due_days_offset',
        'task_priority',
        'no_reply_nudge_days',
        'nudge_triggers',
        'ledger_idle_nudge_days',
        'automated_actions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
            'auto_create_task' => 'boolean',
            'nudge_triggers' => 'array',
            'automated_actions' => 'array',
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
     * @return BelongsTo<SalesScript, $this>
     */
    public function salesScript(): BelongsTo
    {
        return $this->belongsTo(SalesScript::class, 'sales_script_id');
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leadsOnStage(): HasMany
    {
        return $this->hasMany(Lead::class, 'business_process_stage_id');
    }
}
