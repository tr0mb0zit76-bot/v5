<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'permissions',
        'visibility_areas',
        'visibility_scopes',
        'columns_config',
        'has_signing_authority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'visibility_areas' => 'array',
            'visibility_scopes' => 'array',
            'columns_config' => 'array',
            'has_signing_authority' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
