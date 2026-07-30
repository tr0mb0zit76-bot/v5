<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HttpErrorPagesTest extends TestCase
{
    public function test_forbidden_page_shows_readable_russian_message(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'no_sales_assistant_403_page',
            'display_name' => 'No sales assistant 403 page',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book'))
            ->assertForbidden()
            ->assertSee('У вас нет прав доступа', false)
            ->assertSee('Этот раздел или действие недоступны для вашей роли', false);
    }
}
