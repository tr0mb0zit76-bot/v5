<?php

namespace App\Models;

use App\Enums\SalesBookArticleFeedbackRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesBookArticleFeedback extends Model
{
    protected $table = 'sales_book_article_feedback';

    protected $fillable = [
        'sales_book_article_id',
        'user_id',
        'rating',
        'comment',
        'source',
        'turn_id',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_book_article_id' => 'integer',
            'user_id' => 'integer',
            'rating' => SalesBookArticleFeedbackRating::class,
            'metadata' => 'array',
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
