<?php

namespace Tests\Feature\SalesScripts;

use App\Models\SalesScriptVersion;
use App\Models\User;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesScriptAnalyticsPageTest extends TestCase
{
    public function test_script_manager_can_open_version_analytics_page(): void
    {
        if (! Schema::hasTable('sales_script_versions')) {
            $this->markTestSkipped('Sales scripts tables unavailable.');
        }

        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'scripts_analytics_manager',
            'display_name' => 'Scripts analytics',
            'visibility_areas' => json_encode(['dashboard', 'scripts', 'settings_system'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $version = SalesScriptVersion::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('scripts.editor.versions.analytics', $version))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesScripts/Editor/Analytics')
                ->has('report.reaction_matrix')
                ->has('report.top_reactions'));
    }

    public function test_analytics_csv_export_returns_attachment(): void
    {
        if (! Schema::hasTable('sales_script_versions')) {
            $this->markTestSkipped('Sales scripts tables unavailable.');
        }

        $this->seed(SalesScriptsDemoSeeder::class);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'scripts_analytics_export',
            'display_name' => 'Scripts analytics export',
            'visibility_areas' => json_encode(['dashboard', 'scripts', 'settings_system'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $version = SalesScriptVersion::query()->firstOrFail();

        $response = $this->actingAs($user)
            ->get(route('scripts.editor.versions.analytics.export', $version));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('node_key', (string) $response->getContent());
    }
}
