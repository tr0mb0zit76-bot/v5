<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'version',
        'is_enabled',  // Изменено с enabled на is_enabled
        'order',
        'dependencies',
        'settings',
    ];
    
    protected $casts = [
        'is_enabled' => 'boolean',  // Изменено с enabled на is_enabled
        'dependencies' => 'array',
        'settings' => 'array',
    ];
    
    // Добавим аксессор для обратной совместимости
    public function getEnabledAttribute()
    {
        return $this->is_enabled;
    }
}