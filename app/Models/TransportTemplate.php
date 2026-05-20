<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportTemplate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'name',
        'category',
        'length_mm',
        'width_mm',
        'height_mm',
        'max_payload_kg',
        'axles_count',
        'is_active',
        'is_system',
        'sort_order',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
