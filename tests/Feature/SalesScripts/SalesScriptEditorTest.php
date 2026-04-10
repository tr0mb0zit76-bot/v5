<?php

namespace Tests\Feature\SalesScripts;

use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptVersion;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesScriptEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id')->nullable()->after('id');
            });
        }
    }

    public function test_manager_with_scripts_only_cannot_open_editor(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'editor_denied',
            'display_name' => 'Editor denied',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('scripts.editor.index'))->assertForbidden();
    }

    public function test_settings_system_user_can_create_script_version_nodes_and_publish(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'editor_allowed',
            'display_name' => 'Editor allowed',
            'visibility_areas' => json_encode(['dashboard', 'settings_system'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('scripts.editor.scripts.store'), [
                'title' => 'Тестовый сценарий',
                'description' => 'Описание',
                'channel' => 'phone',
                'tags' => ['a'],
            ])
            ->assertRedirect(route('scripts.editor.index'));

        $script = SalesScript::query()->where('title', 'Тестовый сценарий')->first();
        $this->assertNotNull($script);

        $this->actingAs($user)
            ->post(route('scripts.editor.scripts.versions.store', $script), [
                'duplicate_from_version_id' => null,
            ])
            ->assertRedirect();

        $version = SalesScriptVersion::query()->where('sales_script_id', $script->id)->first();
        $this->assertNotNull($version);

        $this->actingAs($user)
            ->post(route('scripts.editor.versions.nodes.store', $version), [
                'client_key' => 'start',
                'kind' => 'say',
                'body' => 'Приветствие',
                'hint' => null,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch(route('scripts.editor.versions.update', $version), [
                'entry_node_key' => 'start',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('scripts.editor.versions.publish', $version))
            ->assertRedirect();

        $version->refresh();
        $this->assertTrue($version->is_active);
        $this->assertNotNull($version->published_at);

        $this->assertSame(1, SalesScriptNode::query()->where('sales_script_version_id', $version->id)->count());
    }

    public function test_admin_can_open_editor_index(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('scripts.editor.index'))->assertOk();
    }
}
