<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadProcessStageLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'business_process_stage_id',
        'entered_at',
        'exited_at',
        'due_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<BusinessProcessStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(BusinessProcessStage::class, 'business_process_stage_id');
    }
}
