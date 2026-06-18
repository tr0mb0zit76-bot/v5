<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportCostTnVedEntry extends Model
{
    protected $fillable = [
        'code',
        'code_display',
        'label',
        'duty_percent',
        'vat_percent',
        'pp1291_category_key',
        'requires_utilization_fee',
        'duty_source',
        'eec_payload',
        'eec_synced_at',
        'kodtnved_payload',
        'kodtnved_synced_at',
        'alta_payload',
        'alta_synced_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duty_percent' => 'float',
            'vat_percent' => 'float',
            'requires_utilization_fee' => 'boolean',
            'eec_payload' => 'array',
            'eec_synced_at' => 'datetime',
            'kodtnved_payload' => 'array',
            'kodtnved_synced_at' => 'datetime',
            'alta_payload' => 'array',
            'alta_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
