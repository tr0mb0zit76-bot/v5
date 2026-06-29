<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\Role;
use App\Models\User;
use App\Services\Pipeline\PipelineKpiService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesInTransitOrders;
use Tests\TestCase;

class PipelineKpiServiceTest extends TestCase
{
    use CreatesInTransitOrders;

    public function test_metrics_include_overdue_payments_percent(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('payment_schedules table is not migrated.');
        }

        $user = $this->makeUser(['orders'], ['orders' => 'all']);

        $withOverdue = $this->createInTransitOrder(['manager_id' => $user->id]);
        $withoutOverdue = $this->createInTransitOrder(['manager_id' => $user->id]);

        PaymentSchedule::query()->create([
            'order_id' => $withOverdue->id,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 1000,
            'planned_date' => now()->subDays(10)->toDateString(),
            'status' => 'overdue',
        ]);

        $metrics = app(PipelineKpiService::class)->metricsForUser($user);

        $this->assertSame(2, $metrics['active_orders_count']);
        $this->assertSame(1, $metrics['orders_with_overdue_payments']);
        $this->assertSame(50.0, $metrics['overdue_payments_percent']);
    }

    public function test_metrics_average_lead_to_order_days(): void
    {
        $user = $this->makeUser(['orders'], ['orders' => 'all']);

        $lead = Lead::factory()->create([
            'responsible_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);

        Order::factory()->create([
            'manager_id' => $user->id,
            'lead_id' => $lead->id,
            'created_at' => now()->subDays(4),
        ]);

        $metrics = app(PipelineKpiService::class)->metricsForUser($user);

        $this->assertSame(1, $metrics['linked_leads_count']);
        $this->assertSame(6.0, $metrics['avg_lead_to_order_days']);
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = []): User
    {
        $role = Role::query()->create([
            'name' => 'pipeline_kpi_test_'.uniqid(),
            'display_name' => 'Pipeline KPI Test',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
