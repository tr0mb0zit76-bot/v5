<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\PrintFormTemplateTransportScope;
use App\Support\RoleAccess;
use Tests\TestCase;

class PrintFormTemplateOrderEligibilityTest extends TestCase
{
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

    public function test_template_to_array_flags_basic_terms_placeholders(): void
    {
        $service = app(PrintFormTemplateOrderEligibility::class);

        $customerTemplate = PrintFormTemplate::query()->create([
            'name' => 'Заявка с условиями заказчика',
            'code' => 'customer-basic-terms',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'is_active' => true,
            'file_path' => 'print-forms/customer-basic-terms.docx',
            'file_disk' => 'local',
            'settings' => [
                'variables' => ['cp_basic_terms_row_text', 'order_number'],
            ],
        ]);

        $carrierTemplate = PrintFormTemplate::query()->create([
            'name' => 'Заявка с условиями перевозчика',
            'code' => 'carrier-basic-terms',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'is_active' => true,
            'file_path' => 'print-forms/carrier-basic-terms.docx',
            'file_disk' => 'local',
            'settings' => [
                'variables' => ['dp_basic_terms_row_text#1'],
            ],
        ]);

        $plainTemplate = PrintFormTemplate::query()->create([
            'name' => 'Заявка без условий',
            'code' => 'plain-request',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'is_active' => true,
            'file_path' => 'print-forms/plain-request.docx',
            'file_disk' => 'local',
            'settings' => [
                'variables' => ['order_number'],
            ],
        ]);

        $customerArray = $service->templateToArray($customerTemplate);
        $carrierArray = $service->templateToArray($carrierTemplate);
        $plainArray = $service->templateToArray($plainTemplate);

        $this->assertTrue($customerArray['has_customer_basic_terms']);
        $this->assertFalse($customerArray['has_carrier_basic_terms']);
        $this->assertTrue($carrierArray['has_carrier_basic_terms']);
        $this->assertFalse($plainArray['has_customer_basic_terms']);
        $this->assertFalse($plainArray['has_carrier_basic_terms']);
    }

    public function test_carrier_party_template_is_not_available_for_customer_print_slot(): void
    {
        $order = Order::factory()->create();

        $template = PrintFormTemplate::query()->create([
            'name' => 'Заявка перевозчику',
            'code' => 'carrier-only-request',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'is_active' => true,
            'file_path' => 'print-forms/carrier-only.docx',
            'file_disk' => 'local',
        ]);

        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $this->assertFalse($eligibility->isTemplateAvailableForOrder($template, $order, 'customer'));
        $this->assertTrue($eligibility->isTemplateAvailableForOrder($template, $order, 'carrier'));
    }

    public function test_internal_carrier_basic_terms_template_is_not_available_for_customer_print_slot(): void
    {
        $order = Order::factory()->create([
            'is_international_transport' => true,
        ]);

        $template = PrintFormTemplate::query()->create([
            'name' => 'ВЭД перевозчик (внутренняя)',
            'code' => 'internal-carrier-ved',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'system',
            'vue_component' => 'SystemPrintFormTemplate',
            'transport_scope' => PrintFormTemplateTransportScope::INTERNATIONAL,
            'is_active' => true,
            'file_path' => 'print-forms/internal-carrier-ved.docx',
            'file_disk' => 'local',
            'settings' => [
                'variables' => ['dp_basic_terms_row_text', 'route_row_stage'],
            ],
        ]);

        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $this->assertFalse($eligibility->isTemplateAvailableForOrder($template, $order, 'customer'));
        $this->assertTrue($eligibility->isTemplateAvailableForOrder($template, $order, 'carrier'));
    }
}
