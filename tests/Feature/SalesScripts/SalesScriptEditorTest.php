<?php

namespace Tests\Feature\SalesScripts;

use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesScriptEditorTest extends TestCase
{
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
            'visibility_areas' => json_encode(['dashboard', 'settings_system', 'sales_assistant_scripts'], JSON_THROW_ON_ERROR),
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

    public function test_graph_save_persists_node_tags_and_transition_guidance(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'editor_tags',
            'display_name' => 'Editor tags',
            'visibility_areas' => json_encode(['dashboard', 'settings_system', 'sales_assistant_scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $script = SalesScript::query()->create([
            'title' => 'С тегами',
            'description' => null,
            'channel' => 'phone',
            'tags' => [],
        ]);

        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'published_at' => null,
            'is_active' => false,
            'entry_node_key' => 'start',
        ]);

        $this->actingAs($user)
            ->put(route('scripts.editor.versions.graph.update', $version), [
                'entry_node_key' => 'start',
                'nodes' => [
                    [
                        'client_key' => 'start',
                        'kind' => 'say',
                        'body' => 'Приветствие',
                        'hint' => null,
                        'tags' => ['Квалификация', 'знакомство'],
                        'sort_order' => 0,
                        'canvas_x' => 40,
                        'canvas_y' => 40,
                    ],
                    [
                        'client_key' => 'next',
                        'kind' => 'say',
                        'body' => 'Следующий ход',
                        'hint' => null,
                        'tags' => ['цена'],
                        'sort_order' => 1,
                        'canvas_x' => 300,
                        'canvas_y' => 40,
                    ],
                ],
                'transitions' => [
                    [
                        'from_client_key' => 'start',
                        'to_client_key' => 'next',
                        'target_type' => 'node',
                        'sales_script_reaction_class_id' => null,
                        'customer_label' => 'Дорого',
                        'conversation_effect' => 'risk',
                        'momentum_delta' => -1,
                        'next_move_preview' => 'Сравним одинаковые условия',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('scripts.editor.versions.show', $version));

        $node = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'start')
            ->first();
        $this->assertNotNull($node);
        $this->assertSame(['квалификация', 'знакомство'], $node->tags);

        $transition = SalesScriptTransition::query()
            ->where('sales_script_version_id', $version->id)
            ->firstOrFail();
        $this->assertSame('risk', $transition->conversation_effect);
        $this->assertSame(-1, $transition->momentum_delta);
        $this->assertSame('Сравним одинаковые условия', $transition->next_move_preview);

        $this->actingAs($user)
            ->post(route('scripts.editor.scripts.versions.store', $script), [
                'duplicate_from_version_id' => $version->id,
            ])
            ->assertRedirect();

        $copy = SalesScriptVersion::query()
            ->where('sales_script_id', $script->id)
            ->whereKeyNot($version->id)
            ->firstOrFail();
        $copiedTransition = SalesScriptTransition::query()
            ->where('sales_script_version_id', $copy->id)
            ->firstOrFail();
        $this->assertSame('risk', $copiedTransition->conversation_effect);
        $this->assertSame(-1, $copiedTransition->momentum_delta);
        $this->assertSame('Сравним одинаковые условия', $copiedTransition->next_move_preview);
    }

    public function test_graph_autosave_returns_json_without_redirect(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'editor_autosave',
            'display_name' => 'Editor autosave',
            'visibility_areas' => json_encode(['dashboard', 'settings_system', 'sales_assistant_scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $script = SalesScript::query()->create([
            'title' => 'Автосохранение',
            'description' => null,
            'channel' => 'phone',
            'tags' => [],
        ]);

        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'published_at' => null,
            'is_active' => false,
            'entry_node_key' => 'start',
        ]);

        $this->actingAs($user)
            ->putJson(route('scripts.editor.versions.graph.update', $version), [
                'autosave' => true,
                'entry_node_key' => 'start',
                'nodes' => [
                    [
                        'client_key' => 'start',
                        'kind' => 'say',
                        'body' => 'Текст',
                        'hint' => null,
                        'tags' => [],
                        'sort_order' => 0,
                        'canvas_x' => 10,
                        'canvas_y' => 20,
                    ],
                ],
                'transitions' => [],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $node = SalesScriptNode::query()->where('sales_script_version_id', $version->id)->first();
        $this->assertSame(10, $node?->canvas_x);
        $this->assertSame(20, $node?->canvas_y);
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
