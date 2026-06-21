<?php

namespace Database\Factories;

use App\Models\ProposalHtmlTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProposalHtmlTemplate>
 */
class ProposalHtmlTemplateFactory extends Factory
{
    protected $model = ProposalHtmlTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'is_active' => true,
            'html_body' => '<h1>Коммерческое предложение</h1><p>Клиент: {counterparty.name}</p><p>Маршрут: {route.loading_first_city} → {route.unloading_last_city}</p><p>Ставка: {offer.price} {offer.currency}</p>',
            'css_inline' => 'body{font-family:Arial,sans-serif;padding:24px;color:#111}',
            'version' => 1,
            'published_at' => now(),
            'owner_user_id' => User::factory(),
            'visibility' => 'workspace',
        ];
    }
}
