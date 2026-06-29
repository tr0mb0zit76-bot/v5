<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialAiSuggestionLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'suggestion_key',
        'user_id',
        'suggestion_type',
        'mail_thread_id',
        'lead_id',
        'rating',
        'comment',
        'payload',
        'rated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'rated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
