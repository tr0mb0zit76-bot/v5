<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesScriptVersion extends Model
{
    protected $table = 'sales_script_versions';

    protected $fillable = [
        'sales_script_id',
        'version_number',
        'published_at',
        'is_active',
        'entry_node_key',
    ];

    /**
     * @return BelongsTo<SalesScript, $this>
     */
    public function script(): BelongsTo
    {
        return $this->belongsTo(SalesScript::class, 'sales_script_id');
    }

    /**
     * @return HasMany<SalesScriptNode, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(SalesScriptNode::class, 'sales_script_version_id');
    }

    /**
     * @return HasMany<SalesScriptTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(SalesScriptTransition::class, 'sales_script_version_id');
    }

    /**
     * @return HasMany<SalesScriptPlaySession, $this>
     */
    public function playSessions(): HasMany
    {
        return $this->hasMany(SalesScriptPlaySession::class, 'sales_script_version_id');
    }

    public function isPublished(): bool
    {
        return $this->is_active && $this->published_at !== null;
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
            'version_number' => 'integer',
        ];
    }
}
