<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\GetPrintFormTemplatesInsightsTool;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrintFormTemplatesMcpToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'print_form_basic_terms',
            'print_form_templates',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('print_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('entity_type')->default('order');
            $table->string('document_type')->nullable();
            $table->string('party')->default('internal');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('print_form_basic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('party', 16);
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function test_insights_reports_missing_basic_terms_placeholder(): void
    {
        $user = $this->settingsSystemUser();

        PrintFormTemplate::query()->create([
            'code' => 'dz_s_perevozom_RF',
            'name' => 'ДЗ с перевозчиком РФ',
            'party' => PrintFormBasicTerm::PARTY_CARRIER,
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
            'party' => PrintFormBasicTerm::PARTY_CARRIER,
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
            ->assertSee('should_render')
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
