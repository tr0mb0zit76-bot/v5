<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderNumberSegmentType;
use App\Enums\OrderNumberSequenceScope;
use App\Models\Contractor;
use App\Models\OrderNumberingRule;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use App\Services\OrderWizardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderNumberReserveOnSaveTest extends TestCase
{
    public function test_resolve_company_code_only_does_not_bump_sequence_counter(): void
    {
        if (! Schema::hasTable('order_numbering_rules')) {
            $this->markTestSkipped('order_numbering_rules missing');
        }

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ООО Нумератор Тест',
            'inn' => '1000000099',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rule = OrderNumberingRule::query()->create([
            'cipher' => 'AS',
            'own_company_id' => $ownCompanyId,
            'separator' => '-',
            'prefix_type' => OrderNumberSegmentType::Text,
            'prefix_value' => 'АС',
            'body_type' => OrderNumberSegmentType::ManagerInitials,
            'body_value' => null,
            'suffix_type' => OrderNumberSegmentType::Sequence,
            'suffix_value' => null,
            'sequence_pad' => 0,
            'sequence_scope' => OrderNumberSequenceScope::Year,
            'sequence_counters' => ['2026' => 900],
        ]);

        $ownCompany = Contractor::query()->findOrFail($ownCompanyId);
        $code = app(OrderNumberGenerator::class)->resolveCompanyCodeOnly($ownCompany);

        $this->assertSame('AS', $code);
        $this->assertSame(
            900,
            (int) (OrderNumberingRule::query()->findOrFail($rule->id)->sequence_counters['2026'] ?? 0),
        );
    }

    public function test_updating_order_with_existing_number_does_not_burn_sequence(): void
    {
        if (! Schema::hasTable('order_numbering_rules')) {
            $this->markTestSkipped('order_numbering_rules missing');
        }

        $admin = $this->createAdminUser();

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ООО Нумератор Update',
            'inn' => '1000000098',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'inn' => '1000000097',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        OrderNumberingRule::query()->create([
            'cipher' => 'AS',
            'own_company_id' => $ownCompanyId,
            'separator' => '-',
            'prefix_type' => OrderNumberSegmentType::Text,
            'prefix_value' => 'АС',
            'body_type' => OrderNumberSegmentType::Text,
            'body_value' => 'ТД',
            'suffix_type' => OrderNumberSegmentType::Sequence,
            'suffix_value' => null,
            'sequence_pad' => 0,
            'sequence_scope' => OrderNumberSequenceScope::Year,
            'sequence_counters' => ['2026' => 930],
        ]);

        $service = app(OrderWizardService::class);

        $order = $service->create([
            'status' => 'new',
            'own_company_id' => $ownCompanyId,
            'client_id' => $clientId,
            'order_date' => '2026-08-26',
            'order_number' => '',
            'special_notes' => null,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'performers' => [],
            'route_points' => [],
            'cargo_items' => [],
            'client_payment_form' => 'vat',
        ], $admin);

        $ruleAfterCreate = OrderNumberingRule::query()->where('own_company_id', $ownCompanyId)->firstOrFail();
        $counterAfterCreate = (int) ($ruleAfterCreate->sequence_counters['2026'] ?? 0);
        $this->assertSame(931, $counterAfterCreate);
        $this->assertSame('АС-ТД-931', $order->order_number);

        $service->update($order->fresh(), [
            'status' => 'new',
            'own_company_id' => $ownCompanyId,
            'client_id' => $clientId,
            'order_date' => '2026-08-26',
            'order_number' => $order->order_number,
            'special_notes' => 'правка',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'performers' => [],
            'route_points' => [],
            'cargo_items' => [],
            'client_payment_form' => 'vat',
        ], $admin);

        $ruleAfterUpdate = OrderNumberingRule::query()->where('own_company_id', $ownCompanyId)->firstOrFail();
        $this->assertSame(
            931,
            (int) ($ruleAfterUpdate->sequence_counters['2026'] ?? 0),
            'Сохранение существующего номера не должно резервировать новый',
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_number' => 'АС-ТД-931',
        ]);
    }

    public function test_create_with_explicit_number_does_not_burn_extra_sequence(): void
    {
        if (! Schema::hasTable('order_numbering_rules')) {
            $this->markTestSkipped('order_numbering_rules missing');
        }

        $admin = $this->createAdminUser();

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ООО Нумератор Explicit',
            'inn' => '1000000096',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент 2',
            'inn' => '1000000095',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        OrderNumberingRule::query()->create([
            'cipher' => 'AS',
            'own_company_id' => $ownCompanyId,
            'separator' => '-',
            'prefix_type' => OrderNumberSegmentType::Text,
            'prefix_value' => 'АС',
            'body_type' => OrderNumberSegmentType::Text,
            'body_value' => 'ТД',
            'suffix_type' => OrderNumberSegmentType::Sequence,
            'suffix_value' => null,
            'sequence_pad' => 0,
            'sequence_scope' => OrderNumberSequenceScope::Year,
            'sequence_counters' => ['2026' => 940],
        ]);

        $order = app(OrderWizardService::class)->create([
            'status' => 'new',
            'own_company_id' => $ownCompanyId,
            'client_id' => $clientId,
            'order_date' => '2026-08-26',
            'order_number' => 'АС-ТД-941',
            'special_notes' => null,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'performers' => [],
            'route_points' => [],
            'cargo_items' => [],
            'client_payment_form' => 'vat',
        ], $admin);

        $this->assertSame('АС-ТД-941', $order->order_number);
        $this->assertSame(
            941,
            (int) (OrderNumberingRule::query()->where('own_company_id', $ownCompanyId)->firstOrFail()->sequence_counters['2026'] ?? 0),
            'Явный номер из превью должен подтянуть счётчик до своего sequence, без лишнего +1',
        );
    }

    private function createAdminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['orders', 'dashboard', 'settings', 'contractors']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'has_signing_authority' => true,
        ]);

        DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
        $user->role_id = $roleId;

        return $user;
    }
}
