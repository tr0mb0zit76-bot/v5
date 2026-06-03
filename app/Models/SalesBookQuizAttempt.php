<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesBookQuizAttempt extends Model
{
    protected $fillable = [
        'sales_book_article_id',
        'user_id',
        'score',
        'total_questions',
        'answers',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_book_article_id' => 'integer',
            'user_id' => 'integer',
            'score' => 'integer',
            'total_questions' => 'integer',
            'answers' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SalesBookArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(SalesBookArticle::class, 'sales_book_article_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
