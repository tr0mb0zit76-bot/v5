<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesScript extends Model
{
    protected $table = 'sales_scripts';

    protected $fillable = [
        'title',
        'description',
        'channel',
        'tags',
    ];

    /**
     * @return HasMany<SalesScriptVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SalesScriptVersion::class, 'sales_script_id');
    }

    /**
     * @return HasMany<SalesScriptVersion, $this>
     */
    public function activeVersions(): HasMany
    {
        return $this->versions()->where('is_active', true)->whereNotNull('published_at');
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
