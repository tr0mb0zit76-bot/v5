<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Models\Role;
use App\Models\User;
use App\Support\KpiDeductionCarrierRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesAssistantCounterRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('kpi_deduction_rules')) {
            Schema::create('kpi_deduction_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('priority')->default(100);
                $table->string('customer_payment_form')->nullable();
                $table->boolean('customer_positive_vat_required')->default(false);
                $table->decimal('customer_vat_rate_percent', 5, 2)->nullable();
                $table->string('carrier_rule');
                $table->json('carrier_payment_forms')->nullable();
                $table->decimal('carrier_vat_rate_percent', 5, 2)->nullable();
                $table->decimal('deduction_primary_percent', 5, 2);
                $table->decimal('deduction_secondary_percent', 5, 2)->nullable();
                $table->decimal('margin_supplement_percent', 5, 2)->nullable();
                $table->decimal('margin_supplement_carrier_vat_percent', 5, 2)->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_counter_page_and_calculate_endpoint_require_visibility_area(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            $this->markTestSkipped('Таблицы roles или users недоступны.');
        }

        $role = Role::query()->create([
            'name' => 'counter_tester',
            'display_name' => 'Counter tester',
            'visibility_areas' => ['sales_assistant_counter'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $rule = KpiDeductionRule::query()->create([
            'name' => 'Тест',
            'priority' => 100,
            'customer_payment_form' => 'vat_22',
            'carrier_rule' => KpiDeductionCarrierRule::ALL_CASH,
            'deduction_primary_percent' => 4,
            'deduction_secondary_percent' => 16,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.counter.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get('/modules/counter')
            ->assertRedirect('/sales-assistant/counter');

        $this->actingAs($user)
            ->postJson(route('sales-assistant.counter.calculate'), [
                'kpi_deduction_rule_id' => $rule->id,
                'customer_rate' => 100_000,
                'carrier_rate' => 80_000,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'customer_rate',
                    'carrier_rate',
                    'kpi_deduction_amount',
                    'margin',
                    'margin_percent',
                    'comment',
                ],
            ]);
    }

    public function test_counter_routes_are_forbidden_without_area(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            $this->markTestSkipped('Таблицы roles или users недоступны.');
        }

        $role = Role::query()->create([
            'name' => 'no_counter',
            'display_name' => 'No counter',
            'visibility_areas' => ['dashboard'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get(route('sales-assistant.counter.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('sales-assistant.counter.calculate'), [
                'kpi_deduction_rule_id' => 1,
                'customer_rate' => 100_000,
                'carrier_rate' => 80_000,
            ])
            ->assertForbidden();
    }
}
