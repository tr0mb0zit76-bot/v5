<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\PrintFormTemplateTransportScope;
use App\Support\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintFormTemplateOrderEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_international_carrier_template_available_when_order_flagged_ved(): void
    {
        $order = Order::factory()->create([
            'is_international_transport' => true,
        ]);

        $template = PrintFormTemplate::query()->create([
            'name' => 'Заявка с перевозчиком ВЭД',
            'code' => 'ved-carrier-request',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'transport_scope' => PrintFormTemplateTransportScope::INTERNATIONAL,
            'is_active' => true,
            'is_default' => true,
            'file_path' => 'templates/ved-carrier.docx',
            'file_disk' => 'local',
        ]);

        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $this->assertTrue($eligibility->isTemplateAvailableForOrder($template, $order, 'carrier'));
    }

    public function test_international_carrier_template_hidden_for_domestic_order(): void
    {
        $order = Order::factory()->create([
            'is_international_transport' => false,
        ]);

        $template = PrintFormTemplate::query()->create([
            'name' => 'Заявка с перевозчиком ВЭД',
            'code' => 'ved-carrier-request-domestic',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'transport_scope' => PrintFormTemplateTransportScope::INTERNATIONAL,
            'is_active' => true,
            'file_path' => 'templates/ved-carrier.docx',
            'file_disk' => 'local',
        ]);

        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $this->assertFalse($eligibility->isTemplateAvailableForOrder($template, $order, 'carrier'));
    }

    public function test_international_carrier_template_available_from_wizard_state_snapshot(): void
    {
        $order = Order::factory()->create([
            'is_international_transport' => false,
            'wizard_state' => [
                'is_international_transport' => true,
            ],
        ]);

        $template = PrintFormTemplate::query()->create([
            'name' => 'Заявка с перевозчиком ВЭД',
            'code' => 'ved-carrier-request-wizard',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'transport_scope' => PrintFormTemplateTransportScope::INTERNATIONAL,
            'is_active' => true,
            'file_path' => 'templates/ved-carrier.docx',
            'file_disk' => 'local',
        ]);

        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $this->assertTrue($eligibility->isTemplateAvailableForOrder($template, $order, 'carrier'));
    }

    public function test_user_has_role_name_uses_primary_role_when_pivot_empty(): void
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'permissions' => [],
            'visibility_areas' => RoleAccess::visibilityAreaKeys(),
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $user->setRelation('roles', collect());

        $this->assertTrue(RoleAccess::userHasRoleName($user, 'admin'));
        $this->assertTrue($user->isAdmin());
    }
}
