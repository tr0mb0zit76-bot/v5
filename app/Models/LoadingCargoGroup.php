<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadingCargoGroup extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'loading_planner_project_id',
        'name',
        'recipient_name',
        'color',
        'sort_order',
    ];

    /**
     * @return BelongsTo<LoadingPlannerProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(LoadingPlannerProject::class, 'loading_planner_project_id');
    }

    /**
     * @return HasMany<LoadingCargoItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LoadingCargoItem::class)->orderBy('sort_order');
    }
}
