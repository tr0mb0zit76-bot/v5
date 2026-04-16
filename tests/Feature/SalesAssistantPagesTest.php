<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesAssistantPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_sales_assistant_book(): void
    {
        $this->get(route('sales-assistant.book'))->assertRedirect();
    }

    public function test_user_without_scripts_area_cannot_access_sales_assistant_pages(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'no_sales_assistant',
            'display_name' => 'No sales assistant',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('sales-assistant.book'))->assertForbidden();
        $this->actingAs($user)->get(route('sales-assistant.trainer'))->assertForbidden();
    }

    public function test_user_with_scripts_area_can_open_sales_assistant_stubs(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'with_scripts_stub',
            'display_name' => 'With scripts',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('sales-assistant.book'))->assertOk();
        $this->actingAs($user)->get(route('sales-assistant.trainer'))->assertOk();
    }
}
