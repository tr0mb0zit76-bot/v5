<?php

namespace Database\Factories;

use App\Models\GridView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GridView>
 */
class GridViewFactory extends Factory
{
    protected $model = GridView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grid_key' => 'orders',
            'name' => fake()->words(2, true),
            'owner_user_id' => User::factory(),
            'visibility' => 'private',
            'shared_with' => null,
            'column_state' => [
                ['colId' => 'order_number', 'hide' => false, 'width' => 120, 'order' => 0],
            ],
            'filter_state' => [],
            'sort_state' => null,
            'quick_search' => null,
            'is_pinned_sidebar' => false,
            'sort_order' => 10,
        ];
    }
}
