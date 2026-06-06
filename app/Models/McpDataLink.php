<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpDataLink extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_key',
        'target_key',
        'bidirectional',
        'is_active',
        'label',
        'notes',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bidirectional' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
