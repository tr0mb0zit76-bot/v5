<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\GetPrintFormTemplatesInsightsTool;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrintFormTemplatesMcpToolsTest extends TestCase
{
    public function test_insights_reports_missing_basic_terms_placeholder(): void
    {
        $user = $this->settingsSystemUser();

        PrintFormTemplate::query()->create([
            'code' => 'dz_s_perevozom_RF',
            'name' => 'ДЗ с перевозчиком РФ',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => PrintFormBasicTerm::PARTY_CARRIER,
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/1/test.docx',
            'settings' => [
                'variables' => ['order.number', 'carrier.name'],
            ],
        ]);

        DB::table('print_form_basic_terms')->insert([
            'party' => PrintFormBasicTerm::PARTY_CARRIER,
            'contractor_id' => null,
            'sort_order' => 1,
            'body' => 'Пункт 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = CrmServer::actingAs($user)->tool(GetPrintFormTemplatesInsightsTool::class, [
            'code' => 'dz_s_perevozom_RF',
        ]);

        $response
            ->assertOk()
            ->assertSee('dz_s_perevozom_RF')
            ->assertSee('missing_basic_terms_placeholder')
            ->assertSee('global_basic_terms_present')
            ->assertSee('dp_basic_terms_row_text');
    }

    public function test_insights_ok_when_placeholder_and_terms_present(): void
    {
        $user = $this->settingsSystemUser();

        PrintFormTemplate::query()->create([
            'code' => 'dz_s_perevozom_RF',
            'name' => 'ДЗ с перевозчиком РФ',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => PrintFormBasicTerm::PARTY_CARRIER,
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/1/test.docx',
            'settings' => [
                'variables' => ['dp_basic_terms_row_index', 'dp_basic_terms_row_text', 'order.number'],
            ],
        ]);

        DB::table('print_form_basic_terms')->insert([
            [
                'party' => PrintFormBasicTerm::PARTY_CARRIER,
                'contractor_id' => null,
                'sort_order' => 1,
                'body' => 'Пункт 1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'party' => PrintFormBasicTerm::PARTY_CARRIER,
                'contractor_id' => null,
                'sort_order' => 2,
                'body' => 'Пункт 2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = CrmServer::actingAs($user)->tool(GetPrintFormTemplatesInsightsTool::class, [
            'code' => 'dz_s_perevozom_RF',
        ]);

        $response
            ->assertOk()
            ->assertSee('basic_terms_placeholder_found')
            ->assertSee('"global_count":2', false);
    }

    private function settingsSystemUser(): User
    {
        $role = Role::query()->create([
            'name' => 'mcp_settings_'.uniqid(),
            'display_name' => 'MCP Settings',
            'permissions' => [],
            'visibility_areas' => ['settings_system'],
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
