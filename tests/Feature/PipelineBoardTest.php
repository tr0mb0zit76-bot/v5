<?php

namespace Tests\Feature;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\PaymentSchedule;
use App\Models\Role;
use App\Models\RoutePoint;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesInTransitOrders;
use Tests\TestCase;

class PipelineBoardTest extends TestCase
{
    use CreatesInTransitOrders;

    public function test_pipeline_index_requires_pipeline_visibility(): void
    {
        $user = $this->makeUser(['dashboard']);

        $this->actingAs($user)
            ->get(route('pipeline.index'))
            ->assertForbidden();
    }

    public function test_pipeline_index_allows_leads_only_role_for_leads_view(): void
    {
        $user = $this->makeUser(['leads'], ['leads' => 'all']);

        $this->actingAs($user)
            ->get(route('pipeline.index', ['view' => 'leads', 'lead_process' => 'transport-intake']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Pipeline/Index')->where('view', 'leads'));
    }

    public function test_orders_board_renders_inertia_with_columns(): void
    {
        $user = $this->makeUser(['pipeline', 'orders'], ['orders' => 'all', 'pipeline' => 'all']);

        $this->createInTransitOrder(['manager_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('pipeline.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pipeline/Index')
                ->where('view', 'orders')
                ->has('columns', 7)
                ->where('columns.0.key', 'order_preparation')
                ->where('columns.1.key', 'in_transit')
                ->has('kpi')
                ->where('kpi.active_orders_count', 1)
            );
    }

    public function test_order_card_shows_overdue_payment_blocker(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('payment_schedules table is not migrated.');
        }

        $user = $this->makeUser(['pipeline', 'orders'], ['orders' => 'all', 'pipeline' => 'all']);

        $order = $this->createInTransitOrder(['manager_id' => $user->id]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 5000,
            'planned_date' => now()->subDays(3)->toDateString(),
            'status' => 'overdue',
        ]);

        $this->actingAs($user)
            ->get(route('pipeline.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('columns', function ($columns): bool {
                    $cards = collect($columns)->flatMap(fn (array $column) => $column['cards'] ?? []);

                    return $cards->contains(
                        fn (array $card): bool => in_array('Просрочен график оплат', $card['blockers'] ?? [], true),
                    );
                }),
            );
    }

    public function test_closed_order_appears_in_closed_column(): void
    {
        $user = $this->makeUser(['pipeline', 'orders'], ['orders' => 'all', 'pipeline' => 'all']);

        $order = $this->createClosedOrder(['manager_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('pipeline.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('columns', function ($columns) use ($order): bool {
                    $closedColumn = collect($columns)->firstWhere('key', 'closed');

                    return $closedColumn !== null
                        && collect($closedColumn['cards'])->contains(
                            fn (array $card): bool => $card['type'] === 'order' && $card['id'] === $order->id,
                        );
                }),
            );
    }

    public function test_accounting_handoff_moves_order_to_accounting_column(): void
    {
        $user = $this->makeUser(['orders', 'finance_salary'], ['orders' => 'all']);

        $order = $this->createClosedOrder(['manager_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('pipeline.orders.accounting-handoff', $order))
            ->assertRedirect(route('pipeline.index', ['view' => 'orders']));

        $order->refresh();

        $this->assertNotNull($order->accounting_handoff_at);
        $this->assertSame($user->id, $order->accounting_handoff_by);

        $this->actingAs($user)
            ->get(route('pipeline.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('columns', function ($columns) use ($order): bool {
                    $handoffColumn = collect($columns)->firstWhere('key', 'accounting_handoff');

                    return $handoffColumn !== null
                        && collect($handoffColumn['cards'])->contains(
                            fn (array $card): bool => $card['id'] === $order->id,
                        );
                }),
            );
    }

    public function test_accounting_handoff_denied_without_finance_salary(): void
    {
        $user = $this->makeUser(['pipeline', 'orders'], ['orders' => 'all', 'pipeline' => 'all']);

        $order = $this->createClosedOrder(['manager_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('pipeline.orders.accounting-handoff', $order))
            ->assertForbidden();
    }

    public function test_pipeline_own_scope_hides_other_managers_orders_even_when_orders_scope_is_all(): void
    {
        $user = $this->makeUser(['pipeline', 'orders'], ['orders' => 'all', 'pipeline' => 'own']);
        $otherManager = User::factory()->create([
            'role_id' => $user->role_id,
            'is_active' => true,
        ]);

        $ownOrder = $this->createInTransitOrder(['manager_id' => $user->id]);
        $foreignOrder = $this->createInTransitOrder(['manager_id' => $otherManager->id]);

        $this->actingAs($user)
            ->get(route('pipeline.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpi.active_orders_count', 1)
                ->where('columns', function ($columns) use ($ownOrder, $foreignOrder): bool {
                    $cardIds = collect($columns)
                        ->flatMap(fn (array $column) => collect($column['cards'] ?? [])
                            ->where('type', 'order')
                            ->pluck('id'));

                    return $cardIds->contains($ownOrder->id) && ! $cardIds->contains($foreignOrder->id);
                }),
            );
    }

    public function test_legacy_orders_visibility_grants_pipeline_access(): void
    {
        $user = $this->makeUser(['orders'], ['orders' => 'all']);

        $this->actingAs($user)
            ->get(route('pipeline.index'))
            ->assertOk();
    }

    public function test_leads_board_groups_cards_by_process_stage(): void
    {
        $user = $this->makeUser(['orders', 'leads'], ['orders' => 'all', 'leads' => 'all']);

        $process = BusinessProcess::query()->where('slug', 'transport-intake')->firstOrFail();
        $stage = BusinessProcessStage::query()
            ->where('business_process_id', $process->id)
            ->orderBy('sequence')
            ->firstOrFail();

        $lead = Lead::factory()->create([
            'responsible_id' => $user->id,
            'status' => 'in_progress',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $stage->id,
        ]);

        $this->actingAs($user)
            ->get(route('pipeline.index', ['view' => 'leads', 'lead_process' => 'transport-intake']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pipeline/Index')
                ->where('view', 'leads')
                ->where('lead_process_slug', 'transport-intake')
                ->has('columns')
                ->where('columns.1.key', 'stage_'.$stage->id)
                ->where('columns.1.cards.0.id', $lead->id)
            );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createClosedOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => 'closed',
            'payment_statuses' => [
                'customer' => ['paid' => true],
                'carrier' => ['paid' => true],
            ],
            'salary_paid' => 100,
        ], $attributes));

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'loading',
            'sequence' => 0,
            'actual_date' => now()->subDay()->toDateString(),
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'actual_date' => now()->toDateString(),
        ]);

        foreach ([
            ['type' => 'request', 'party' => 'customer', 'slot' => 'customer-all'],
            ['type' => 'upd', 'party' => 'customer', 'slot' => 'customer-all'],
            ['type' => 'request', 'party' => 'carrier', 'slot' => 'carrier-empty'],
            ['type' => 'upd', 'party' => 'carrier', 'slot' => 'carrier-empty'],
            ['type' => 'waybill', 'party' => 'internal', 'slot' => 'waybill'],
        ] as $document) {
            OrderDocument::factory()->create([
                'order_id' => $order->id,
                'type' => $document['type'],
                'status' => 'signed',
                'metadata' => [
                    'party' => $document['party'],
                    'requirement_slot_key' => $document['slot'],
                ],
            ]);
        }

        return $order->fresh(['legs.routePoints', 'documents']);
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = []): User
    {
        $role = Role::query()->create([
            'name' => 'pipeline_test_'.uniqid(),
            'display_name' => 'Pipeline Test',
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
