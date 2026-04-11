<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesBook extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_image',
        'author_id',
        'status',
        'order_index',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order_index' => 'integer',
    ];

    /**
     * Get the author of the book.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the pages of the book.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(SalesBookPage::class);
    }

    /**
     * Get the categories of the book.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(SalesBookCategory::class, 'sales_book_category');
    }
}
