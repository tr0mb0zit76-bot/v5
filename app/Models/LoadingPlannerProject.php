<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadingPlannerProject extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'selected_transport_template_id',
        'name',
        'status',
        'calculation',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TransportTemplate, $this>
     */
    public function selectedTransportTemplate(): BelongsTo
    {
        return $this->belongsTo(TransportTemplate::class, 'selected_transport_template_id');
    }

    /**
     * @return HasMany<LoadingCargoGroup, $this>
     */
    public function cargoGroups(): HasMany
    {
        return $this->hasMany(LoadingCargoGroup::class)->orderBy('sort_order');
    }
}
