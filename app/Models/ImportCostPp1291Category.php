<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportCostPp1291Category extends Model
{
    protected $fillable = [
        'key',
        'name',
        'base_fee_rub',
        'age_coefficients',
        'decree_reference',
        'effective_from',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'base_fee_rub' => 'integer',
            'age_coefficients' => 'array',
            'effective_from' => 'date',
            'synced_at' => 'datetime',
        ];
    }
}
