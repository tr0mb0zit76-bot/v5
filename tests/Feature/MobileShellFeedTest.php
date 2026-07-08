<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileShellFeedTest extends TestCase
{
    private function createUserWithAreas(array $areas, array $scopes = []): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'mobile-shell-'.uniqid(),
            'display_name' => 'Mobile Shell',
            'visibility_areas' => json_encode($areas),
            'visibility_scopes' => json_encode($scopes),
            'columns_config' => json_encode([]),
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }

    public function test_mobile_shell_tasks_returns_open_tasks_for_responsible_user(): void
    {
        $user = $this->createUserWithAreas(['tasks'], ['tasks' => 'own']);
        $other = User::factory()->create();

        Task::query()->create([
            'number' => 'TSK-MOB-1',
            'title' => 'Моя открытая задача',
            'status' => 'in_progress',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'number' => 'TSK-MOB-2',
            'title' => 'Чужая задача',
            'status' => 'in_progress',
            'priority' => 'medium',
            'responsible_id' => $other->id,
            'created_by' => $other->id,
        ]);

        Task::query()->create([
            'number' => 'TSK-MOB-3',
            'title' => 'Завершённая',
            'status' => 'done',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('mobile.shell.tasks'))
            ->assertOk()
            ->assertJsonPath('overdue_count', 0)
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Моя открытая задача');
    }

    public function test_mobile_shell_orders_returns_recent_orders_for_manager(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'MOB-1001',
            'is_active' => true,
        ]);

        Order::factory()->create([
            'manager_id' => User::factory()->create()->id,
            'order_number' => 'MOB-9999',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.orders'))
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.order_number', 'MOB-1001')
            ->assertJsonStructure([
                'orders' => [[
                    'documents_pending_count',
                    'documents_total_count',
                    'documents_url',
                ]],
            ]);
    }

    public function test_mobile_shell_order_summary_returns_document_checklist(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'MOB-SUM-1',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.orders.summary', $order))
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.order_number', 'MOB-SUM-1')
            ->assertJsonStructure([
                'order',
                'documents' => ['pending_count', 'completed_count', 'total_count', 'pending'],
                'urls' => ['order', 'documents'],
            ]);
    }

    public function test_mobile_shell_order_summary_forbidden_for_other_manager(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);
        $other = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->getJson(route('mobile.shell.orders.summary', $order))
            ->assertForbidden();
    }

    public function test_mobile_shell_documents_returns_recent_document_chips(): void
    {
        $user = $this->createUserWithAreas(['orders', 'documents'], ['orders' => 'own', 'documents' => 'own']);

        $this->actingAs($user)
            ->getJson(route('mobile.shell.documents'))
            ->assertOk()
            ->assertJsonStructure([
                'recent',
                'attention',
            ]);
    }

    public function test_mobile_shell_traklo_leads_returns_unassigned_public_requests_for_leads_user(): void
    {
        $user = $this->createUserWithAreas(['leads'], ['leads' => 'own']);
        $other = User::factory()->create();

        $visibleLead = Lead::factory()->create([
            'number' => 'TRK-MOB-1',
            'source' => 'traklo_public_request',
            'responsible_id' => null,
            'title' => 'Перевозка станка',
            'metadata' => [
                'public_transport_request' => [
                    'contact_name' => 'Иван',
                    'phone' => '+79990000000',
                    'cargo' => 'Станок',
                ],
            ],
        ]);

        Lead::factory()->create([
            'number' => 'TRK-MOB-2',
            'source' => 'traklo_public_request',
            'responsible_id' => $other->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('mobile.shell.traklo-leads'))
            ->assertOk()
            ->assertJsonCount(1, 'leads')
            ->assertJsonPath('leads.0.id', $visibleLead->id)
            ->assertJsonPath('leads.0.number', 'TRK-MOB-1')
            ->assertJsonPath('leads.0.contact_name', 'Иван')
            ->assertJsonPath('leads.0.cargo', 'Станок');
    }

    public function test_mobile_shell_lead_summary_allows_unassigned_public_traklo_request(): void
    {
        $user = $this->createUserWithAreas(['leads'], ['leads' => 'own']);
        $lead = Lead::factory()->create([
            'source' => 'traklo_public_request',
            'responsible_id' => null,
            'title' => 'Публичная заявка Traklo',
        ]);

        $this->actingAs($user)
            ->getJson(route('mobile.shell.leads.summary', $lead))
            ->assertOk()
            ->assertJsonPath('lead.id', $lead->id)
            ->assertJsonPath('lead.title', 'Публичная заявка Traklo');
    }

    public function test_mobile_shell_creates_lead_from_pasted_message_text(): void
    {
        $user = $this->createUserWithAreas(['leads'], ['leads' => 'own']);

        $this->actingAs($user)
            ->postJson(route('mobile.shell.leads.from-text'), [
                'message' => 'Прошу рассчитать стоимость перевозки из Смоленска в Москву, груз паллеты 3 тонны, телефон +7 999 000 11 22',
            ])
            ->assertCreated()
            ->assertJsonPath('parsed.loading_location', 'Смоленска')
            ->assertJsonPath('parsed.unloading_location', 'Москву')
            ->assertJsonPath('parsed.cargo', 'паллеты 3 тонны')
            ->assertJsonPath('parsed.phone', '+7 999 000 11 22')
            ->assertJsonPath('parsed.parser', 'heuristic');

        $lead = Lead::query()
            ->where('source', 'traklo_message_intake')
            ->latest('id')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame($user->id, $lead->responsible_id);
        $this->assertSame('Смоленска', $lead->loading_location);
        $this->assertSame('Москву', $lead->unloading_location);
        $this->assertSame('паллеты 3 тонны', data_get($lead->metadata, 'traklo_message_intake.cargo'));
    }

    public function test_mobile_shell_updates_traklo_lead_draft_without_assigning_public_lead(): void
    {
        $user = $this->createUserWithAreas(['leads'], ['leads' => 'own']);
        $lead = Lead::factory()->create([
            'source' => 'traklo_public_request',
            'responsible_id' => null,
            'loading_location' => 'Казань',
            'unloading_location' => 'Уфа',
            'metadata' => [
                'public_transport_request' => [
                    'contact_name' => 'Пётр',
                    'phone' => '+7 900 111 22 33',
                    'cargo' => 'металл',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->patchJson(route('mobile.shell.leads.update', $lead), [
                'loading_location' => 'Казань-1',
                'unloading_location' => 'Уфа-2',
                'cargo' => 'металлопрокат',
                'phone' => '+7 900 111 22 44',
            ])
            ->assertOk()
            ->assertJsonPath('lead.loading_location', 'Казань-1')
            ->assertJsonPath('lead.unloading_location', 'Уфа-2')
            ->assertJsonPath('lead.cargo', 'металлопрокат')
            ->assertJsonPath('lead.phone', '+7 900 111 22 44')
            ->assertJsonPath('lead.editable', true);

        $lead->refresh();

        $this->assertNull($lead->responsible_id);
        $this->assertSame('металлопрокат', data_get($lead->metadata, 'public_transport_request.cargo'));
        $this->assertSame('+7 900 111 22 44', data_get($lead->metadata, 'public_transport_request.phone'));
    }

    public function test_mobile_shell_document_contractors_returns_counterparties_from_manager_orders(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);
        $other = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $customer = Contractor::query()->create([
            'name' => 'ООО Мобильный клиент',
            'inn' => '7700000001',
        ]);

        Order::factory()->create([
            'manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'order_number' => 'MOB-DOC-1',
            'is_active' => true,
        ]);

        Order::factory()->create([
            'manager_id' => $other->id,
            'customer_id' => $customer->id,
            'order_number' => 'MOB-DOC-OTHER',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.documents.contractors'))
            ->assertOk()
            ->assertJsonCount(1, 'contractors')
            ->assertJsonPath('contractors.0.id', $customer->id)
            ->assertJsonPath('contractors.0.name', 'ООО Мобильный клиент')
            ->assertJsonPath('contractors.0.orders_count', 1);
    }

    public function test_mobile_shell_document_contractor_orders_scoped_to_contractor_and_manager(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $customer = Contractor::query()->create([
            'name' => 'ООО Документы Traklo',
            'inn' => '7700000002',
        ]);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'order_number' => 'MOB-DOC-ORD-1',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.documents.contractor-orders', $customer))
            ->assertOk()
            ->assertJsonPath('contractor.id', $customer->id)
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $order->id)
            ->assertJsonPath('orders.0.order_number', 'MOB-DOC-ORD-1')
            ->assertJsonStructure([
                'orders' => [[
                    'documents_pending_count',
                    'documents_total_count',
                ]],
            ]);
    }

    public function test_mobile_shell_order_document_checklist_forbidden_for_other_manager(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);
        $other = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->getJson(route('mobile.shell.documents.order-checklist', $order))
            ->assertForbidden();
    }

    public function test_mobile_shell_order_document_checklist_returns_slots_for_manager(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'MOB-DOC-CHK-1',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.documents.order-checklist', $order))
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.order_number', 'MOB-DOC-CHK-1')
            ->assertJsonStructure([
                'order',
                'documents' => ['pending_count', 'completed_count', 'total_count'],
                'slots',
                'urls' => ['order', 'documents'],
            ]);
    }

    public function test_mobile_shell_documents_drill_down_chain_for_manager_order(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $customer = Contractor::query()->create([
            'name' => 'ООО Drill Down Chain',
            'inn' => '7700000099',
        ]);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'order_number' => 'MOB-DRILL-1',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.documents.contractors'))
            ->assertOk()
            ->assertJsonPath('contractors.0.id', $customer->id);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.documents.contractor-orders', $customer))
            ->assertOk()
            ->assertJsonPath('orders.0.id', $order->id);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.documents.order-checklist', $order))
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonStructure(['slots', 'documents', 'urls']);
    }

    public function test_mobile_shell_link_preview_returns_order_number(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'MOB-LINK-42',
            'is_active' => true,
        ]);

        $url = route('orders.edit', $order, absolute: true);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.link-preview', ['url' => $url]))
            ->assertOk()
            ->assertJsonPath('preview.kind', 'order')
            ->assertJsonPath('preview.title', 'MOB-LINK-42');
    }
}
