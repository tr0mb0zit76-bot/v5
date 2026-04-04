<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class TemplateManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('print_form_templates');
        Schema::dropIfExists('lead_offers');
        Schema::dropIfExists('lead_cargo_items');
        Schema::dropIfExists('lead_route_points');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->json('columns_config')->nullable();
            $table->boolean('has_signing_authority')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('site_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('theme', 20)->default('light');
            $table->boolean('is_active')->default(true);
            $table->boolean('has_signing_authority')->default(false);
            $table->json('ai_preferences')->nullable();
            $table->boolean('ai_learning_enabled')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ogrn')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bik', 9)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('correspondent_account', 20)->nullable();
            $table->string('signer_name_nominative')->nullable();
            $table->string('signer_name_prepositional')->nullable();
            $table->string('signer_authority_basis')->nullable();
            $table->timestamps();
        });

        Schema::create('print_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('entity_type', 50)->default('order');
            $table->string('document_type', 50);
            $table->string('document_group', 50);
            $table->string('party', 50)->default('internal');
            $table->string('source_type', 50)->default('system');
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('vue_component', 255);
            $table->string('pdf_view', 255)->nullable();
            $table->boolean('requires_internal_signature')->default(true);
            $table->boolean('requires_counterparty_signature')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->string('file_disk', 50)->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->date('order_date')->nullable();
            $table->date('loading_date')->nullable();
            $table->date('unloading_date')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->string('customer_payment_form', 50)->nullable();
            $table->string('customer_payment_term', 50)->nullable();
            $table->string('carrier_payment_form', 50)->nullable();
            $table->string('carrier_payment_term', 50)->nullable();
            $table->string('status', 50)->default('draft');
            $table->text('special_notes')->nullable();
            $table->string('cargo_sender_name')->nullable();
            $table->string('cargo_sender_address')->nullable();
            $table->string('cargo_sender_contact')->nullable();
            $table->string('cargo_sender_phone', 50)->nullable();
            $table->string('cargo_recipient_name')->nullable();
            $table->string('cargo_recipient_address')->nullable();
            $table->string('cargo_recipient_contact')->nullable();
            $table->string('cargo_recipient_phone', 50)->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('waybill_number')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('own_company_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->string('customer_contact_name')->nullable();
            $table->string('customer_contact_phone', 50)->nullable();
            $table->string('customer_contact_email')->nullable();
            $table->string('carrier_contact_name')->nullable();
            $table->string('carrier_contact_phone', 50)->nullable();
            $table->string('carrier_contact_email')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new');
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('transport_type', 100)->nullable();
            $table->string('loading_location')->nullable();
            $table->string('unloading_location')->nullable();
            $table->date('planned_shipping_date')->nullable();
            $table->decimal('target_price', 12, 2)->nullable();
            $table->string('target_currency', 3)->default('RUB');
            $table->decimal('calculated_cost', 12, 2)->nullable();
            $table->decimal('expected_margin', 12, 2)->nullable();
            $table->timestamp('next_contact_at')->nullable();
            $table->json('lead_qualification')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('type', 50);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('address', 500);
            $table->json('normalized_data')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_cargo_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('name');
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->decimal('volume_m3', 10, 2)->nullable();
            $table->unsignedInteger('package_count')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('number')->nullable();
            $table->date('offer_date')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();
        });
    }

    public function test_admin_can_open_templates_page_with_existing_templates(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Тестовый заказчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('print_form_templates')->insert([
            'code' => 'customer_request_default',
            'name' => 'Договор-заявка заказчика',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => $contractorId,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => true,
            'is_active' => true,
            'version' => 1,
            'original_filename' => 'request.docx',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('settings.templates.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Templates')
            ->has('templates', 1)
            ->where('templates.0.code', 'customer_request_default')
            ->where('templates.0.contractor_name', 'ООО Тестовый заказчик')
            ->where('templates.0.variables', [])
            ->where('orderVariableOptions.15.value', 'cargo_sender.name')
            ->where('orderVariableOptions.33.value', 'customer.bank_name')
            ->where('orderVariableOptions.70.value', 'driver.full_name')
            ->where('orderVariableOptions.80.value', 'route.loading_cities')
            ->where('leadVariableOptions.0.value', 'lead.id')
            ->where('leadVariableOptions.20.value', 'counterparty.name')
            ->where('leadVariableOptions.47.value', 'cargo.summary')
            ->has('contractorOptions', 1)
        );
    }

    public function test_admin_can_create_external_docx_template(): void
    {
        Storage::fake('local');

        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Перевозчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('settings.templates.store'), [
            'code' => 'carrier_contract_request',
            'name' => 'Договор-заявка перевозчика',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'carrier',
            'source_type' => 'external_docx',
            'contractor_id' => $contractorId,
            'is_default' => false,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => true,
            'is_active' => true,
            'source_file' => $this->makeDocxUpload('carrier-request.docx', [
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r><w:r><w:t>${contractor.name}</w:t></w:r></w:p></w:body></w:document>',
            ]),
        ]);

        $response->assertRedirect(route('settings.templates.index'));
        $this->assertDatabaseHas('print_form_templates', [
            'code' => 'carrier_contract_request',
            'source_type' => 'external_docx',
            'contractor_id' => $contractorId,
            'original_filename' => 'carrier-request.docx',
        ]);

        $template = DB::table('print_form_templates')->where('code', 'carrier_contract_request')->first();

        Storage::disk('local')->assertExists($template->file_path);
        $settings = json_decode($template->settings, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['contractor.name', 'order.number'], $settings['variables']);
        $this->assertSame('placeholders_ready', $settings['pipeline_status']);
    }

    public function test_admin_can_update_template_assignment_and_flags(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'order_offer',
            'name' => 'Коммерческое предложение',
            'entity_type' => 'lead',
            'document_type' => 'offer',
            'document_group' => 'commercial',
            'party' => 'internal',
            'source_type' => 'system',
            'is_default' => false,
            'vue_component' => 'SystemPrintFormTemplate',
            'requires_internal_signature' => false,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('settings.templates.update', $templateId), [
            'code' => 'order_offer',
            'name' => 'Коммерческое предложение v2',
            'entity_type' => 'order',
            'document_type' => 'offer',
            'document_group' => 'commercial',
            'party' => 'customer',
            'source_type' => 'system',
            'contractor_id' => $contractorId,
            'is_default' => true,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('settings.templates.index'));
        $this->assertDatabaseHas('print_form_templates', [
            'id' => $templateId,
            'name' => 'Коммерческое предложение v2',
            'entity_type' => 'order',
            'party' => 'customer',
            'contractor_id' => $contractorId,
            'is_default' => true,
            'requires_internal_signature' => true,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_save_variable_mapping_and_download_draft_docx(): void
    {
        Storage::fake('local');

        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId, 'name' => 'Руководитель']);
        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Заказчик',
            'bank_name' => 'АО Банк Тест',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'customer_request_template',
            'name' => 'Договор-заявка',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'is_default' => true,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => true,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/1/customer-request-template-v1.docx',
            'original_filename' => 'customer-request-template.docx',
            'settings' => json_encode([
                'variables' => ['order.number', 'customer.name', 'customer.bank_name', 'cargo_sender.address'],
                'variable_mapping' => [],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/1/customer-request-template-v1.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p><w:p><w:r><w:t>${customer.name}</w:t></w:r></w:p><w:p><w:r><w:t>${customer.bank_name}</w:t></w:r></w:p><w:p><w:r><w:t>${cargo_sender.address}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-125',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $customerId,
            'cargo_sender_address' => 'Самара, Складская, 7',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updateResponse = $this->actingAs($admin)->patch(route('settings.templates.update', $templateId), [
            'code' => 'customer_request_template',
            'name' => 'Договор-заявка',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => true,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => true,
            'is_active' => true,
            'variable_mappings' => [
                ['placeholder' => 'order.number', 'source_path' => 'order.order_number'],
                ['placeholder' => 'customer.name', 'source_path' => 'customer.name'],
                ['placeholder' => 'customer.bank_name', 'source_path' => 'customer.bank_name'],
                ['placeholder' => 'cargo_sender.address', 'source_path' => 'cargo_sender.address'],
            ],
        ]);

        $updateResponse->assertRedirect(route('settings.templates.index'));

        $updatedTemplate = DB::table('print_form_templates')->where('id', $templateId)->first();
        $settings = json_decode($updatedTemplate->settings, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            'order.number' => 'order.order_number',
            'customer.name' => 'customer.name',
            'customer.bank_name' => 'customer.bank_name',
            'cargo_sender.address' => 'cargo_sender.address',
        ], $settings['variable_mapping']);

        $downloadResponse = $this->actingAs($admin)->get(route('settings.templates.generate-order-draft', [
            'printFormTemplate' => $templateId,
            'order_id' => $orderId,
        ]));

        $downloadResponse->assertOk();
        $downloadResponse->assertDownload('customer-request-template-order-'.$orderId.'-draft.docx');

        $downloadedPath = $downloadResponse->baseResponse->getFile()->getPathname();

        $this->assertFileExists($downloadedPath);
    }

    public function test_admin_can_save_lead_variable_mapping_and_download_draft_docx(): void
    {
        Storage::fake('local');

        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId, 'name' => 'Менеджер КП']);
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент КП',
            'bank_name' => 'АО Банк Лид',
            'ogrn' => '1234567890123',
            'signer_name_nominative' => 'Иванов Иван Иванович',
            'signer_authority_basis' => 'Устав',
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
            'is_default' => true,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/2/lead-offer-template-v1.docx',
            'original_filename' => 'lead-offer-template.docx',
            'settings' => json_encode([
                'variables' => ['lead.number', 'counterparty.name', 'route.loading_addresses', 'cargo.summary'],
                'variable_mapping' => [],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/2/lead-offer-template-v1.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${lead.number}</w:t></w:r></w:p><w:p><w:r><w:t>${counterparty.name}</w:t></w:r></w:p><w:p><w:r><w:t>${route.loading_addresses}</w:t></w:r></w:p><w:p><w:r><w:t>${cargo.summary}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $leadId = DB::table('leads')->insertGetId([
            'number' => 'LD-260404-001',
            'status' => 'new',
            'counterparty_id' => $contractorId,
            'responsible_id' => $admin->id,
            'title' => 'Коммерческое на перевозку',
            'loading_location' => 'Самара',
            'unloading_location' => 'Казань',
            'planned_shipping_date' => '2026-04-10',
            'target_price' => 125000,
            'target_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_route_points')->insert([
            'lead_id' => $leadId,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Самара, Заводская 1',
            'normalized_data' => json_encode(['city' => 'Самара'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_cargo_items')->insert([
            'lead_id' => $leadId,
            'name' => 'Оборудование',
            'weight_kg' => 1200,
            'volume_m3' => 8.4,
            'package_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updateResponse = $this->actingAs($admin)->patch(route('settings.templates.update', $templateId), [
            'code' => 'lead_offer_template',
            'name' => 'Коммерческое предложение',
            'entity_type' => 'lead',
            'document_type' => 'offer',
            'document_group' => 'commercial',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => true,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'variable_mappings' => [
                ['placeholder' => 'lead.number', 'source_path' => 'lead.number'],
                ['placeholder' => 'counterparty.name', 'source_path' => 'counterparty.name'],
                ['placeholder' => 'route.loading_addresses', 'source_path' => 'route.loading_addresses'],
                ['placeholder' => 'cargo.summary', 'source_path' => 'cargo.summary'],
            ],
        ]);

        $updateResponse->assertRedirect(route('settings.templates.index'));

        $downloadResponse = $this->actingAs($admin)->get(route('settings.templates.generate-lead-draft', [
            'printFormTemplate' => $templateId,
            'lead_id' => $leadId,
        ]));

        $downloadResponse->assertOk();
        $downloadResponse->assertDownload('lead-offer-template-lead-'.$leadId.'-draft.docx');
        $this->assertFileExists($downloadResponse->baseResponse->getFile()->getPathname());
    }

    private function createRole(string $name, string $displayName): int
    {
        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => $displayName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeDocxUpload(string $name, array $entries): UploadedFile
    {
        $directory = storage_path('framework/testing/disks/local');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.uniqid('docx-', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }

        $zip->close();

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
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
}
