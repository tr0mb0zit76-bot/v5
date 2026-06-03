<?php

namespace App\Models;

use App\Enums\SalesBookArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesBookArticle extends Model
{
    protected $fillable = [
        'parent_id',
        'title',
        'markdown_content',
        'sort_order',
        'status',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => 'published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
            'status' => SalesBookArticleStatus::class,
            'tags' => 'array',
        ];
    }

    /**
     * @param  Builder<SalesBookArticle>  $query
     * @return Builder<SalesBookArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', SalesBookArticleStatus::Published->value);
    }

    /**
     * @return BelongsTo<SalesBookArticle, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<SalesBookArticle, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
