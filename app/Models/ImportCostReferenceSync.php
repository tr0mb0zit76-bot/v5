<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportCostReferenceSync extends Model
{
    protected $fillable = [
        'source',
        'status',
        'items_updated',
        'message',
        'meta',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'items_updated' => 'integer',
            'meta' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public static function latestForSource(string $source): ?self
    {
        return self::query()
            ->where('source', $source)
            ->orderByDesc('synced_at')
            ->first();
    }
}
