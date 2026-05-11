<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VatRate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'label',
        'rate_percent',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
