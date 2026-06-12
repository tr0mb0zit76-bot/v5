<?php

namespace App\Models;

use Database\Factories\GridViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GridView extends Model
{
    /** @use HasFactory<GridViewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grid_key',
        'name',
        'owner_user_id',
        'visibility',
        'shared_with',
        'column_state',
        'filter_state',
        'sort_state',
        'quick_search',
        'is_pinned_sidebar',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shared_with' => 'array',
            'column_state' => 'array',
            'filter_state' => 'array',
            'sort_state' => 'array',
            'is_pinned_sidebar' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
