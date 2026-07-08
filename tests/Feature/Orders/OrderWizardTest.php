<?php

namespace Tests\Feature\Orders;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class OrderWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $phpWordTmp = storage_path('framework/phpword-tmp');
        if (! is_dir($phpWordTmp)) {
            File::makeDirectory($phpWordTmp, 0777, true);
        }

        if (Schema::hasTable('vat_rates') && DB::table('vat_rates')->count() === 0) {
            $now = now();
            DB::table('vat_rates')->insert([
                ['code' => 'vat_22', 'label' => 'С НДС 22%', 'rate_percent' => 22, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'vat_5', 'label' => 'С НДС 5%', 'rate_percent' => 5, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'vat_0', 'label' => 'С НДС 0%', 'rate_percent' => 0, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function test_admin_can_open_order_wizard_create_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('orders.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->has('currencyOptions')
            ->has('paymentFormOptions')
            ->has('defaultClientPaymentFormCode')
            ->has('orderStatusOptions')
            ->has('documentPartyOptions', 4)
            ->has('printFormTemplateOptions')
            ->has('requiredDocumentRules', 5)
            ->has('requiredDocumentChecklist', 5)
            ->has('currentUser')
        );
    }

    public function test_order_document_rules_allow_contract_type_and_print_template_workflow_flow(): void
    {
        $rules = (new StoreOrderRequest)->rules();
        $subset = collect($rules)->only([
            'documents',
            'documents.*.type',
            'documents.*.flow',
            'documents.*.party',
            'documents.*.status',
        ])->all();

        $validator = Validator::make([
            'documents' => [
                [
                    'type' => 'contract',
                    'flow' => 'print_template_workflow',
                    'party' => 'customer',
                    'status' => 'draft',
                ],
            ],
        ], $subset);

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    public function test_ati_cargo_backfill_migrates_legacy_columns(): void
    {
        $cargoId = DB::table('cargos')->insertGetId([
            'title' => 'Станок',
            'weight' => 1250,
            'cargo_type' => 'oversized',
            'packing_type' => 'crate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_05_03_090135_backfill_ati_cargo_fields_from_legacy_columns.php');
        $migration->up();

        $this->assertDatabaseHas('cargos', [
            'id' => $cargoId,
            'ati_cargo_name' => 'Станок',
            'weight_value' => '1250.000',
            'weight_unit' => 'kg',
            'cargo_type_label' => 'oversized',
            'pack_type_label' => 'crate',
            'is_oversized' => true,
        ]);
    }

    public function test_admin_can_create_order_with_nested_data(): void
    {
        $admin = $this->createAdminUser();

        DB::table('kpi_settings')->insert([
            'key' => 'delta_bonus_multiplier',
            'value' => '1.30',
            'type' => 'float',
            'group' => 'delta',
            'description' => 'Multiplier',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('salary_coefficients')->insert([
            'manager_id' => $admin->id,
            'base_salary' => 10000,
            'bonus_percent' => 10,
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'inn' => '1234567890',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ООО Наша Компания',
            'inn' => '9876543210',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'inn' => '5555555555',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'status' => 'new',
            'own_company_id' => $ownCompanyId,
            'client_id' => $clientId,
            'order_date' => '2026-04-01',
            'order_number' => '',
            'special_notes' => 'Хрупкий груз',
            'additional_expenses' => 5000,
            'insurance' => 0,
            'bonus' => 0,
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'loading_types' => ['tail_lift'],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Московское шоссе, 10',
                    'normalized_data' => ['city' => 'Самара', 'coordinates' => ['lat' => '53.2', 'lng' => '50.1']],
                    'planned_date' => '2026-04-02',
                    'contact_person' => 'Иван',
                    'contact_phone' => '+79990000000',
                    'sender_name' => 'ООО Отправитель',
                    'sender_contact' => 'Склад',
                    'sender_phone' => '+79990000001',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Казань, ул. Баумана, 1',
                    'normalized_data' => ['city' => 'Казань'],
                    'planned_date' => '2026-04-03',
                    'contact_person' => 'Петр',
                    'contact_phone' => '+79991111111',
                    'recipient_name' => 'ООО Получатель',
                    'recipient_contact' => 'Приемка',
                    'recipient_phone' => '+79990000002',
                ],
            ],
            'cargo_items' => [
                [
                    'name' => 'Бытовая техника',
                    'description' => 'Партия холодильников',
                    'weight_value' => 1.2,
                    'weight_unit' => 't',
                    'volume_m3' => 16.5,
                    'length_m' => 2.5,
                    'width_m' => 2,
                    'height_m' => 1.5,
                    'package_type' => 'pallet',
                    'pack_type_id' => 1,
                    'pack_type_label' => 'Паллета',
                    'loading_type_id' => 5,
                    'loading_type_code' => 'tail_lift',
                    'loading_type_label' => 'Гидроборт',
                    'loading_type_items' => [
                        ['id' => 5, 'code' => 'tail_lift', 'label' => 'Гидроборт'],
                        ['id' => 6, 'code' => 'crane', 'label' => 'Манипулятор'],
                    ],
                    'truck_body_type_id' => 1,
                    'truck_body_type_code' => 'all_closed',
                    'truck_body_type_label' => 'Все закрытые',
                    'truck_body_type_items' => [
                        ['id' => 1, 'code' => 'all_closed', 'label' => 'Все закрытые'],
                        ['id' => 2, 'code' => 'all_open', 'label' => 'Все открытые'],
                    ],
                    'trailer_type_id' => 1,
                    'trailer_type_code' => 'semi_trailer',
                    'trailer_type_label' => 'Полуприцеп',
                    'trailer_type_items' => [
                        ['id' => 1, 'code' => 'semi_trailer', 'label' => 'Полуприцеп'],
                    ],
                    'package_count' => 10,
                    'dangerous_goods' => false,
                    'dangerous_class' => null,
                    'hs_code' => '841810',
                    'cargo_type' => 'general',
                    'cargo_type_id' => 1,
                    'cargo_type_label' => 'Общий груз',
                ],
            ],
            'financial_term' => [
                'client_price' => 120000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => true,
                    'prepayment_ratio' => 30,
                    'prepayment_days' => 1,
                    'prepayment_mode' => 'fttn',
                    'postpayment_days' => 5,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 10,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 80000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 7,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [
                [
                    'type' => 'request',
                    'party' => 'customer',
                    'requirement_key' => 'customer_request',
                    'number' => 'REQ-1',
                    'document_date' => '2026-04-01',
                    'status' => 'signed',
                    'template_id' => null,
                    'file' => UploadedFile::fake()->create('request.pdf', 120, 'application/pdf'),
                ],
            ],
        ]);

        $orderId = DB::table('orders')->value('id');

        $response->assertRedirect(route('orders.edit', $orderId));
        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'customer_id' => $clientId,
            'own_company_id' => $ownCompanyId,
            'manager_id' => $admin->id,
            'status' => 'new',
            'customer_payment_form' => 'vat_22',
            'carrier_payment_form' => 'no_vat',
        ]);
        $this->assertDatabaseHas('route_points', [
            'address' => 'Самара, Московское шоссе, 10',
            'sender_name' => 'ООО Отправитель',
            'sender_contact' => 'Склад',
            'sender_phone' => '+79990000001',
        ]);
        $this->assertDatabaseHas('route_points', [
            'address' => 'Казань, ул. Баумана, 1',
            'recipient_name' => 'ООО Получатель',
            'recipient_contact' => 'Приемка',
            'recipient_phone' => '+79990000002',
        ]);
        $this->assertDatabaseHas('cargos', [
            'order_id' => $orderId,
            'title' => 'Бытовая техника',
            'ati_cargo_name' => 'Бытовая техника',
            'weight' => '1200.00',
            'weight_value' => '1.200',
            'weight_unit' => 't',
            'volume' => '7.50',
            'cargo_type_id' => 1,
            'cargo_type_label' => 'Общий груз',
            'pack_type_id' => 1,
            'pack_type_label' => 'Паллета',
            'loading_type_id' => 5,
            'loading_type_code' => 'tail_lift',
            'loading_type_label' => 'Гидроборт',
            'truck_body_type_id' => 1,
            'truck_body_type_code' => 'all_closed',
            'truck_body_type_label' => 'Все закрытые',
            'trailer_type_id' => 1,
            'trailer_type_code' => 'semi_trailer',
            'trailer_type_label' => 'Полуприцеп',
            'needs_hydraulic' => true,
            'needs_manipulator' => true,
            'length' => '2.50',
            'width' => '2.00',
            'height' => '1.50',
        ]);
        $cargo = DB::table('cargos')->where('order_id', $orderId)->first();
        $this->assertNotNull($cargo);
        $loadingTypeItems = json_decode((string) $cargo->loading_type_items, true);
        $truckBodyTypeItems = json_decode((string) $cargo->truck_body_type_items, true);
        $this->assertSame('crane', $loadingTypeItems[1]['code'] ?? null);
        $this->assertSame('all_open', $truckBodyTypeItems[1]['code'] ?? null);
        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
            'client_price' => 120000,
        ]);
        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'kpi_percent' => '3.00',
            'delta' => '36400.00',
            'salary_accrued' => '13640.00',
        ]);
        $wizardState = json_decode((string) DB::table('orders')->where('id', $orderId)->value('wizard_state'), true);
        $this->assertIsArray($wizardState);
        $this->assertSame(1, $wizardState['version']);
        $this->assertSame(120000, (int) ($wizardState['financial_term']['client_price'] ?? 0));
        $financialTerm = DB::table('financial_terms')->where('order_id', $orderId)->first();
        $this->assertNotNull($financialTerm);
        $this->assertStringContainsString('30%', (string) $financialTerm->client_payment_terms);
        $this->assertStringContainsString('сканам', (string) $financialTerm->client_payment_terms);
        $this->assertStringContainsString('70%', (string) $financialTerm->client_payment_terms);
        $this->assertStringContainsString('оригиналам', (string) $financialTerm->client_payment_terms);
        $decodedCosts = json_decode((string) $financialTerm->contractors_costs, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('no_vat', $decodedCosts[0]['payment_form'] ?? null);
        $this->assertDatabaseHas('payment_schedules', [
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => '36000.00',
        ]);
        $this->assertDatabaseHas('payment_schedules', [
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => '84000.00',
        ]);
        $this->assertDatabaseHas('payment_schedules', [
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => '80000.00',
        ]);
        $this->assertDatabaseHas('order_documents', [
            'order_id' => $orderId,
            'number' => 'REQ-1',
        ]);
        $documentMetadata = DB::table('order_documents')
            ->where('order_id', $orderId)
            ->where('number', 'REQ-1')
            ->value('metadata');
        $this->assertIsString($documentMetadata);
        $metadata = json_decode($documentMetadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('customer', $metadata['party'] ?? null);
        $this->assertSame('customer_request', $metadata['requirement_key'] ?? null);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $orderId,
            'status_to' => 'new',
        ]);
    }

    public function test_admin_can_create_order_with_order_payload_multipart_and_attached_document_file(): void
    {
        $admin = $this->createAdminUser();

        DB::table('kpi_settings')->insert([
            'key' => 'delta_bonus_multiplier',
            'value' => '1.30',
            'type' => 'float',
            'group' => 'delta',
            'description' => 'Multiplier',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('salary_coefficients')->insert([
            'manager_id' => $admin->id,
            'base_salary' => 10000,
            'bonus_percent' => 10,
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент Payload',
            'inn' => '1234567891',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ООО Наша Компания Payload',
            'inn' => '9876543211',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик Payload',
            'inn' => '5555555556',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderPayload = [
            'status' => 'new',
            'own_company_id' => $ownCompanyId,
            'client_id' => $clientId,
            'order_date' => '2026-04-01',
            'order_number' => '',
            'special_notes' => 'Payload multipart',
            'additional_expenses' => 1000,
            'insurance' => 0,
            'bonus' => 0,
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, ул. Payload, 1',
                    'normalized_data' => ['city' => 'Самара'],
                    'planned_date' => '2026-04-02',
                    'contact_person' => 'Иван',
                    'contact_phone' => '+79990000000',
                    'sender_name' => 'ООО Отправитель',
                    'sender_contact' => 'Склад',
                    'sender_phone' => '+79990000001',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Казань, ул. Payload, 2',
                    'normalized_data' => ['city' => 'Казань'],
                    'planned_date' => '2026-04-03',
                    'contact_person' => 'Петр',
                    'contact_phone' => '+79991111111',
                    'recipient_name' => 'ООО Получатель',
                    'recipient_contact' => 'Приемка',
                    'recipient_phone' => '+79990000002',
                ],
            ],
            'cargo_items' => [
                [
                    'name' => 'Груз payload',
                    'description' => '',
                    'weight_kg' => 100,
                    'volume_m3' => 1,
                    'package_type' => 'pallet',
                    'package_count' => 1,
                    'dangerous_goods' => false,
                    'dangerous_class' => null,
                    'hs_code' => '',
                    'cargo_type' => 'general',
                ],
            ],
            'financial_term' => [
                'client_price' => 50000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_request_mode' => 'single_request',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 5,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 5,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 30000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 7,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [
                [
                    'type' => 'request',
                    'flow' => 'uploaded',
                    'party' => 'customer',
                    'stage' => null,
                    'requirement_key' => null,
                    'number' => 'PL-REQ-1',
                    'document_date' => '',
                    'status' => 'signed',
                    'template_id' => null,
                    'original_name' => '',
                    'generated_pdf_path' => null,
                ],
            ],
        ];

        $file = UploadedFile::fake()->create('payload-request.pdf', 120, 'application/pdf');

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'order_payload' => json_encode($orderPayload, JSON_THROW_ON_ERROR),
            'document_file_0' => $file,
        ]);

        $orderId = DB::table('orders')->value('id');
        $response->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseHas('route_points', [
            'address' => 'Самара, ул. Payload, 1',
        ]);
        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
            'client_price' => 50000,
        ]);
        $this->assertDatabaseHas('order_documents', [
            'order_id' => $orderId,
            'number' => 'PL-REQ-1',
        ]);

        $filePath = DB::table('order_documents')
            ->where('order_id', $orderId)
            ->where('number', 'PL-REQ-1')
            ->value('file_path');
        $this->assertNotNull($filePath);
        $this->assertNotSame('', $filePath);
        $this->assertNull(DB::table('order_documents')
            ->where('order_id', $orderId)
            ->where('number', 'PL-REQ-1')
            ->value('document_date'));
    }

    public function test_admin_can_update_order_and_persist_contractor_costs(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'inn' => '1234567890',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'inn' => '5555555555',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-2026-001',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('orders.update', $orderId), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-02',
            'order_number' => 'ORD-2026-001',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_custom', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Московское шоссе, 10',
                    'normalized_data' => [],
                    'planned_date' => '2026-04-02',
                    'actual_date' => null,
                    'contact_person' => null,
                    'contact_phone' => null,
                    'sender_name' => 'ООО Новый отправитель',
                    'sender_contact' => 'Диспетчер',
                    'sender_phone' => '+79990000003',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Уфа, Центральная, 9',
                    'normalized_data' => [],
                    'planned_date' => '2026-04-03',
                    'actual_date' => null,
                    'contact_person' => null,
                    'contact_phone' => null,
                    'recipient_name' => 'ООО Новый получатель',
                    'recipient_contact' => 'Приемка',
                    'recipient_phone' => '+79990000004',
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 150000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 5,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_custom',
                        'contractor_id' => $carrierId,
                        'amount' => 99000.50,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 3,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $response->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'customer_rate' => '150000.00',
        ]);

        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
            'client_price' => '150000.00',
        ]);
        $this->assertOrderCarrierRate($orderId, 99000.50);
        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'kpi_percent' => '3.00',
            'delta' => '46499.50',
            'salary_accrued' => '23249.75',
        ]);

        $contractorsCosts = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($contractorsCosts);
        $decodedCosts = json_decode($contractorsCosts, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(99000.5, round((float) ($decodedCosts[0]['amount'] ?? 0), 1));
        $this->assertSame('leg_custom', $decodedCosts[0]['stage'] ?? null);
    }

    public function test_updating_order_does_not_reassign_manager_to_current_user(): void
    {
        $admin = $this->createAdminUser();
        $manager = User::factory()->create();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'inn' => '1234500000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'inn' => '5555500000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-2026-010',
            'company_code' => 'TST',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-10',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('orders.update', $orderId), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-11',
            'order_number' => 'ORD-2026-010',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                ['type' => 'loading', 'sequence' => 1, 'address' => 'Самара', 'normalized_data' => []],
                ['type' => 'unloading', 'sequence' => 2, 'address' => 'Уфа', 'normalized_data' => []],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 110000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 90000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $response->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'manager_id' => $manager->id,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_updating_order_preserves_print_workflow_documents_and_does_not_recreate_stale_drafts(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $printTemplateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'preserve_workflow',
            'name' => 'Preserve Workflow',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => $clientId,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/preserve.docx',
            'original_filename' => 'preserve.docx',
            'settings' => json_encode(['pipeline_status' => 'placeholders_ready'], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-2026-011',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $printDocumentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'request',
            'source' => 'print_template',
            'original_name' => 'Заявка.docx',
            'file_path' => 'order_documents/'.$orderId.'/draft.docx',
            'template_id' => $printTemplateId,
            'status' => 'draft',
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'signature_status' => 'not_requested',
            'metadata' => json_encode(['flow' => 'print_template_workflow'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_documents')->insert([
            'order_id' => $orderId,
            'type' => 'other',
            'source' => 'uploaded',
            'number' => 'OLD-UPLOAD',
            'status' => 'draft',
            'metadata' => json_encode(['flow' => 'uploaded', 'party' => 'customer'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $artifactDocumentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'request',
            'source' => 'print_template',
            'template_id' => $printTemplateId,
            'status' => 'draft',
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'signature_status' => 'not_requested',
            'metadata' => json_encode(['flow' => 'print_template_workflow'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('orders.update', $orderId), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-11',
            'order_number' => 'ORD-2026-011',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                ['type' => 'loading', 'sequence' => 1, 'address' => 'Самара', 'normalized_data' => []],
                ['type' => 'unloading', 'sequence' => 2, 'address' => 'Уфа', 'normalized_data' => []],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 110000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 90000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [
                [
                    'type' => 'request',
                    'flow' => 'print_template_workflow',
                    'party' => 'carrier',
                    'number' => null,
                    'document_date' => null,
                    'status' => 'draft',
                    'template_id' => $printTemplateId,
                ],
                [
                    'type' => 'other',
                    'flow' => 'uploaded',
                    'party' => 'customer',
                    'number' => 'NEW-UPLOAD',
                    'document_date' => null,
                    'status' => 'signed',
                    'template_id' => null,
                    'file' => UploadedFile::fake()->create('new-upload.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);

        $response->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseHas('order_documents', [
            'id' => $printDocumentId,
            'order_id' => $orderId,
            'source' => 'print_template',
            'file_path' => 'order_documents/'.$orderId.'/draft.docx',
            'signature_status' => 'not_requested',
        ]);
        $this->assertDatabaseMissing('order_documents', [
            'order_id' => $orderId,
            'source' => 'uploaded',
            'number' => 'OLD-UPLOAD',
        ]);
        $this->assertDatabaseMissing('order_documents', [
            'id' => $artifactDocumentId,
        ]);
        $this->assertDatabaseHas('order_documents', [
            'order_id' => $orderId,
            'source' => 'uploaded',
            'number' => 'NEW-UPLOAD',
        ]);
        $this->assertSame(1, DB::table('order_documents')
            ->where('order_id', $orderId)
            ->where('source', 'print_template')
            ->count());
    }

    public function test_order_edit_print_workflow_documents_expose_template_name_for_display(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-2026-012',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'carrier_request_code',
            'name' => 'Заявка перевозчику',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/carrier/request.docx',
            'original_filename' => 'request.docx',
            'settings' => json_encode(['variables' => []], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_documents')->insert([
            'order_id' => $orderId,
            'type' => 'contract_request',
            'source' => 'print_template',
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'file_path' => 'order_documents/'.$orderId.'/draft.docx',
            'template_id' => $templateId,
            'original_name' => 'carrier_request_code.docx',
            'status' => 'draft',
            'metadata' => json_encode([
                'flow' => 'print_template_workflow',
                'template_code' => 'carrier_request_code',
                'template_name' => 'Старое название из snapshot',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('orders.edit', $orderId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.documents.0.print_template_name', 'Заявка перевозчику')
                ->where('order.documents.0.print_template_code', 'carrier_request_code')
                ->where('order.documents.0.original_name', 'carrier_request_code.docx')
            );
    }

    public function test_order_with_two_legs_persists_route_points_per_leg_and_restores_client_request_mode(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Multi Leg Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'status' => 'new',
            'client_id' => $clientId,
            'order_date' => '2026-04-05',
            'order_number' => '',
            'special_notes' => 'Split route order',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierOneId],
                ['stage' => 'leg_2', 'contractor_id' => $carrierTwoId],
            ],
            'route_points' => [
                ['stage' => 'leg_1', 'type' => 'loading', 'sequence' => 1, 'address' => 'Samara pickup', 'normalized_data' => []],
                ['stage' => 'leg_1', 'type' => 'unloading', 'sequence' => 2, 'address' => 'Kazan hub', 'normalized_data' => []],
                ['stage' => 'leg_2', 'type' => 'loading', 'sequence' => 3, 'address' => 'Kazan hub', 'normalized_data' => []],
                ['stage' => 'leg_2', 'type' => 'unloading', 'sequence' => 4, 'address' => 'Moscow delivery', 'normalized_data' => []],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 180000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_request_mode' => 'split_by_leg',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierOneId,
                        'amount' => 70000,
                        'currency' => 'RUB',
                        'payment_form' => 'vat_22',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 5,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                    [
                        'stage' => 'leg_2',
                        'contractor_id' => $carrierTwoId,
                        'amount' => 50000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 3,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $orderId = DB::table('orders')->value('id');

        $response->assertRedirect(route('orders.edit', $orderId));

        $legs = DB::table('order_legs')
            ->where('order_id', $orderId)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(2, $legs);
        $this->assertSame('leg_1', $legs[0]->description);
        $this->assertSame('leg_2', $legs[1]->description);

        $legOneId = $legs[0]->id;
        $legTwoId = $legs[1]->id;

        $this->assertDatabaseHas('route_points', [
            'order_leg_id' => $legOneId,
            'sequence' => 1,
            'type' => 'loading',
            'address' => 'Samara pickup',
        ]);
        $this->assertDatabaseHas('route_points', [
            'order_leg_id' => $legOneId,
            'sequence' => 2,
            'type' => 'unloading',
            'address' => 'Kazan hub',
        ]);
        $this->assertDatabaseHas('route_points', [
            'order_leg_id' => $legTwoId,
            'sequence' => 1,
            'type' => 'loading',
            'address' => 'Kazan hub',
        ]);
        $this->assertDatabaseHas('route_points', [
            'order_leg_id' => $legTwoId,
            'sequence' => 2,
            'type' => 'unloading',
            'address' => 'Moscow delivery',
        ]);

        $this->actingAs($admin)
            ->get(route('orders.edit', $orderId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.financial_term.client_request_mode', 'split_by_leg')
                ->where('order.route_points.0.stage', 'leg_1')
                ->where('order.route_points.1.stage', 'leg_1')
                ->where('order.route_points.2.stage', 'leg_2')
                ->where('order.route_points.3.stage', 'leg_2')
                ->where('order.route_points.3.address', 'Moscow delivery')
            );
    }

    public function test_order_with_multiple_loading_points_on_single_leg_persists(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Multi Pickup Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier Multi Pickup',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'status' => 'new',
            'client_id' => $clientId,
            'order_date' => '2026-04-05',
            'order_number' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                ['stage' => 'leg_1', 'type' => 'loading', 'sequence' => 1, 'address' => 'Samara pickup A', 'normalized_data' => []],
                ['stage' => 'leg_1', 'type' => 'loading', 'sequence' => 2, 'address' => 'Samara pickup B', 'normalized_data' => []],
                ['stage' => 'leg_1', 'type' => 'unloading', 'sequence' => 3, 'address' => 'Kazan delivery', 'normalized_data' => []],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 120000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 80000,
                        'currency' => 'RUB',
                        'payment_form' => 'vat_22',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 5,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('route_points', [
            'type' => 'loading',
            'address' => 'Samara pickup A',
        ]);
        $this->assertDatabaseHas('route_points', [
            'type' => 'loading',
            'address' => 'Samara pickup B',
        ]);
        $this->assertDatabaseHas('route_points', [
            'type' => 'unloading',
            'address' => 'Kazan delivery',
        ]);
    }

    public function test_second_order_in_same_period_recalculates_existing_orders(): void
    {
        $admin = $this->createAdminUser();

        DB::table('kpi_thresholds')->insert([
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.00',
                'threshold_to' => '0.50',
                'kpi_percent' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.00',
                'threshold_to' => '0.50',
                'kpi_percent' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.51',
                'threshold_to' => '1.00',
                'kpi_percent' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.51',
                'threshold_to' => '1.00',
                'kpi_percent' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $directCarrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Direct Carrier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indirectCarrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Indirect Carrier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('orders.store'), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-10',
            'order_number' => '',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $directCarrierId],
            ],
            'route_points' => [
                ['type' => 'loading', 'sequence' => 1, 'address' => 'A', 'normalized_data' => [], 'planned_date' => '2026-04-11', 'actual_date' => null, 'contact_person' => null, 'contact_phone' => null],
                ['type' => 'unloading', 'sequence' => 2, 'address' => 'B', 'normalized_data' => [], 'planned_date' => '2026-04-12', 'actual_date' => null, 'contact_person' => null, 'contact_phone' => null],
            ],
            'cargo_items' => [
                ['name' => 'Cargo', 'description' => '', 'weight_kg' => 10, 'volume_m3' => 1, 'package_type' => 'box', 'package_count' => 1, 'dangerous_goods' => false, 'dangerous_class' => null, 'hs_code' => '', 'cargo_type' => 'general'],
            ],
            'financial_term' => [
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => ['has_prepayment' => false, 'postpayment_days' => 5, 'postpayment_mode' => 'fttn'],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    ['stage' => 'leg_1', 'contractor_id' => $directCarrierId, 'amount' => 70000, 'currency' => 'RUB', 'payment_form' => 'vat_22', 'payment_schedule' => ['has_prepayment' => false, 'postpayment_days' => 5, 'postpayment_mode' => 'fttn']],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $firstOrderId = (int) DB::table('orders')->orderByDesc('id')->value('id');

        $this->assertDatabaseHasOrder([
            'id' => $firstOrderId,
            'kpi_percent' => '4.00',
            'delta' => '26000.00',
            'salary_accrued' => '13000.00',
        ]);

        $this->actingAs($admin)->post(route('orders.store'), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-12',
            'order_number' => '',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $indirectCarrierId],
            ],
            'route_points' => [
                ['type' => 'loading', 'sequence' => 1, 'address' => 'C', 'normalized_data' => [], 'planned_date' => '2026-04-13', 'actual_date' => null, 'contact_person' => null, 'contact_phone' => null],
                ['type' => 'unloading', 'sequence' => 2, 'address' => 'D', 'normalized_data' => [], 'planned_date' => '2026-04-14', 'actual_date' => null, 'contact_person' => null, 'contact_phone' => null],
            ],
            'cargo_items' => [
                ['name' => 'Cargo 2', 'description' => '', 'weight_kg' => 20, 'volume_m3' => 2, 'package_type' => 'box', 'package_count' => 2, 'dangerous_goods' => false, 'dangerous_class' => null, 'hs_code' => '', 'cargo_type' => 'general'],
            ],
            'financial_term' => [
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => ['has_prepayment' => false, 'postpayment_days' => 5, 'postpayment_mode' => 'fttn'],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    ['stage' => 'leg_1', 'contractor_id' => $indirectCarrierId, 'amount' => 70000, 'currency' => 'RUB', 'payment_form' => 'no_vat', 'payment_schedule' => ['has_prepayment' => false, 'postpayment_days' => 5, 'postpayment_mode' => 'fttn']],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $secondOrderId = (int) DB::table('orders')->orderByDesc('id')->value('id');

        $this->assertDatabaseHasOrder([
            'id' => $firstOrderId,
            'kpi_percent' => '4.00',
            'delta' => '26000.00',
            'salary_accrued' => '13000.00',
        ]);

        $this->assertDatabaseHasOrder([
            'id' => $secondOrderId,
            'kpi_percent' => '3.00',
            'delta' => '27000.00',
            'salary_accrued' => '13500.00',
        ]);
    }

    public function test_edit_page_restores_contractor_costs_from_order_rate_when_financial_terms_row_is_missing(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-RESTORE-001',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'carrier_id' => $carrierId,
            'customer_rate' => 150000,
            'wizard_state' => json_encode([
                'version' => 1,
                'financial_term' => [
                    'client_price' => 150000,
                    'client_currency' => 'RUB',
                    'contractors_costs' => [
                        [
                            'stage' => 'leg_1',
                            'contractor_id' => $carrierId,
                            'amount' => 88000,
                            'currency' => 'RUB',
                            'payment_form' => 'no_vat',
                            'payment_schedule' => [],
                        ],
                    ],
                ],
                'performers' => [
                    ['stage' => 'leg_1', 'contractor_id' => $carrierId, 'contractor_name' => 'Carrier'],
                ],
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->where('order.financial_term.client_price', 150000)
            ->where('order.financial_term.contractors_costs.0.contractor_id', $carrierId)
            ->where('order.financial_term.contractors_costs.0.amount', 88000)
            ->where('order.performers.0.contractor_id', $carrierId)
            ->where('order.performers.0.contractor_name', 'Carrier')
        );
    }

    public function test_edit_page_restores_performer_special_conditions_from_saved_snapshot(): void
    {
        $this->markTestSkipped('Колонка orders.performers удалена; условия хранятся в wizard_state/legs и требуют отдельного сценария.');
    }

    public function test_update_rejects_future_performer_loading_actual_date(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-FUTURE-001',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => now()->toDateString(),
            'status' => 'new',
            'customer_id' => $clientId,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $futureDate = now()->addDay()->toDateString();

        $response = $this->actingAs($admin)->from(route('orders.edit', $orderId))->patch(route('orders.update', $orderId), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => now()->toDateString(),
            'order_number' => 'ORD-FUTURE-001',
            'special_notes' => '',
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierId,
                    'loading_actual' => $futureDate,
                ],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара',
                    'normalized_data' => [],
                    'planned_date' => now()->toDateString(),
                    'actual_date' => null,
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Уфа',
                    'normalized_data' => [],
                    'planned_date' => now()->addDay()->toDateString(),
                    'actual_date' => null,
                ],
            ],
            'cargo_items' => [],
            'documents' => [],
            'financial_term' => [
                'client_price' => '1000',
                'client_currency' => 'RUB',
                'client_payment_form' => 'bank_transfer',
                'contractors_costs' => [
                    ['stage' => 'leg_1', 'contractor_id' => $carrierId, 'amount' => '800'],
                ],
            ],
        ]);

        $response->assertSessionHasErrors('performers.0.loading_actual');
    }

    public function test_edit_page_opens_with_cargos_linked_through_legs_and_legacy_order_columns_missing(): void
    {
        $this->markTestSkipped('На u_tromb нельзя дропнуть cargos.order_id из-за FK; legacy DDL не совместим с RefreshDatabase.');
    }

    public function test_edit_page_exposes_available_print_form_templates_and_downloads_docx_draft(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Заказчик',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-TPL-001',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/10/customer-request-v1.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p><w:p><w:r><w:t>${customer.name}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'customer_request',
            'name' => 'Заявка заказчика',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => $clientId,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => true,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/10/customer-request-v1.docx',
            'original_filename' => 'customer-request-v1.docx',
            'settings' => json_encode([
                'variables' => ['customer.name', 'order.number'],
                'variable_mapping' => [
                    'order.number' => 'order.order_number',
                    'customer.name' => 'customer.name',
                ],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('print_form_templates')->insert([
            'code' => 'default_request',
            'name' => 'Общий шаблон заявки',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => true,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/10/customer-request-v1.docx',
            'original_filename' => 'customer-request-v1.docx',
            'settings' => json_encode([
                'variables' => ['order.number'],
                'variable_mapping' => [
                    'order.number' => 'order.order_number',
                ],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->has('printFormTemplateOptions', 2)
            ->where('printFormTemplateOptions', fn ($options): bool => collect($options)->contains(
                fn (array $option): bool => (int) ($option['id'] ?? 0) === $templateId
                    && ($option['contractor_name'] ?? '') === 'ООО Заказчик'
            ))
            ->where('printFormTemplateOptions', fn ($options): bool => collect($options)->contains(
                fn (array $option): bool => ($option['is_default'] ?? false) === true
            ))
        );

        $downloadResponse = $this->actingAs($admin)->get(route('orders.templates.generate-draft', [
            'order' => $orderId,
            'printFormTemplate' => $templateId,
        ]));

        $downloadResponse->assertOk();
        $downloadResponse->assertDownload('customer-request-order-'.$orderId.'-draft.docx');

        $downloadedPath = $downloadResponse->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($downloadedPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('ORD-TPL-001', $documentXml);
        $this->assertStringContainsString('ООО Заказчик', $documentXml);

        $previewResponse = $this->actingAs($admin)->get(route('orders.templates.generate-draft', [
            'order' => $orderId,
            'printFormTemplate' => $templateId,
            'preview' => 1,
        ]));

        $previewResponse->assertOk();
        $this->assertStringContainsString('wordprocessingml', strtolower($previewResponse->headers->get('content-type') ?? ''));
        $this->assertStringContainsString('inline', strtolower($previewResponse->headers->get('content-disposition') ?? ''));
    }

    public function test_print_form_workflow_persists_document_and_completes_approval_and_finalize(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Заказчик WF',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WF-002',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/10/wf-flow.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'wf_flow',
            'name' => 'Заявка WF',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/10/wf-flow.docx',
            'original_filename' => 'wf-flow.docx',
            'settings' => json_encode([
                'variables' => ['order.number'],
                'variable_mapping' => [
                    'order.number' => 'order.order_number',
                ],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('orders.documents.from-template', $orderId), [
            'print_form_template_id' => $templateId,
        ])->assertRedirect(route('orders.edit', $orderId));

        $documentId = DB::table('order_documents')->where('order_id', $orderId)->value('id');
        $this->assertNotNull($documentId);

        $this->actingAs($admin)->get(route('orders.documents.preview-draft', [$orderId, $documentId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/PrintWorkflowDocumentPreview')
                ->where('orderId', (int) $orderId)
                ->where('documentId', (int) $documentId)
                ->where('canRequestApproval', true)
            );

        $this->assertDatabaseHas('order_documents', [
            'id' => $documentId,
            'order_id' => $orderId,
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'source' => 'print_template',
        ]);

        $this->actingAs($admin)->post(route('orders.documents.request-approval', [$orderId, $documentId]))
            ->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseHas('order_documents', [
            'id' => $documentId,
            'workflow_status' => OrderDocumentWorkflowStatus::PENDING_APPROVAL,
        ]);

        $this->actingAs($admin)->post(route('orders.documents.approve', [$orderId, $documentId]))
            ->assertRedirect(route('orders.edit', $orderId).'?tab=documents');

        $this->assertDatabaseHas('order_documents', [
            'id' => $documentId,
            'workflow_status' => OrderDocumentWorkflowStatus::APPROVED,
        ]);

        $pdf = UploadedFile::fake()->create('final.pdf', 100, 'application/pdf');

        $this->actingAs($admin)->post(route('orders.documents.finalize', [$orderId, $documentId]), [
            'pdf' => $pdf,
        ])->assertRedirect(route('orders.edit', $orderId));

        /** @var array{type: string, message: string}|null $flash */
        $flash = session('flash');
        $this->assertIsArray($flash);
        $this->assertArrayHasKey('message', $flash);
        $this->assertStringContainsString('папке заказа', $flash['message']);

        $this->assertDatabaseHas('order_documents', [
            'id' => $documentId,
            'workflow_status' => OrderDocumentWorkflowStatus::FINALIZED,
        ]);

        $path = (string) DB::table('order_documents')->where('id', $documentId)->value('generated_pdf_path');
        $this->assertStringStartsWith('order_documents/'.$orderId.'/', $path);
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertStringContainsString('final', $path);
    }

    public function test_regenerate_draft_clears_cached_browser_preview_pdf_metadata(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Заказчик кэш PDF',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WF-CACHE',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/10/wf-cache.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'wf_cache',
            'name' => 'Заявка кэш',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/10/wf-cache.docx',
            'original_filename' => 'wf-cache.docx',
            'settings' => json_encode([
                'variables' => ['order.number'],
                'variable_mapping' => [
                    'order.number' => 'order.order_number',
                ],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('orders.documents.from-template', $orderId), [
            'print_form_template_id' => $templateId,
        ])->assertRedirect(route('orders.edit', $orderId));

        $documentId = (int) DB::table('order_documents')->where('order_id', $orderId)->value('id');
        $this->assertNotSame(0, $documentId);

        $stalePreview = 'order_documents/'.$orderId.'/stale-preview.pdf';
        Storage::disk('local')->put($stalePreview, '%PDF-1.4 fake');

        $row = DB::table('order_documents')->where('id', $documentId)->first();
        $this->assertNotNull($row);
        $metadata = json_decode((string) $row->metadata, true, 512, JSON_THROW_ON_ERROR);
        $metadata['preview_pdf_path'] = $stalePreview;
        $metadata['preview_pdf_storage_driver'] = 'local';
        $metadata['preview_pdf_generated_at'] = now()->toIso8601String();
        $metadata['preview_pdf_source_docx_path'] = 'order_documents/'.$orderId.'/nonexistent-old.docx';
        $metadata['preview_pdf_source_docx_size'] = 999;

        DB::table('order_documents')->where('id', $documentId)->update([
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);

        $this->assertTrue(Storage::disk('local')->exists($stalePreview));

        $this->actingAs($admin)->post(route('orders.documents.regenerate-draft', [$orderId, $documentId]))
            ->assertRedirect(route('orders.edit', $orderId));

        $metadataAfter = json_decode((string) DB::table('order_documents')->where('id', $documentId)->value('metadata'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('preview_pdf_path', $metadataAfter);
        $this->assertArrayNotHasKey('preview_pdf_source_docx_path', $metadataAfter);
        $this->assertFalse(Storage::disk('local')->exists($stalePreview));
    }

    public function test_settings_user_can_update_template_overlay_positions(): void
    {
        $this->markTestSkipped('Маршрут settings.templates.update-overlay-positions удалён; overlay сохраняется через orders.documents.update-overlay-positions.');
    }

    public function test_admin_without_signing_authority_cannot_approve_print_document(): void
    {
        $admin = User::factory()->create([
            'has_signing_authority' => false,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['orders', 'settings']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $admin->id)->update(['role_id' => $roleId]);
        $admin->role_id = $roleId;

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client No Sign',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WF-NOSIGN',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put('order_documents/'.$orderId.'/pending.docx', 'draft');

        $documentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'request',
            'document_group' => null,
            'source' => 'print_template',
            'number' => null,
            'document_date' => null,
            'original_name' => 'pending.docx',
            'file_path' => 'order_documents/'.$orderId.'/pending.docx',
            'generated_pdf_path' => null,
            'template_id' => null,
            'status' => 'pending',
            'workflow_status' => OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'signature_status' => 'not_requested',
            'metadata' => json_encode(['flow' => 'print_template_workflow'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('orders.documents.approve', [$orderId, $documentId]))
            ->assertForbidden();
    }

    public function test_signing_authority_user_can_approve_reject_or_discard_while_pending_approval(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $signer = User::factory()->create([
            'role_id' => $managerRoleId,
            'has_signing_authority' => true,
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client Sign',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WF-SIGNER',
            'company_code' => 'TST',
            'manager_id' => $signer->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put('order_documents/'.$orderId.'/pending.docx', 'draft');

        $documentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'request',
            'document_group' => null,
            'source' => 'print_template',
            'number' => null,
            'document_date' => null,
            'original_name' => 'pending.docx',
            'file_path' => 'order_documents/'.$orderId.'/pending.docx',
            'generated_pdf_path' => null,
            'template_id' => null,
            'status' => 'pending',
            'workflow_status' => OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'signature_status' => 'not_requested',
            'metadata' => json_encode(['flow' => 'print_template_workflow'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($signer)->get(route('orders.edit', $orderId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.documents.0.id', $documentId)
                ->where('order.documents.0.can_approve', true)
                ->where('order.documents.0.can_reject', true)
                ->where('order.documents.0.can_request_approval', false)
                ->where('order.documents.0.can_regenerate_draft', false)
                ->where('order.documents.0.can_finalize', false)
                ->where('order.documents.0.can_discard_print_draft', true)
            );

        $this->actingAs($signer)
            ->post(route('orders.documents.request-approval', [$orderId, $documentId]))
            ->assertForbidden();

        $this->actingAs($signer)
            ->post(route('orders.documents.regenerate-draft', [$orderId, $documentId]))
            ->assertForbidden();

        $this->actingAs($signer)
            ->post(route('orders.documents.finalize', [$orderId, $documentId]), [
                'pdf' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($signer)
            ->delete(route('orders.documents.discard-print-workflow', [$orderId, $documentId]))
            ->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseMissing('order_documents', ['id' => $documentId]);
    }

    public function test_print_workflow_discard_removes_draft_and_deletes_file(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client Discard',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WF-DISC',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/99/wf-discard.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'wf_discard',
            'name' => 'Discard test',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/99/wf-discard.docx',
            'original_filename' => 'wf-discard.docx',
            'settings' => json_encode([
                'variables' => ['order.number'],
                'variable_mapping' => [
                    'order.number' => 'order.order_number',
                ],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('orders.documents.from-template', $orderId), [
            'print_form_template_id' => $templateId,
        ])->assertRedirect(route('orders.edit', $orderId));

        $documentId = (int) DB::table('order_documents')->where('order_id', $orderId)->value('id');
        $filePath = (string) DB::table('order_documents')->where('id', $documentId)->value('file_path');
        $this->assertNotSame('', $filePath);

        $this->actingAs($admin)->delete(route('orders.documents.discard-print-workflow', [$orderId, $documentId]))
            ->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseMissing('order_documents', ['id' => $documentId]);
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_print_workflow_discard_rejects_finalized_document(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WF-NO-DISC',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put('order_documents/'.$orderId.'/keep.docx', 'x');
        Storage::disk('local')->put('order_documents/'.$orderId.'/final.pdf', 'pdf');

        $documentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'request',
            'document_group' => null,
            'source' => 'print_template',
            'number' => null,
            'document_date' => null,
            'original_name' => 'x.docx',
            'file_path' => 'order_documents/'.$orderId.'/keep.docx',
            'generated_pdf_path' => 'order_documents/'.$orderId.'/final.pdf',
            'template_id' => null,
            'status' => 'signed',
            'workflow_status' => OrderDocumentWorkflowStatus::FINALIZED,
            'signature_status' => 'signed_internal',
            'metadata' => json_encode(['flow' => 'print_template_workflow'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('orders.documents.discard-print-workflow', [$orderId, $documentId]))
            ->assertStatus(422);

        $this->assertDatabaseHas('order_documents', ['id' => $documentId]);
    }

    public function test_order_create_page_exposes_contractor_credit_policy_and_default_terms(): void
    {
        $admin = $this->createAdminUser();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'debt_limit' => 125000,
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => true,
            'default_customer_payment_schedule' => json_encode([
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 7,
                'postpayment_mode' => 'ottn',
            ], JSON_UNESCAPED_UNICODE),
            'default_customer_payment_form' => 'vat_22',
            'default_customer_payment_term' => '7 дн OTTN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-DEBT-001',
            'status' => 'payment',
            'customer_id' => $contractorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 130000,
            'planned_date' => '2026-04-03',
            'status' => 'overdue',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('contractors.0.default_customer_payment_form', 'vat_22')
            ->where('contractors.0.default_customer_payment_term', '7 дн OTTN')
            ->where('contractors.0.default_customer_payment_schedule.postpayment_days', 7)
            ->where('contractors.0.default_customer_payment_schedule.postpayment_mode', 'ottn')
            ->where('contractors.0.current_debt', 130000)
            ->where('contractors.0.debt_limit_reached', true)
        );
    }

    public function test_order_creation_is_blocked_when_customer_debt_limit_is_reached(): void
    {
        $admin = $this->createAdminUser();

        DB::table('kpi_settings')->insert([
            'key' => 'delta_bonus_multiplier',
            'value' => '1.00',
            'type' => 'float',
            'group' => 'delta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Blocked Client',
            'debt_limit' => 100000,
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => true,
            'default_customer_payment_schedule' => json_encode([
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 7,
                'postpayment_mode' => 'ottn',
            ], JSON_UNESCAPED_UNICODE),
            'default_customer_payment_form' => 'vat_22',
            'default_customer_payment_term' => '7 дн OTTN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier',
            'default_carrier_payment_schedule' => json_encode([
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 5,
                'postpayment_mode' => 'ottn',
            ], JSON_UNESCAPED_UNICODE),
            'default_carrier_payment_form' => 'no_vat',
            'default_carrier_payment_term' => '5 дн OTTN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacyOrderId = $this->insertOrderRow([
            'order_number' => 'ORD-LEGACY-DEBT',
            'status' => 'payment',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $legacyOrderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'planned_date' => '2026-04-02',
            'status' => 'overdue',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->from(route('orders.create'))->post(route('orders.store'), [
            'status' => 'new',
            'client_id' => $clientId,
            'order_date' => '2026-04-03',
            'order_number' => '',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                ['type' => 'loading', 'sequence' => 1, 'address' => 'Самара', 'normalized_data' => []],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 1000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 500,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 5,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $response->assertRedirect(route('orders.create'));
        $response->assertSessionHasErrors('client_id');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_calculate_compensation_uses_payment_forms_for_deal_type(): void
    {
        $admin = $this->createAdminUser();

        DB::table('kpi_thresholds')->insert([
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.00',
                'threshold_to' => '1.00',
                'kpi_percent' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.00',
                'threshold_to' => '1.00',
                'kpi_percent' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('kpi_settings')->insert([
            'key' => 'delta_bonus_multiplier',
            'value' => '1.30',
            'type' => 'float',
            'group' => 'delta',
            'description' => 'Multiplier',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('salary_coefficients')->insert([
            'manager_id' => $admin->id,
            'base_salary' => 0,
            'bonus_percent' => 0,
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $basePayload = [
            'customer_rate' => 1000,
            'carrier_rate' => 400,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
        ];

        $this->actingAs($admin)->postJson(route('orders.calculate-compensation'), array_merge($basePayload, [
            'customer_payment_form' => 'vat_22',
            'contractors_costs' => [
                ['payment_form' => 'vat_22', 'amount' => 400],
            ],
        ]))->assertOk()
            ->assertJson(['deal_type' => 'vat_all']);

        $this->actingAs($admin)->postJson(route('orders.calculate-compensation'), array_merge($basePayload, [
            'customer_payment_form' => 'vat_22',
            'contractors_costs' => [
                ['payment_form' => 'no_vat', 'amount' => 400],
            ],
        ]))->assertOk()
            ->assertJson(['deal_type' => 'vat']);
    }

    public function test_edit_page_uses_financial_terms_costs_when_wizard_state_contractors_costs_empty(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier WS',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WS-EMPTY-CC',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'carrier_id' => $carrierId,
            'customer_rate' => 100000,
            'carrier_rate' => 45000,
            'performers' => json_encode([
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ], JSON_THROW_ON_ERROR),
            'wizard_state' => json_encode([
                'version' => 1,
                'financial_term' => [
                    'client_price' => 100000,
                    'contractors_costs' => [],
                    'client_currency' => 'RUB',
                ],
                'performers' => [],
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 100000,
            'client_currency' => 'RUB',
            'client_payment_terms' => null,
            'contractors_costs' => json_encode([
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierId,
                    'amount' => 45000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'total_cost' => 45000,
            'margin' => 0,
            'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->where('order.financial_term.contractors_costs.0.contractor_id', $carrierId)
            ->where('order.financial_term.contractors_costs.0.amount', 45000)
        );
    }

    public function test_edit_page_keeps_additional_cost_contractor_when_wizard_state_snapshot_is_stale(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client AC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subcontractorId = DB::table('contractors')->insertGetId([
            'type' => 'contractor',
            'name' => 'Subcontractor AC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $additionalRowId = 'additional-row-1';

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-WS-AC',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'customer_rate' => 100000,
            'performers' => json_encode([
                ['stage' => 'leg_1', 'contractor_id' => null],
            ], JSON_THROW_ON_ERROR),
            'wizard_state' => json_encode([
                'version' => 1,
                'financial_term' => [
                    'client_price' => 100000,
                    'contractors_costs' => [],
                    'additional_costs' => [
                        [
                            'id' => $additionalRowId,
                            'contractor_id' => null,
                            'amount' => 5000,
                            'currency' => 'RUB',
                            'payment_form' => 'no_vat',
                            'payment_schedule' => [],
                            'payment_terms' => '',
                        ],
                    ],
                    'client_currency' => 'RUB',
                ],
                'performers' => [],
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 100000,
            'client_currency' => 'RUB',
            'client_payment_terms' => null,
            'contractors_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'total_cost' => 5000,
            'margin' => 0,
            'additional_costs' => json_encode([
                [
                    'id' => $additionalRowId,
                    'contractor_id' => $subcontractorId,
                    'contractor_name' => 'Subcontractor AC',
                    'service_date' => '2026-04-01',
                    'amount' => 5000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [],
                    'payment_terms' => '',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->where('order.financial_term.additional_costs.0.contractor_id', $subcontractorId)
            ->where('order.financial_term.additional_costs.0.contractor_name', 'Subcontractor AC')
        );
    }

    public function test_edit_page_includes_payment_settlement_payload(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-PS',
            'manager_id' => $admin->id,
            'customer_id' => $clientId,
            'carrier_id' => $carrierId,
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat_22',
            'carrier_rate' => 50000,
            'carrier_payment_form' => 'no_vat',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'planned_date' => '2026-04-01',
            'actual_date' => '2026-04-05',
            'status' => 'paid',
            'paid_amount' => 100000,
            'remaining_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'counterparty_id' => $carrierId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 50000,
            'planned_date' => '2026-04-10',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->has('order.payment_settlement.lines', 2)
            ->where('order.payment_settlement.lines.0.party', 'customer')
            ->where('order.payment_settlement.lines.0.state', 'complete')
            ->where('order.payment_settlement.lines.0.percent_paid', 100)
            ->where('order.payment_settlement.lines.0.last_payment_at', '2026-04-05')
            ->where('order.payment_settlement.lines.1.party', 'carrier')
            ->where('order.payment_settlement.lines.1.state', 'none')
            ->where('order.payment_settlement.lines.1.percent_paid', 0)
        );
    }

    public function test_admin_can_save_order_with_actual_route_date_without_planned_date(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент ФактДата',
            'inn' => '1112223334',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ООО Своя компания ФактДата',
            'inn' => '4445556667',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик ФактДата',
            'inn' => '7778889990',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'status' => 'new',
            'client_id' => $clientId,
            'own_company_id' => $ownCompanyId,
            'order_date' => '2026-04-10',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'financial_term' => [
                'client_price' => 95000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 5,
                    'postpayment_mode' => 'fttn',
                ],
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 70000,
                        'currency' => 'RUB',
                        'payment_form' => 'vat_22',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 5,
                            'postpayment_mode' => 'fttn',
                        ],
                    ],
                ],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Москва, Тестовая, 10',
                    'planned_date' => null,
                    'actual_date' => '2026-04-11',
                ],
                [
                    'type' => null,
                    'sequence' => 2,
                    'address' => '',
                    'actual_date' => '2026-04-12',
                ],
            ],
            'cargo_items' => [
                [
                    'name' => '',
                    'cargo_type' => null,
                ],
            ],
        ]);

        $orderId = DB::table('orders')->value('id');

        $response->assertRedirect(route('orders.edit', $orderId));
        $this->assertDatabaseHas('route_points', [
            'address' => 'Москва, Тестовая, 10',
            'planned_date' => null,
            'actual_date' => '2026-04-11 00:00:00',
        ]);
        $this->assertDatabaseMissing('route_points', [
            'actual_date' => '2026-04-12 00:00:00',
        ]);
    }

    public function test_order_creation_requires_carrier_and_client_price(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент обязательные поля',
            'inn' => '1231231231',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('orders.create'))
            ->post(route('orders.store'), [
                'status' => 'new',
                'client_id' => $clientId,
                'order_date' => '2026-04-10',
                'performers' => [
                    ['stage' => 'leg_1', 'contractor_id' => null],
                ],
                'financial_term' => [
                    'client_price' => 0,
                    'client_currency' => 'RUB',
                    'contractors_costs' => [],
                ],
            ]);

        $response->assertRedirect(route('orders.create'));
        $response->assertSessionHasErrors([
            'performers',
            'financial_term.client_price',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_manager_cannot_update_order_when_all_print_workflow_documents_are_finalized(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role_id' => $managerRoleId,
            'has_signing_authority' => false,
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент lock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик lock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-LOCK-1',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'lock_tpl_1',
            'name' => 'LockTpl',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/lock/1.docx',
            'original_filename' => '1.docx',
            'settings' => json_encode(['variables' => []], JSON_THROW_ON_ERROR),
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_documents')->insert([
            'order_id' => $orderId,
            'type' => 'contract_request',
            'source' => 'print_template',
            'workflow_status' => OrderDocumentWorkflowStatus::FINALIZED,
            'generated_pdf_path' => 'order_documents/'.$orderId.'/a-final.pdf',
            'template_id' => $templateId,
            'original_name' => 'Заявка',
            'status' => 'sent',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patchPayload = [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-02',
            'order_number' => 'ORD-LOCK-1',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара',
                    'normalized_data' => [],
                    'planned_date' => '2026-04-02',
                    'actual_date' => null,
                    'contact_person' => null,
                    'contact_phone' => null,
                    'sender_name' => 'ООО Отправитель',
                    'sender_contact' => 'Диспетчер',
                    'sender_phone' => '+79990000003',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Уфа',
                    'normalized_data' => [],
                    'planned_date' => '2026-04-03',
                    'actual_date' => null,
                    'contact_person' => null,
                    'contact_phone' => null,
                    'recipient_name' => 'ООО Получатель',
                    'recipient_contact' => 'Приемка',
                    'recipient_phone' => '+79990000004',
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 150000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 5,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 99000.50,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 3,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ];

        $this->actingAs($manager)->patch(route('orders.update', $orderId), $patchPayload)->assertForbidden();

        $template2Id = DB::table('print_form_templates')->insertGetId([
            'code' => 'lock_tpl_2',
            'name' => 'LockTpl2',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/lock/2.docx',
            'original_filename' => '2.docx',
            'settings' => json_encode(['variables' => []], JSON_THROW_ON_ERROR),
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->post(route('orders.documents.from-template', $orderId), [
            'print_form_template_id' => $template2Id,
        ])->assertForbidden();
    }

    public function test_manager_can_update_order_when_at_least_one_print_workflow_document_is_not_finalized(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role_id' => $managerRoleId,
            'has_signing_authority' => false,
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент partial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик partial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-PART-1',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateA = DB::table('print_form_templates')->insertGetId([
            'code' => 'part_tpl_a',
            'name' => 'PartA',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/part/a.docx',
            'original_filename' => 'a.docx',
            'settings' => json_encode(['variables' => []], JSON_THROW_ON_ERROR),
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateB = DB::table('print_form_templates')->insertGetId([
            'code' => 'part_tpl_b',
            'name' => 'PartB',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/part/b.docx',
            'original_filename' => 'b.docx',
            'settings' => json_encode(['variables' => []], JSON_THROW_ON_ERROR),
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_documents')->insert([
            'order_id' => $orderId,
            'type' => 'contract_request',
            'source' => 'print_template',
            'workflow_status' => OrderDocumentWorkflowStatus::FINALIZED,
            'generated_pdf_path' => 'order_documents/'.$orderId.'/a-final.pdf',
            'template_id' => $templateA,
            'original_name' => 'Заявка заказчик',
            'status' => 'sent',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_documents')->insert([
            'order_id' => $orderId,
            'type' => 'contract_request',
            'source' => 'print_template',
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'file_path' => 'order_documents/'.$orderId.'/draft.docx',
            'generated_pdf_path' => null,
            'template_id' => $templateB,
            'original_name' => 'Заявка перевозчик',
            'status' => 'draft',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patchPayload = [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-02',
            'order_number' => 'ORD-PART-1',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара',
                    'normalized_data' => [],
                    'planned_date' => '2026-04-02',
                    'actual_date' => null,
                    'contact_person' => null,
                    'contact_phone' => null,
                    'sender_name' => 'ООО Отправитель',
                    'sender_contact' => 'Диспетчер',
                    'sender_phone' => '+79990000003',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Уфа',
                    'normalized_data' => [],
                    'planned_date' => '2026-04-03',
                    'actual_date' => null,
                    'contact_person' => null,
                    'contact_phone' => null,
                    'recipient_name' => 'ООО Получатель',
                    'recipient_contact' => 'Приемка',
                    'recipient_phone' => '+79990000004',
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 150000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 5,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 99000.50,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 3,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ];

        $this->actingAs($manager)->patch(route('orders.update', $orderId), $patchPayload)->assertRedirect(route('orders.edit', $orderId));
    }

    public function test_store_order_request_accepts_is_international_transport_flag(): void
    {
        $rules = (new StoreOrderRequest)->rules();

        $validator = Validator::make(
            ['is_international_transport' => true],
            ['is_international_transport' => $rules['is_international_transport']],
        );

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    public function test_order_edit_exposes_lead_precalculation_snapshot(): void
    {
        $admin = $this->createAdminUser();

        $order = Order::factory()->create([
            'manager_id' => $admin->id,
            'order_number' => 'ORD-PREC-SNAP-1',
            'is_active' => true,
        ]);

        $snapshot = [
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
                    'title' => 'Доставка',
                    'amount' => 40000,
                    'currency' => 'RUB',
                ],
            ],
            'computed' => [
                'goods_total' => 0,
                'services_total' => 40000,
                'grand_total' => 40000,
                'goods_lines' => [],
            ],
        ];

        if (Schema::hasColumn('orders', 'metadata')) {
            $order->forceFill([
                'metadata' => [
                    'lead_precalculation_snapshot' => $snapshot,
                    'lead_precalculation_snapshot_at' => now()->toIso8601String(),
                ],
            ])->saveQuietly();
        } elseif (Schema::hasColumn('orders', 'wizard_state')) {
            $order->forceFill([
                'wizard_state' => [
                    'lead_precalculation_snapshot' => $snapshot,
                    'lead_precalculation_snapshot_at' => now()->toIso8601String(),
                ],
            ])->saveQuietly();
        } else {
            $this->markTestSkipped('orders.metadata / wizard_state column missing');
        }

        $this->actingAs($admin)
            ->get(route('orders.edit', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Wizard')
                ->has('order.lead_precalculation_snapshot.precalculation')
                ->where('order.lead_precalculation_snapshot.precalculation.status', 'ready')
            );
    }

    public function test_order_lead_precalculation_snapshot_document_returns_html(): void
    {
        $admin = $this->createAdminUser();

        $order = Order::factory()->create([
            'manager_id' => $admin->id,
            'order_number' => 'ORD-PREC-DOC-1',
            'is_active' => true,
        ]);

        $snapshot = [
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
                    'amount' => 15000,
                    'currency' => 'RUB',
                ],
            ],
        ];

        if (Schema::hasColumn('orders', 'metadata')) {
            $order->forceFill(['metadata' => ['lead_precalculation_snapshot' => $snapshot]])->saveQuietly();
        } elseif (Schema::hasColumn('orders', 'wizard_state')) {
            $order->forceFill(['wizard_state' => ['lead_precalculation_snapshot' => $snapshot]])->saveQuietly();
        } else {
            $this->markTestSkipped('orders.metadata / wizard_state column missing');
        }

        $this->actingAs($admin)
            ->get(route('orders.lead-precalculation-snapshot.document', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Коммерческий предрасчёт', false)
            ->assertSee('ORD-PREC-DOC-1', false)
            ->assertSee('Брокер', false);
    }

    public function test_order_wizard_persists_owner_dispatcher_and_compensation_split(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id') || ! Schema::hasColumn('orders', 'dispatcher_id')) {
            $this->markTestSkipped('order_owner_id / dispatcher_id columns missing');
        }

        $admin = $this->createAdminUser();
        $owner = User::factory()->create(['role_id' => $admin->role_id]);
        $dispatcher = User::factory()->create(['role_id' => $admin->role_id]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент роли',
            'inn' => '7700000001',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик роли',
            'inn' => '7700000002',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $minimalPayload = [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-07-08',
            'order_number' => '',
            'order_owner_id' => $owner->id,
            'dispatcher_id' => $dispatcher->id,
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Москва',
                    'normalized_data' => [],
                    'planned_date' => '2026-07-09',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Тула',
                    'normalized_data' => [],
                    'planned_date' => '2026-07-10',
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 5,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 5,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 80000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 3,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('orders.store'), $minimalPayload)
            ->assertRedirect();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame($owner->id, (int) $order->order_owner_id);
        $this->assertSame($owner->id, (int) $order->manager_id);
        $this->assertSame($dispatcher->id, (int) $order->dispatcher_id);

        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $this->assertSame($owner->id, $metadata['compensation_split']['order_owner_id'] ?? null);
        $this->assertSame($dispatcher->id, $metadata['compensation_split']['dispatcher_id'] ?? null);

        $this->actingAs($admin)
            ->patch(route('orders.update', $order->id), [
                ...$minimalPayload,
                'order_number' => $order->order_number,
                'compensation_owner_percent' => 70,
                'compensation_dispatcher_percent' => 30,
            ])
            ->assertRedirect();

        $order->refresh();
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $this->assertSame(70.0, (float) ($metadata['compensation_split']['order_owner_percent'] ?? 0));
        $this->assertSame(30.0, (float) ($metadata['compensation_split']['dispatcher_percent'] ?? 0));

        $this->actingAs($admin)
            ->get(route('orders.edit', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Wizard')
                ->where('order.order_owner_id', $owner->id)
                ->where('order.dispatcher_id', $dispatcher->id)
            );
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
