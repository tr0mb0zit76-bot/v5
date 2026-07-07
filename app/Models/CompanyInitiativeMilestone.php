<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyInitiativeMilestone extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_initiative_id',
        'responsible_id',
        'task_id',
        'title',
        'description',
        'done_criteria',
        'status',
        'priority',
        'starts_on',
        'ends_on',
        'completed_on',
        'progress_percent',
        'sort_order',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'completed_on' => 'date',
            'progress_percent' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CompanyInitiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(CompanyInitiative::class, 'company_initiative_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
