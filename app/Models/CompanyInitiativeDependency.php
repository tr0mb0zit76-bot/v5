<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInitiativeDependency extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_initiative_id',
        'blocked_milestone_id',
        'depends_on_milestone_id',
        'type',
        'notes',
    ];

    /**
     * @return BelongsTo<CompanyInitiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(CompanyInitiative::class, 'company_initiative_id');
    }

    /**
     * @return BelongsTo<CompanyInitiativeMilestone, $this>
     */
    public function blockedMilestone(): BelongsTo
    {
        return $this->belongsTo(CompanyInitiativeMilestone::class, 'blocked_milestone_id');
    }

    /**
     * @return BelongsTo<CompanyInitiativeMilestone, $this>
     */
    public function dependsOnMilestone(): BelongsTo
    {
        return $this->belongsTo(CompanyInitiativeMilestone::class, 'depends_on_milestone_id');
    }
}
