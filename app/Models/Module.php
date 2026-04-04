<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'version',
        'is_enabled',
        'order',
        'dependencies',
        'settings',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'dependencies' => 'array',
        'settings' => 'array',
    ];

    public function getEnabledAttribute(): bool
    {
        return (bool) $this->is_enabled;
    }

    public function setEnabledAttribute(bool $value): void
    {
        $this->attributes['is_enabled'] = $value;
    }
}
