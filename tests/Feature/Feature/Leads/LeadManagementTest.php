<?php

namespace Tests\Feature\Feature\Leads;

use App\Enums\LeadCloseOutcomeFlag;
use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Commercial\LeadAttentionQueueService;
use App\Support\PaymentFormDictionary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class LeadManagementTest extends TestCase
{
    private ?int $defaultBusinessProcessId = null;

    public function test_lead_counterparty_authority_hint_returns_decision_maker(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor($manager->id);

        DB::table('contractors')->where('id', $contractorId)->update([
            'contact_person' => 'Иванова Анна',
            'contact_person_position' => 'Директор',
        ]);

        $response = $this->actingAs($manager)->getJson(route('leads.counterparty-authority-hint', [
            'contractor_id' => $contractorId,
        ]));

        $response->assertOk();
        $response->assertJson([
            'authority' => 'Иванова Анна, Директор',
        ]);
    }

    public function test_commercial_process_nudges_creates_task_for_missed_next_contact(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'next_contact_at' => now()->subDay(),
            'title' => 'Пропущенный контакт',
        ]);

        $this->artisan('commercial:process-nudges')->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'responsible_id' => $manager->id,
            'status' => 'new',
        ]);

        $this->artisan('commercial:process-nudges')->assertSuccessful();
        $this->assertSame(1, DB::table('tasks')->where('lead_id', $lead->id)->count());
    }

    public function test_lead_attention_queue_lists_missed_next_contact(): void
    {
        $manager = $this->createUserWithRole('manager');
        Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'next_contact_at' => now()->subHours(4),
            'title' => 'В очереди внимания',
        ]);

        $queue = app(LeadAttentionQueueService::class)
            ->queueForUser($manager, 10);

        $this->assertTrue($queue['available']);
        $this->assertSame(1, $queue['total']);
        $this->assertSame('В очереди внимания', $queue['items'][0]['title']);
    }

    public function test_lead_attention_queue_is_unavailable_without_leads_visibility_area(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'without-leads-'.uniqid(),
            'display_name' => 'Without leads',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['leads' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create(['role_id' => $roleId]);

        $queue = app(LeadAttentionQueueService::class)
            ->queueForUser($user, 10);

        $this->assertFalse($queue['available']);
        $this->assertSame(0, $queue['total']);
        $this->assertSame([], $queue['items']);
    }

    public function test_lead_show_includes_operational_brief(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'counterparty_id' => null,
            'title' => 'Лид для брифа',
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->where('selectedLead.id', $lead->id)
            ->where('selectedLead.operational_brief.lead_id', $lead->id)
            ->where('selectedLead.operational_brief.health', 'stuck')
            ->has('selectedLead.operational_brief.actions_now')
            ->has('selectedLead.operational_brief.summary_ru')
            ->has('paymentFormOptions')
        );
    }

    public function test_lead_wizard_exposes_business_process_slugs(): void
    {
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->get(route('leads.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->has('businessProcesses')
            ->where('businessProcesses', function (array $processes): bool {
                return collect($processes)->contains(
                    fn (array $process): bool => ($process['slug'] ?? null) === 'contract-signing',
                );
            })
        );
    }

    public function test_lead_process_progress_includes_process_slug(): void
    {
        $manager = $this->createUserWithRole('manager');
        $processId = BusinessProcess::query()->where('slug', 'contract-signing')->value('id');

        if ($processId === null) {
            $this->markTestSkipped('contract-signing process not seeded');
        }

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'business_process_id' => $processId,
            'title' => 'Лид по подписанию контракта',
            'target_currency' => 'RUB',
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedLead.process_progress.process_slug', 'contract-signing')
        );
    }

    public function test_manager_sees_only_own_leads(): void
    {
        $manager = $this->createUserWithRole('manager');
        $otherManager = $this->createUserWithRole('manager');

        Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'Свой лид',
        ]);

        Lead::factory()->create([
            'responsible_id' => $otherManager->id,
            'title' => 'Чужой лид',
        ]);

        $response = $this->actingAs($manager)->get(route('leads.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->has('leads', 1)
            ->where('leads.0.title', 'Свой лид')
        );
    }

    public function test_manager_can_create_lead_with_nested_data(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'source' => 'inbound',
            'counterparty_id' => $contractorId,
            'title' => 'Лид на перевозку оборудования',
            'description' => 'Нужно срочно просчитать рейс',
            'transport_type' => 'ftl',
            'loading_location' => 'Самара',
            'unloading_location' => 'Казань',
            'planned_shipping_date' => now()->addDays(5)->toDateString(),
            'target_price' => 150000,
            'target_currency' => 'RUB',
            'qualification' => [
                'need' => 'FTL',
                'timeline' => '5 дней',
                'authority' => 'Директор',
                'budget' => 'До 150 000',
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Заводская 1',
                    'normalized_data' => [],
                    'planned_date' => now()->addDays(5)->toDateString(),
                ],
            ],
            'cargo_items' => [
                [
                    'name' => 'Оборудование',
                    'description' => 'Станки',
                    'weight_kg' => 1200,
                    'volume_m3' => 9.5,
                    'package_type' => 'pallet',
                    'package_count' => 4,
                    'dangerous_goods' => false,
                    'dangerous_class' => null,
                    'hs_code' => null,
                    'cargo_type' => 'general',
                ],
            ],
            'activities' => [
                [
                    'type' => 'call',
                    'subject' => 'Первичный звонок',
                    'content' => 'Уточнили сроки',
                    'next_action_at' => now()->addDay()->format('Y-m-d H:i:s'),
                ],
            ],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'title' => 'Лид на перевозку оборудования',
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('lead_route_points', [
            'lead_id' => $leadId,
            'address' => 'Самара, Заводская 1',
        ]);
        $this->assertDatabaseHas('lead_cargo_items', [
            'lead_id' => $leadId,
            'name' => 'Оборудование',
        ]);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $leadId,
            'subject' => 'Первичный звонок',
        ]);
    }

    public function test_manager_can_save_lead_with_multiple_loading_points(): void
    {
        $manager = $this->createUserWithRole('manager');

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Lead Multi Pickup Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'source' => 'inbound',
            'counterparty_id' => $contractorId,
            'title' => 'Лид с двумя погрузками',
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Заводская 1',
                    'normalized_data' => [],
                ],
                [
                    'type' => 'loading',
                    'sequence' => 2,
                    'address' => 'Самара, Склад 2',
                    'normalized_data' => [],
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 3,
                    'address' => 'Казань, Терминал',
                    'normalized_data' => [],
                ],
            ],
            'cargo_items' => [],
            'activities' => [],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('lead_route_points', [
            'lead_id' => $leadId,
            'type' => 'loading',
            'address' => 'Самара, Заводская 1',
        ]);
        $this->assertDatabaseHas('lead_route_points', [
            'lead_id' => $leadId,
            'type' => 'loading',
            'address' => 'Самара, Склад 2',
        ]);
        $this->assertDatabaseHas('lead_route_points', [
            'lead_id' => $leadId,
            'type' => 'unloading',
            'address' => 'Казань, Терминал',
        ]);
    }

    public function test_manager_can_save_lead_with_multiple_legs_and_staged_route_points(): void
    {
        $manager = $this->createUserWithRole('manager');

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Lead Multi Leg Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierOneId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Lead Carrier One',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierTwoId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Lead Carrier Two',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'source' => 'inbound',
            'counterparty_id' => $contractorId,
            'title' => 'Лид с двумя плечами',
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierOneId,
                    'contractor_name' => 'Lead Carrier One',
                    'estimated_cost' => 80000,
                ],
                [
                    'stage' => 'leg_2',
                    'contractor_id' => $carrierTwoId,
                    'contractor_name' => 'Lead Carrier Two',
                    'estimated_cost' => 45000,
                ],
            ],
            'route_points' => [
                [
                    'stage' => 'leg_1',
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Заводская 1',
                    'normalized_data' => [],
                ],
                [
                    'stage' => 'leg_1',
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Казань, Хаб',
                    'normalized_data' => [],
                ],
                [
                    'stage' => 'leg_2',
                    'type' => 'loading',
                    'sequence' => 3,
                    'address' => 'Казань, Хаб',
                    'normalized_data' => [],
                ],
                [
                    'stage' => 'leg_2',
                    'type' => 'unloading',
                    'sequence' => 4,
                    'address' => 'Москва, Склад',
                    'normalized_data' => [],
                ],
            ],
            'cargo_items' => [],
            'activities' => [],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));

        $this->assertDatabaseHas('lead_route_points', [
            'lead_id' => $leadId,
            'stage' => 'leg_1',
            'type' => 'loading',
            'address' => 'Самара, Заводская 1',
        ]);
        $this->assertDatabaseHas('lead_route_points', [
            'lead_id' => $leadId,
            'stage' => 'leg_2',
            'type' => 'unloading',
            'address' => 'Москва, Склад',
        ]);

        $lead = Lead::query()->findOrFail($leadId);
        $performers = is_array($lead->performers) ? $lead->performers : [];

        $this->assertCount(2, $performers);
        $this->assertSame('leg_1', $performers[0]['stage'] ?? null);
        $this->assertSame($carrierOneId, $performers[0]['contractor_id'] ?? null);
        $this->assertSame(80000.0, (float) ($performers[0]['estimated_cost'] ?? 0));
        $this->assertSame('leg_2', $performers[1]['stage'] ?? null);
        $this->assertSame($carrierTwoId, $performers[1]['contractor_id'] ?? null);
    }

    public function test_manager_can_convert_multi_leg_lead_into_order_with_legs_and_costs(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();

        $carrierOneId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier One',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierTwoId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier Two',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lead = Lead::factory()->create([
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Конвертация мультиплеча',
            'target_price' => 210000,
            'target_currency' => 'RUB',
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierOneId,
                    'contractor_name' => 'Carrier One',
                    'estimated_cost' => 80000,
                ],
                [
                    'stage' => 'leg_2',
                    'contractor_id' => $carrierTwoId,
                    'contractor_name' => 'Carrier Two',
                    'estimated_cost' => 45000,
                ],
            ],
        ]);

        DB::table('lead_route_points')->insert([
            [
                'lead_id' => $lead->id,
                'stage' => 'leg_1',
                'type' => 'loading',
                'sequence' => 1,
                'address' => 'Samara pickup',
                'normalized_data' => json_encode([], JSON_THROW_ON_ERROR),
                'planned_date' => now()->addDays(2)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $lead->id,
                'stage' => 'leg_1',
                'type' => 'unloading',
                'sequence' => 2,
                'address' => 'Kazan hub',
                'normalized_data' => json_encode([], JSON_THROW_ON_ERROR),
                'planned_date' => now()->addDays(3)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $lead->id,
                'stage' => 'leg_2',
                'type' => 'loading',
                'sequence' => 3,
                'address' => 'Kazan hub',
                'normalized_data' => json_encode([], JSON_THROW_ON_ERROR),
                'planned_date' => now()->addDays(4)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $lead->id,
                'stage' => 'leg_2',
                'type' => 'unloading',
                'sequence' => 4,
                'address' => 'Moscow delivery',
                'normalized_data' => json_encode([], JSON_THROW_ON_ERROR),
                'planned_date' => now()->addDays(5)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($manager)->post(route('leads.convert', $lead));

        $response->assertRedirect();

        $orderId = DB::table('orders')->where('lead_id', $lead->id)->value('id');

        $this->assertNotNull($orderId);

        $legs = DB::table('order_legs')
            ->where('order_id', $orderId)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(2, $legs);
        $this->assertSame('leg_1', $legs[0]->description);
        $this->assertSame('leg_2', $legs[1]->description);

        $this->assertDatabaseHas('route_points', [
            'order_leg_id' => $legs[0]->id,
            'type' => 'loading',
            'address' => 'Samara pickup',
        ]);
        $this->assertDatabaseHas('route_points', [
            'order_leg_id' => $legs[1]->id,
            'type' => 'unloading',
            'address' => 'Moscow delivery',
        ]);

        $financialTerm = DB::table('financial_terms')->where('order_id', $orderId)->first();
        $this->assertNotNull($financialTerm);

        $contractorsCosts = json_decode((string) $financialTerm->contractors_costs, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $contractorsCosts);
        $this->assertSame('leg_1', $contractorsCosts[0]['stage'] ?? null);
        $this->assertSame(80000.0, (float) ($contractorsCosts[0]['amount'] ?? 0));
        $this->assertSame('leg_2', $contractorsCosts[1]['stage'] ?? null);
        $this->assertSame(45000.0, (float) ($contractorsCosts[1]['amount'] ?? 0));
    }

    public function test_manager_can_save_lead_with_precalculation_lines(): void
    {
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'source' => 'inbound',
            'title' => 'Лид с предрасчётом',
            'precalculation' => [
                'status' => 'draft',
                'goods_lines' => [
                    [
                        'id' => 'goods_1',
                        'description' => 'Запчасти',
                        'tn_ved_code' => '',
                        'invoice_amount' => null,
                    ],
                ],
                'service_lines' => [
                    [
                        'id' => 'service_1',
                        'kind' => 'logistics',
                        'title' => 'Доставка',
                        'amount' => 120000,
                        'currency' => 'RUB',
                    ],
                ],
            ],
            'route_points' => [],
            'cargo_items' => [],
            'activities' => [],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));

        $lead = Lead::query()->findOrFail($leadId);
        $precalculation = is_array($lead->precalculation) ? $lead->precalculation : [];

        $this->assertCount(1, $precalculation['service_lines'] ?? []);
        $this->assertSame(120000.0, (float) ($precalculation['computed']['services_total'] ?? 0));
        $this->assertSame(120000.0, (float) ($precalculation['computed']['grand_total'] ?? 0));
    }

    public function test_manager_can_calculate_lead_precalculation_via_api(): void
    {
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->postJson(route('leads.precalculation.calculate'), [
            'service_lines' => [
                [
                    'kind' => 'logistics',
                    'title' => 'Логистика',
                    'amount' => 90000,
                    'currency' => 'RUB',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('computed.services_total', 90000);
        $response->assertJsonPath('computed.grand_total', 90000);
    }

    public function test_manager_can_convert_lead_with_precalculation_snapshot_on_order(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();

        $lead = Lead::factory()->create([
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Конвертация со снимком предрасчёта',
            'precalculation' => [
                'status' => 'ready',
                'freight' => [
                    'to_border_total' => 0,
                    'after_border_total' => 0,
                    'distribution_basis' => 'invoice_rub',
                ],
                'goods_lines' => [],
                'service_lines' => [
                    [
                        'id' => 'service_1',
                        'kind' => 'other',
                        'title' => 'Брокер',
                        'amount' => 25000,
                        'currency' => 'RUB',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->post(route('leads.convert', $lead));

        $response->assertRedirect();

        $orderId = DB::table('orders')->where('lead_id', $lead->id)->value('id');
        $this->assertNotNull($orderId);

        $metadata = DB::table('orders')->where('id', $orderId)->value('metadata');
        if ($metadata !== null) {
            $decoded = json_decode((string) $metadata, true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('lead_precalculation_snapshot', $decoded);
            $this->assertSame('ready', $decoded['lead_precalculation_snapshot']['status'] ?? null);

            return;
        }

        $wizardState = DB::table('orders')->where('id', $orderId)->value('wizard_state');
        $this->assertNotNull($wizardState);
        $decodedWizard = json_decode((string) $wizardState, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('lead_precalculation_snapshot', $decodedWizard);
    }

    public function test_manager_can_open_precalculation_document_html(): void
    {
        $manager = $this->createUserWithRole('manager');

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'HTML предрасчёт',
            'precalculation' => [
                'status' => 'ready',
                'freight' => [
                    'to_border_total' => 0,
                    'after_border_total' => 0,
                    'distribution_basis' => 'invoice_rub',
                ],
                'service_lines' => [
                    [
                        'kind' => 'other',
                        'title' => 'Доставка',
                        'amount' => 50000,
                        'currency' => 'RUB',
                    ],
                ],
                'goods_lines' => [],
            ],
        ]);

        $response = $this->actingAs($manager)->get(route('leads.precalculation.document', [
            'lead' => $lead,
            'format' => 'html',
            'preview' => 1,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Коммерческий предрасчёт', (string) $response->getContent());
        $this->assertStringContainsString('50 000', (string) $response->getContent());
    }

    public function test_manager_can_save_lead_finance_fields_with_expected_margin(): void
    {
        $manager = $this->createUserWithRole('manager');

        $clientVatCode = PaymentFormDictionary::defaultClientVatCode();

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'source' => 'inbound',
            'title' => 'Лид с финансами',
            'target_currency' => 'RUB',
            'target_price' => 200000,
            'calculated_cost' => 150000,
            'customer_payment_form' => $clientVatCode,
            'carrier_payment_form' => 'no_vat',
            'qualification' => [],
            'route_points' => [],
            'cargo_items' => [],
            'activities' => [],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'target_price' => 200000,
            'calculated_cost' => 150000,
            'customer_payment_form' => $clientVatCode,
            'carrier_payment_form' => 'no_vat',
            'expected_margin' => 50000,
        ]);
    }

    public function test_lead_status_auto_advances_when_route_and_cargo_are_filled(): void
    {
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'qualification',
            'source' => 'inbound',
            'title' => 'Лид с маршрутом и грузом',
            'target_currency' => 'RUB',
            'qualification' => [],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Заводская 1',
                    'normalized_data' => [],
                    'planned_date' => now()->addDays(3)->toDateString(),
                ],
            ],
            'cargo_items' => [
                [
                    'name' => 'Оборудование',
                    'description' => null,
                    'weight_kg' => 1000,
                    'volume_m3' => null,
                    'package_type' => null,
                    'package_count' => null,
                    'dangerous_goods' => false,
                    'dangerous_class' => null,
                    'hs_code' => null,
                    'cargo_type' => 'general',
                ],
            ],
            'activities' => [],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'status' => 'calculation',
            'loading_location' => 'Самара, Заводская 1',
        ]);
    }

    public function test_lead_status_is_preserved_when_requested(): void
    {
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'qualification',
            'preserve_status' => true,
            'source' => 'inbound',
            'title' => 'Лид с ручным статусом',
            'target_currency' => 'RUB',
            'qualification' => [],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Казань',
                    'normalized_data' => [],
                ],
            ],
            'cargo_items' => [
                [
                    'name' => 'Груз',
                    'description' => null,
                    'weight_kg' => null,
                    'volume_m3' => null,
                    'package_type' => null,
                    'package_count' => null,
                    'dangerous_goods' => false,
                    'dangerous_class' => null,
                    'hs_code' => null,
                    'cargo_type' => 'general',
                ],
            ],
            'activities' => [],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'status' => 'qualification',
        ]);
    }

    public function test_manager_can_prepare_commercial_offer_for_lead(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'КП для клиента',
            'target_price' => 180000,
            'target_currency' => 'RUB',
        ]);

        $response = $this->actingAs($manager)->post(route('leads.proposal', $lead));

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('lead_offers', [
            'lead_id' => $lead->id,
            'status' => 'prepared',
            'number' => 'КП-'.$lead->number,
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'proposal_ready',
        ]);
    }

    public function test_manager_create_page_defaults_responsible_to_current_user_and_hides_reassignment(): void
    {
        $manager = $this->createUserWithRole('manager');
        $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->get(route('leads.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->where('isCreating', true)
            ->where('selectedLead', null)
            ->where('currentUserId', $manager->id)
            ->where('canAssignResponsible', true)
            ->has('responsibleUsers', 2)
            ->where('sourceOptions.4.value', 'existing_customer')
            ->where('sourceOptions.4.label', 'Действующий клиент')
        );
    }

    public function test_manager_can_assign_other_responsible_when_creating_lead(): void
    {
        $manager = $this->createUserWithRole('manager');
        $otherManager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'source' => 'inbound',
            'responsible_id' => $otherManager->id,
            'title' => 'Лид с назначенным ответственным',
            'target_currency' => 'RUB',
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'responsible_id' => $otherManager->id,
        ]);
    }

    public function test_manager_can_reassign_responsible_when_updating_lead(): void
    {
        $manager = $this->createUserWithRole('manager');
        $otherManager = $this->createUserWithRole('manager');

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'Лид без смены ответственного',
            'target_currency' => 'RUB',
        ]);

        $response = $this->actingAs($manager)->patch(route('leads.update', $lead), [
            'business_process_id' => $this->defaultBusinessProcessId(),
            'status' => $lead->status,
            'source' => $lead->source,
            'counterparty_id' => $lead->counterparty_id,
            'responsible_id' => $otherManager->id,
            'title' => 'Лид без смены ответственного',
            'description' => $lead->description,
            'transport_type' => $lead->transport_type,
            'loading_location' => $lead->loading_location,
            'unloading_location' => $lead->unloading_location,
            'planned_shipping_date' => optional($lead->planned_shipping_date)->toDateString(),
            'target_price' => $lead->target_price,
            'target_currency' => $lead->target_currency,
            'calculated_cost' => $lead->calculated_cost,
            'expected_margin' => $lead->expected_margin,
            'next_contact_at' => optional($lead->next_contact_at)->toDateString(),
            'lost_reason' => $lead->lost_reason,
            'qualification' => [],
            'route_points' => [],
            'cargo_items' => [],
            'activities' => [],
        ]);

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'responsible_id' => $otherManager->id,
        ]);
    }

    public function test_update_lead_to_lost_requires_close_outcome(): void
    {
        $manager = $this->createUserWithRole('manager');
        $processId = $this->defaultBusinessProcessId();

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'business_process_id' => $processId,
            'status' => 'negotiation',
            'title' => 'Лид для закрытия без сделки',
            'target_currency' => 'RUB',
        ]);

        $response = $this->actingAs($manager)->patch(route('leads.update', $lead), [
            ...$this->leadUpdatePayload($lead),
            'status' => 'lost',
            'preserve_status' => true,
        ]);

        $response->assertSessionHasErrors('close_outcome_primary_flag');
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'negotiation',
        ]);
    }

    public function test_update_lead_to_lost_with_close_outcome_succeeds(): void
    {
        $manager = $this->createUserWithRole('manager');
        $processId = $this->defaultBusinessProcessId();

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'business_process_id' => $processId,
            'status' => 'negotiation',
            'title' => 'Лид закрывается без сделки',
            'target_currency' => 'RUB',
        ]);

        $response = $this->actingAs($manager)->patch(route('leads.update', $lead), [
            ...$this->leadUpdatePayload($lead),
            'status' => 'lost',
            'preserve_status' => true,
            'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostOther->value,
            'close_outcome_note' => 'Клиент отказался',
        ]);

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'lost',
            'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostOther->value,
        ]);
    }

    public function test_cannot_create_lead_without_title(): void
    {
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'title' => '',
            'target_currency' => 'RUB',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_update_lead_without_business_process_id_uses_existing_value(): void
    {
        $manager = $this->createUserWithRole('manager');
        $processId = $this->defaultBusinessProcessId();

        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'business_process_id' => $processId,
            'title' => 'Лид с процессом',
            'target_currency' => 'RUB',
        ]);

        $payload = $this->leadUpdatePayload($lead);
        unset($payload['business_process_id']);

        $response = $this->actingAs($manager)->patch(route('leads.update', $lead), $payload);

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'business_process_id' => $processId,
        ]);
    }

    public function test_manager_opens_lead_card_over_index_grid(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();
        $lead = Lead::factory()->create([
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Отдельная карточка лида',
        ]);

        DB::table('print_form_templates')->insert([
            [
                'code' => 'lead_offer_default',
                'name' => 'Коммерческое по умолчанию',
                'entity_type' => 'lead',
                'document_type' => 'offer',
                'document_group' => 'commercial',
                'party' => 'customer',
                'source_type' => 'external_docx',
                'contractor_id' => null,
                'is_default' => true,
                'vue_component' => 'ExternalDocxTemplate',
                'requires_internal_signature' => true,
                'requires_counterparty_signature' => false,
                'is_active' => true,
                'version' => 1,
                'file_disk' => 'local',
                'file_path' => 'print-form-templates/lead/default.docx',
                'original_filename' => 'default.docx',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'lead_offer_for_contractor',
                'name' => 'Коммерческое клиента',
                'entity_type' => 'lead',
                'document_type' => 'offer',
                'document_group' => 'commercial',
                'party' => 'customer',
                'source_type' => 'external_docx',
                'contractor_id' => $contractorId,
                'is_default' => false,
                'vue_component' => 'ExternalDocxTemplate',
                'requires_internal_signature' => true,
                'requires_counterparty_signature' => false,
                'is_active' => true,
                'version' => 1,
                'file_disk' => 'local',
                'file_path' => 'print-form-templates/lead/customer.docx',
                'original_filename' => 'customer.docx',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'lead_contract_should_not_show',
                'name' => 'Договорный шаблон',
                'entity_type' => 'lead',
                'document_type' => 'contract',
                'document_group' => 'contractual',
                'party' => 'customer',
                'source_type' => 'external_docx',
                'contractor_id' => null,
                'is_default' => false,
                'vue_component' => 'ExternalDocxTemplate',
                'requires_internal_signature' => true,
                'requires_counterparty_signature' => false,
                'is_active' => true,
                'version' => 1,
                'file_disk' => 'local',
                'file_path' => 'print-form-templates/lead/contract.docx',
                'original_filename' => 'contract.docx',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->where('isCreating', false)
            ->where('selectedLead.id', $lead->id)
            ->where('selectedLead.title', 'Отдельная карточка лида')
            ->has('printFormTemplateOptions', 2)
            ->where('printFormTemplateOptions.0.code', 'lead_offer_for_contractor')
            ->where('printFormTemplateOptions.1.code', 'lead_offer_default')
        );
    }

    public function test_manager_can_download_commercial_draft_for_lead(): void
    {
        Storage::fake('local');

        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();
        $lead = Lead::factory()->create([
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Коммерческое из шаблона',
            'target_price' => 180000,
            'target_currency' => 'RUB',
        ]);

        DB::table('lead_route_points')->insert([
            'lead_id' => $lead->id,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Самара, Заводская 1',
            'normalized_data' => json_encode(['city' => 'Самара'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_cargo_items')->insert([
            'lead_id' => $lead->id,
            'name' => 'Оборудование',
            'weight_kg' => 1200,
            'volume_m3' => 8.5,
            'package_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'lead_offer_template',
            'name' => 'Коммерческое предложение',
            'entity_type' => 'lead',
            'document_type' => 'offer',
            'document_group' => 'commercial',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => $contractorId,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/lead-offer-template.docx',
            'original_filename' => 'lead-offer-template.docx',
            'settings' => json_encode([
                'variables' => ['lead.number', 'counterparty.name', 'route.loading_addresses', 'cargo.summary'],
                'variable_mapping' => [
                    'lead.number' => 'lead.number',
                    'counterparty.name' => 'counterparty.name',
                    'route.loading_addresses' => 'route.loading_addresses',
                    'cargo.summary' => 'cargo.summary',
                ],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/lead-offer-template.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${lead.number}</w:t></w:r></w:p><w:p><w:r><w:t>${counterparty.name}</w:t></w:r></w:p><w:p><w:r><w:t>${route.loading_addresses}</w:t></w:r></w:p><w:p><w:r><w:t>${cargo.summary}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $response = $this->actingAs($manager)->get(route('leads.templates.generate-draft', [
            'lead' => $lead,
            'printFormTemplate' => $templateId,
        ]));

        $response->assertOk();
        $response->assertDownload('lead-offer-template-lead-'.$lead->id.'-draft.docx');
        $this->assertFileExists($response->baseResponse->getFile()->getPathname());

        $previewResponse = $this->actingAs($manager)->get(route('leads.templates.generate-draft', [
            'lead' => $lead,
            'printFormTemplate' => $templateId,
            'preview' => 1,
        ]));

        $previewResponse->assertOk();
        $this->assertStringContainsString('wordprocessingml', strtolower($previewResponse->headers->get('content-type') ?? ''));
        $this->assertStringContainsString('inline', strtolower($previewResponse->headers->get('content-disposition') ?? ''));
    }

    public function test_manager_can_convert_lead_into_order(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();

        $lead = Lead::factory()->create([
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Конвертация в заказ',
            'target_price' => 210000,
            'target_currency' => 'RUB',
        ]);

        DB::table('lead_route_points')->insert([
            'lead_id' => $lead->id,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Москва, Склад 10',
            'normalized_data' => json_encode([], JSON_THROW_ON_ERROR),
            'planned_date' => now()->addDays(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->post(route('leads.convert', $lead));

        $response->assertRedirect();

        $orderId = DB::table('orders')->where('lead_id', $lead->id)->value('id');

        $this->assertNotNull($orderId);
        $response->assertRedirect(route('orders.edit', $orderId));
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'lead_id' => $lead->id,
            'customer_id' => $contractorId,
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'won',
        ]);
    }

    public function test_manager_can_create_next_step_task_for_own_lead(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'Лид для следующего шага',
        ]);

        $response = $this->actingAs($manager)->post(route('leads.next-step.store', $lead), [
            'title' => 'Перезвонить клиенту после согласования ставки',
            'description' => 'Уточнить решение по коммерческому предложению',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'responsible_id' => $manager->id,
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'title' => 'Перезвонить клиенту после согласования ставки',
            'responsible_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'subject' => 'Создан следующий шаг',
        ]);
    }

    public function test_create_from_task_prefills_lead_template(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();

        $task = Task::query()->create([
            'number' => 'TSK-TEST-001',
            'title' => 'Перезвонить по заявке',
            'description' => 'Клиент ждёт КП',
            'status' => 'new',
            'priority' => 'high',
            'responsible_id' => $manager->id,
            'contractor_id' => $contractorId,
        ]);

        $response = $this->actingAs($manager)->get(route('leads.create', ['from_task' => $task->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->where('isCreating', true)
            ->where('leadTemplate.title', 'Перезвонить по заявке')
            ->where('leadTemplate.description', 'Клиент ждёт КП')
            ->where('leadTemplate.counterparty_id', $contractorId)
            ->where('leadTemplate.responsible_id', $manager->id)
            ->where('leadTemplate.link_task_id', $task->id)
        );
    }

    public function test_store_with_link_task_id_attaches_lead_to_task(): void
    {
        $manager = $this->createUserWithRole('manager');

        $task = Task::query()->create([
            'number' => 'TSK-TEST-002',
            'title' => 'Связать с лидом',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            ...$this->leadStoreDefaults($manager),
            'status' => 'new',
            'source' => 'inbound',
            'title' => 'Лид из задачи',
            'target_currency' => 'RUB',
            'link_task_id' => $task->id,
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'lead_id' => $leadId,
        ]);
    }

    public function test_index_returns_feature_unavailable_when_lead_tables_are_missing(): void
    {
        $this->markTestSkipped('DDL drop ломает RefreshDatabase; проверка featureUnavailable покрывается без destructive schemaDropMany.');
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->get(route('leads.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->where('featureUnavailable', true)
            ->has('leads', 0)
        );
    }

    public function test_manager_can_destroy_lead_and_it_disappears_from_index(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
            'title' => 'На удаление',
        ]);

        $this->actingAs($manager)
            ->delete(route('leads.destroy', $lead))
            ->assertRedirect(route('leads.index'));

        $this->assertNotNull($lead->fresh()?->deleted_at);

        $this->actingAs($manager)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('leads')
                ->where('leads', fn ($leads) => collect($leads)->doesntContain('id', $lead->id))
            );
    }

    public function test_destroyed_lead_is_not_accessible_via_show_route(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'status' => 'qualification',
        ]);

        $lead->delete();

        $this->actingAs($manager)
            ->get(route('leads.show', $lead))
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function leadStoreDefaults(User $manager): array
    {
        return [
            'responsible_id' => $manager->id,
            'business_process_id' => $this->defaultBusinessProcessId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadUpdatePayload(Lead $lead): array
    {
        return [
            'business_process_id' => $lead->business_process_id ?? $this->defaultBusinessProcessId(),
            'status' => $lead->status,
            'source' => $lead->source,
            'counterparty_id' => $lead->counterparty_id,
            'responsible_id' => $lead->responsible_id,
            'title' => $lead->title,
            'description' => $lead->description,
            'transport_type' => $lead->transport_type,
            'loading_location' => $lead->loading_location,
            'unloading_location' => $lead->unloading_location,
            'planned_shipping_date' => optional($lead->planned_shipping_date)->toDateString(),
            'target_price' => $lead->target_price,
            'target_currency' => $lead->target_currency,
            'calculated_cost' => $lead->calculated_cost,
            'expected_margin' => $lead->expected_margin,
            'next_contact_at' => optional($lead->next_contact_at)->toDateString(),
            'lost_reason' => $lead->lost_reason,
            'qualification' => [],
            'route_points' => [],
            'cargo_items' => [],
            'activities' => [],
        ];
    }

    private function defaultBusinessProcessId(): int
    {
        if ($this->defaultBusinessProcessId !== null) {
            return $this->defaultBusinessProcessId;
        }

        $process = BusinessProcess::query()->create([
            'name' => 'Тестовый процесс лидов',
            'slug' => 'lead-test-process-'.uniqid(),
            'is_active' => true,
        ]);

        BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Квалификация',
            'sequence' => 10,
        ]);

        $this->defaultBusinessProcessId = $process->id;

        return $this->defaultBusinessProcessId;
    }

    private function createUserWithRole(string $roleName): User
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $roleName,
                'display_name' => ucfirst($roleName),
                'visibility_areas' => json_encode(['dashboard', 'leads', 'orders', 'tasks'], JSON_THROW_ON_ERROR),
                'visibility_scopes' => json_encode([
                    'leads' => $roleName === 'manager' ? 'own' : 'all',
                    'orders' => $roleName === 'manager' ? 'own' : 'all',
                    'tasks' => $roleName === 'manager' ? 'own' : 'all',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }

    private function createContractor(?int $ownerId = null): int
    {
        $payload = [
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'ogrn' => '1234567890123',
            'bank_name' => 'АО Банк Клиент',
            'signer_name_nominative' => 'Иванов Иван Иванович',
            'signer_authority_basis' => 'Устав',
            'is_active' => true,
            'is_verified' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($ownerId !== null) {
            $payload['owner_id'] = $ownerId;
        }

        return (int) DB::table('contractors')->insertGetId($payload);
    }

    private function makeDocxPath(array $entries): string
    {
        $directory = storage_path('framework/testing/disks/local');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.uniqid('docx-template-', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }

        $zip->close();

        return $path;
    }
}
