<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtiDictionaryItem extends Model
{
    protected $fillable = [
        'dictionary',
        'ati_id',
        'code',
        'label',
        'is_active',
        'raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ati_id' => 'integer',
            'is_active' => 'boolean',
            'raw' => 'array',
        ];
    }
}
