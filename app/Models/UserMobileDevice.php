<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMobileDevice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_key',
        'pin_hash',
        'device_name',
        'fcm_token',
        'failed_pin_attempts',
        'pin_locked_until',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin_locked_until' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
