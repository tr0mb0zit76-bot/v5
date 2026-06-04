<?php

namespace App\Models;

use App\Enums\OrderIntakePhraseField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIntakePhraseLearning extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'field',
        'source_phrase',
        'canonical_value',
        'use_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'use_count' => 'integer',
            'field' => OrderIntakePhraseField::class,
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
