<?php

namespace Tests\Feature\SalesScripts;

use App\Models\SalesScriptVersion;
use App\Models\User;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesScriptGraphEditorTest extends TestCase
{
    public function test_manager_can_save_visual_graph_payload(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['scripts', 'settings_system'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRoleId,
            'email_verified_at' => now(),
        ]);

        $version = SalesScriptVersion::query()->firstOrFail();

        $response = $this->actingAs($user)->put(route('scripts.editor.versions.graph.update', $version), [
            'entry_node_key' => 'intro',
            'nodes' => [
                [
                    'client_key' => 'intro',
                    'kind' => 'say',
                    'body' => 'Привет, это новая вступительная реплика.',
                    'hint' => 'Коротко и по делу.',
                    'sort_order' => 0,
                    'canvas_x' => 48,
                    'canvas_y' => 64,
                ],
                [
                    'client_key' => 'qualification',
                    'kind' => 'ask',
                    'body' => 'Как у вас сейчас устроен выбор перевозчиков?',
                    'hint' => null,
                    'sort_order' => 1,
                    'canvas_x' => 410,
                    'canvas_y' => 72,
                ],
            ],
            'transitions' => [
                [
                    'from_client_key' => 'intro',
                    'to_client_key' => 'qualification',
                    'sales_script_reaction_class_id' => null,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('scripts.editor.versions.show', $version));

        $this->assertDatabaseHas('sales_script_nodes', [
            'sales_script_version_id' => $version->id,
            'client_key' => 'intro',
            'canvas_x' => 48,
            'canvas_y' => 64,
        ]);

        $this->assertDatabaseHas('sales_script_nodes', [
            'sales_script_version_id' => $version->id,
            'client_key' => 'qualification',
            'canvas_x' => 410,
            'canvas_y' => 72,
        ]);

        $this->assertDatabaseHas('sales_script_transitions', [
            'sales_script_version_id' => $version->id,
        ]);
    }
}
