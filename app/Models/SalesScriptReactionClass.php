<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesScriptReactionClass extends Model
{
    protected $table = 'sales_script_reaction_classes';

    protected $fillable = [
        'key',
        'label',
        'sort_order',
    ];

    /**
     * @return HasMany<SalesScriptTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(SalesScriptTransition::class, 'sales_script_reaction_class_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
