<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class TemplateManagementTest extends TestCase
{
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
            ->where('orderVariableOptions', fn ($options): bool => collect($options)->pluck('value')->intersect([
                'cargo_sender.name',
                'cargo_sender.contact_phone',
                'cargo_sender.all_names',
                'customer.bank_name',
                'driver.full_name',
                'route.loading_cities',
                'customer.signer_position',
                'vehicle.brand',
                'vehicle.number',
                'vehicle.transport_type',
                'route.loading_method',
            ])->count() === 11)
            ->where('leadVariableOptions', fn ($options): bool => collect($options)->contains(
                fn ($row): bool => ($row['value'] ?? '') === 'lead.id'
            ))
            ->where('leadVariableOptions', fn ($options): bool => collect($options)->contains(
                fn ($row): bool => ($row['value'] ?? '') === 'counterparty.name'
            ))
            ->where('leadVariableOptions', fn ($options): bool => collect($options)->contains(
                fn ($row): bool => ($row['value'] ?? '') === 'cargo.summary'
            ))
            ->has('contractorOptions', 1)
        );
    }

    public function test_templates_index_exposes_effective_variable_mapping_including_order_legacy_rules(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        DB::table('print_form_templates')->insert([
            'code' => 'legacy_map_preview',
            'name' => 'Проверка отображения маппинга',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
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
            'original_filename' => 't.docx',
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/1/x.docx',
            'settings' => json_encode([
                'variables' => ['nomer_zayavki', 'custom_x'],
                'variable_mapping' => (object) [],
                'pipeline_status' => 'placeholders_ready',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('settings.templates.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Templates')
            ->has('templates', 1)
            ->where('templates.0.variable_mapping', [
                'nomer_zayavki' => 'order.order_number',
                'custom_x' => 'custom_x',
            ])
        );
    }

    public function test_templates_index_merges_docx_placeholders_with_stored_variables(): void
    {
        Storage::fake('local');

        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'carrier_dp_podpisant_preview',
            'name' => 'Заявка с перевозчиком тест',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'contractor_id' => null,
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'original_filename' => 'carrier.docx',
            'file_disk' => 'local',
            'file_path' => 'print-form-templates/99/carrier.docx',
            'settings' => json_encode([
                'variables' => ['nomer_zayavki'],
                'variable_mapping' => (object) [],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put(
            'print-form-templates/99/carrier.docx',
            file_get_contents($this->makeDocxPath([
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${dp_podpisant}</w:t></w:r></w:p><w:p><w:r><w:t>${dp_FIO_podpisant_im}</w:t></w:r></w:p></w:body></w:document>',
            ]))
        );

        $response = $this->actingAs($admin)->get(route('settings.templates.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Templates')
            ->has('templates', 1)
            ->where('templates.0.id', $templateId)
            ->where('templates.0.variable_mapping.dp_podpisant', 'carrier.signer_position')
            ->where('templates.0.variable_mapping.dp_FIO_podpisant_im', 'carrier.signer_name_nominative')
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
            'source_type' => 'external_docx',
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

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-125',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $customerId,
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
            'contractor_id' => $customerId,
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
        ], $settings['variable_mapping']);

        $downloadResponse = $this->actingAs($admin)->get(route('settings.templates.generate-order-draft', [
            'printFormTemplate' => $templateId,
            'order_id' => $orderId,
        ]));

        $downloadResponse->assertOk();
        $downloadResponse->assertDownload('customer-request-template-order-'.$orderId.'-draft.docx');

        $downloadedPath = $downloadResponse->baseResponse->getFile()->getPathname();

        $this->assertFileExists($downloadedPath);

        $previewResponse = $this->actingAs($admin)->get(route('settings.templates.generate-order-draft', [
            'printFormTemplate' => $templateId,
            'order_id' => $orderId,
            'preview' => 1,
        ]));

        $previewResponse->assertOk();
        $this->assertStringContainsString('wordprocessingml', strtolower($previewResponse->headers->get('content-type') ?? ''));
        $this->assertStringContainsString('inline', strtolower($previewResponse->headers->get('content-disposition') ?? ''));
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
            'contractor_id' => $contractorId,
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

        $previewResponse = $this->actingAs($admin)->get(route('settings.templates.generate-lead-draft', [
            'printFormTemplate' => $templateId,
            'lead_id' => $leadId,
            'preview' => 1,
        ]));

        $previewResponse->assertOk();
        $this->assertStringContainsString('wordprocessingml', strtolower($previewResponse->headers->get('content-type') ?? ''));
        $this->assertStringContainsString('inline', strtolower($previewResponse->headers->get('content-disposition') ?? ''));
    }

    public function test_admin_can_upload_signature_and_stamp_assets_and_render_them_into_docx(): void
    {
        Storage::fake('local');

        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Заказчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('settings.templates.store'), [
            'code' => 'order_with_stamp_assets',
            'name' => 'Шаблон с печатью',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'contractor_id' => $customerId,
            'is_default' => false,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'internal_signature_placeholder' => 'internal_signature_image',
            'internal_stamp_placeholder' => 'internal_stamp_image',
            'signature_image_width_mm' => 40,
            'signature_image_height_mm' => 16,
            'signature_image_offset_x_mm' => 11,
            'signature_image_offset_y_mm' => -6,
            'stamp_image_width_mm' => 28,
            'stamp_image_height_mm' => 28,
            'stamp_image_offset_x_mm' => -8,
            'stamp_image_offset_y_mm' => 14,
            'signature_image_file' => UploadedFile::fake()->image('signature.jpg', 320, 140),
            'stamp_image_file' => UploadedFile::fake()->image('stamp.jpg', 300, 300),
            'source_file' => $this->makeDocxUpload('order-with-assets.docx', [
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p><w:p><w:r><w:t>${internal_signature_image}</w:t></w:r></w:p><w:p><w:r><w:t>${internal_stamp_image}</w:t></w:r></w:p></w:body></w:document>',
            ]),
        ]);

        $response->assertRedirect(route('settings.templates.index'));

        $template = DB::table('print_form_templates')->where('code', 'order_with_stamp_assets')->first();
        $this->assertNotNull($template);

        $templateSettings = json_decode((string) $template->settings, true, 512, JSON_THROW_ON_ERROR);
        $signaturePath = data_get($templateSettings, 'image_overlays.internal_signature.path');
        $stampPath = data_get($templateSettings, 'image_overlays.internal_stamp.path');

        $this->assertNotNull($signaturePath);
        $this->assertNotNull($stampPath);
        $this->assertSame(11.0, (float) data_get($templateSettings, 'image_overlays.internal_signature.offset_x_mm'));
        $this->assertSame(-6.0, (float) data_get($templateSettings, 'image_overlays.internal_signature.offset_y_mm'));
        $this->assertSame(-8.0, (float) data_get($templateSettings, 'image_overlays.internal_stamp.offset_x_mm'));
        $this->assertSame(14.0, (float) data_get($templateSettings, 'image_overlays.internal_stamp.offset_y_mm'));
        Storage::disk('local')->assertExists((string) $signaturePath);
        Storage::disk('local')->assertExists((string) $stampPath);

        $signatureAssetResponse = $this->actingAs($admin)->get(route('settings.templates.overlay-asset', [
            'printFormTemplate' => (int) $template->id,
            'overlayKey' => 'internal_signature',
        ]));
        $signatureAssetResponse->assertOk();
        $this->assertStringStartsWith('image/', strtolower((string) $signatureAssetResponse->headers->get('content-type')));

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-IMG-001',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-04',
            'status' => 'new',
            'customer_id' => $customerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $downloadResponse = $this->actingAs($admin)->get(route('settings.templates.generate-order-draft', [
            'printFormTemplate' => (int) $template->id,
            'order_id' => $orderId,
        ]));

        $downloadResponse->assertOk();
        $downloadResponse->assertDownload('order-with-stamp-assets-order-'.$orderId.'-draft.docx');

        $downloadedPath = $downloadResponse->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($downloadedPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($documentXml);
        $this->assertStringContainsString('<w:pict>', $documentXml);
        $this->assertStringContainsString('v:imagedata', $documentXml);
        $this->assertStringContainsString('margin-left:11.00mm', $documentXml);
        $this->assertStringContainsString('margin-top:-6.00mm', $documentXml);
        $this->assertStringContainsString('margin-left:-8.00mm', $documentXml);
        $this->assertStringContainsString('margin-top:14.00mm', $documentXml);
        $this->assertStringNotContainsString('internal_signature_image', $documentXml);
        $this->assertStringNotContainsString('internal_stamp_image', $documentXml);
    }

    public function test_admin_can_patch_template_to_upload_overlay_images_without_reuploading_docx(): void
    {
        Storage::fake('local');

        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Контрагент overlay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'patch_overlay_images',
            'name' => 'Patch overlay images',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => null,
            'original_filename' => 'orig.docx',
            'settings' => json_encode([
                'variables' => ['internal_stamp_image'],
                'variable_mapping' => [],
                'image_overlays' => [
                    'internal_signature' => [
                        'placeholder' => 'internal_signature_image',
                        'width_mm' => 42,
                        'height_mm' => 18,
                        'offset_x_mm' => 0,
                        'offset_y_mm' => 0,
                        'path' => null,
                        'disk' => null,
                    ],
                    'internal_stamp' => [
                        'placeholder' => 'internal_stamp_image',
                        'width_mm' => 30,
                        'height_mm' => 30,
                        'offset_x_mm' => 0,
                        'offset_y_mm' => 0,
                        'path' => null,
                        'disk' => null,
                    ],
                ],
                'pipeline_status' => 'placeholders_ready',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $relativePath = 'print-form-templates/'.$templateId.'/source.docx';
        Storage::disk('local')->put($relativePath, file_get_contents($this->makeDocxUpload('patch-only.docx', [
            'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${internal_stamp_image}</w:t></w:r></w:p></w:body></w:document>',
        ])));

        DB::table('print_form_templates')->where('id', $templateId)->update([
            'file_path' => $relativePath,
        ]);

        $response = $this->actingAs($admin)->post(route('settings.templates.update', $templateId), [
            '_method' => 'patch',
            'code' => 'patch_overlay_images',
            'name' => 'Patch overlay images',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'external_docx',
            'contractor_id' => $contractorId,
            'is_default' => false,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'variable_mappings' => [],
            'internal_signature_placeholder' => 'internal_signature_image',
            'internal_stamp_placeholder' => 'internal_stamp_image',
            'signature_image_width_mm' => 42,
            'signature_image_height_mm' => 18,
            'signature_image_offset_x_mm' => 0,
            'signature_image_offset_y_mm' => 0,
            'stamp_image_width_mm' => 30,
            'stamp_image_height_mm' => 30,
            'stamp_image_offset_x_mm' => 0,
            'stamp_image_offset_y_mm' => 0,
            'signature_image_file' => UploadedFile::fake()->image('sig-patch.png', 120, 60),
            'stamp_image_file' => UploadedFile::fake()->image('stamp-patch.png', 80, 80),
        ]);

        $response->assertRedirect(route('settings.templates.index'));

        $row = DB::table('print_form_templates')->find($templateId);
        $this->assertNotNull($row);
        $settings = json_decode((string) $row->settings, true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull(data_get($settings, 'image_overlays.internal_signature.path'));
        $this->assertNotNull(data_get($settings, 'image_overlays.internal_stamp.path'));
        Storage::disk('local')->assertExists((string) data_get($settings, 'image_overlays.internal_signature.path'));
        Storage::disk('local')->assertExists((string) data_get($settings, 'image_overlays.internal_stamp.path'));
    }

    public function test_admin_can_open_overlay_preview_and_save_positions(): void
    {
        $this->markTestSkipped('Маршруты settings.templates.preview-order-overlay и update-overlay-positions удалены; overlay настраивается в карточке заказа.');
    }

    public function test_admin_can_save_apply_crm_overlay_offsets_false_from_string_zero(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'overlay_flag_tpl',
            'name' => 'Шаблон флага смещений',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'system',
            'is_default' => false,
            'vue_component' => 'SystemPrintFormTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'settings' => json_encode([
                'variables' => [],
                'variable_mapping' => [],
                'image_overlays' => [
                    'apply_crm_overlay_offsets' => true,
                    'internal_signature' => [
                        'placeholder' => 'internal_signature_image',
                        'width_mm' => 42,
                        'height_mm' => 18,
                        'offset_x_mm' => 5,
                        'offset_y_mm' => 0,
                        'path' => null,
                        'disk' => null,
                    ],
                    'internal_stamp' => [
                        'placeholder' => 'internal_stamp_image',
                        'width_mm' => 30,
                        'height_mm' => 30,
                        'offset_x_mm' => 0,
                        'offset_y_mm' => 0,
                        'path' => null,
                        'disk' => null,
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('settings.templates.update', $templateId), [
            'code' => 'overlay_flag_tpl',
            'name' => 'Шаблон флага смещений',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'system',
            'contractor_id' => null,
            'is_default' => false,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'internal_signature_placeholder' => 'internal_signature_image',
            'internal_stamp_placeholder' => 'internal_stamp_image',
            'signature_image_width_mm' => 42,
            'signature_image_height_mm' => 18,
            'signature_image_offset_x_mm' => 5,
            'signature_image_offset_y_mm' => 0,
            'stamp_image_width_mm' => 30,
            'stamp_image_height_mm' => 30,
            'stamp_image_offset_x_mm' => 0,
            'stamp_image_offset_y_mm' => 0,
            'apply_crm_overlay_offsets' => '0',
        ])->assertRedirect(route('settings.templates.index'));

        $settings = json_decode((string) DB::table('print_form_templates')->where('id', $templateId)->value('settings'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse((bool) data_get($settings, 'image_overlays.apply_crm_overlay_offsets'));
    }

    public function test_admin_can_upload_new_docx_when_variable_mapping_is_legacy_row_list(): void
    {
        Storage::fake('local');

        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldDocx = $this->makeDocxPath([
            'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p></w:body></w:document>',
        ]);
        $storedOld = 'print-form-templates/99/legacy-v1.docx';
        Storage::disk('local')->put($storedOld, file_get_contents($oldDocx));

        $templateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'legacy_mapping_tpl',
            'name' => 'Шаблон со старым форматом сопоставлений',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'is_default' => false,
            'vue_component' => 'ExternalDocxTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_disk' => 'local',
            'file_path' => $storedOld,
            'original_filename' => 'old.docx',
            'settings' => json_encode([
                'variables' => ['order.number'],
                'variable_mapping' => [
                    ['placeholder' => 'order.number', 'source_path' => 'order.order_number'],
                ],
                'pipeline_status' => 'placeholders_ready',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('settings.templates.update', $templateId), [
            'code' => 'legacy_mapping_tpl',
            'name' => 'Шаблон со старым форматом сопоставлений',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'external_docx',
            'contractor_id' => $contractorId,
            'is_default' => false,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'internal_signature_placeholder' => 'internal_signature_image',
            'internal_stamp_placeholder' => 'internal_stamp_image',
            'signature_image_width_mm' => 42,
            'signature_image_height_mm' => 18,
            'signature_image_offset_x_mm' => 0,
            'signature_image_offset_y_mm' => 0,
            'stamp_image_width_mm' => 30,
            'stamp_image_height_mm' => 30,
            'stamp_image_offset_x_mm' => 0,
            'stamp_image_offset_y_mm' => 0,
            'apply_crm_overlay_offsets' => '1',
            'variable_mappings' => [
                ['placeholder' => 'order.number', 'source_path' => 'order.order_number'],
            ],
            'source_file' => $this->makeDocxUpload('replacement.docx', [
                'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>${order.number}</w:t></w:r></w:p><w:p><w:r><w:t>${customer.name}</w:t></w:r></w:p></w:body></w:document>',
            ]),
        ])->assertRedirect(route('settings.templates.index'));

        $settings = json_decode((string) DB::table('print_form_templates')->where('id', $templateId)->value('settings'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['customer.name', 'order.number'], $settings['variables']);
        $this->assertSame(['order.number' => 'order.order_number'], $settings['variable_mapping']);
    }

    public function test_default_template_requires_own_company_when_multiple_own_companies_exist(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        DB::table('contractors')->insert([
            ['name' => 'Компания А', 'is_own_company' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Компания Б', 'is_own_company' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($admin)->post(route('settings.templates.store'), [
            'code' => 'company_a_default',
            'name' => 'Заявка компании А',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'system',
            'transport_scope' => 'domestic',
            'is_default' => true,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('own_company_id');
    }

    public function test_setting_default_for_one_own_company_does_not_clear_default_for_another(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $companyAId = DB::table('contractors')->insertGetId([
            'name' => 'Компания А',
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $companyBId = DB::table('contractors')->insertGetId([
            'name' => 'Компания Б',
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateAId = DB::table('print_form_templates')->insertGetId([
            'code' => 'default_company_a',
            'name' => 'РФ заказчик А',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'system',
            'own_company_id' => $companyAId,
            'transport_scope' => 'domestic',
            'is_default' => true,
            'vue_component' => 'SystemPrintFormTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_path' => 'print-form-templates/a.docx',
            'file_disk' => 'local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateBId = DB::table('print_form_templates')->insertGetId([
            'code' => 'default_company_b',
            'name' => 'РФ заказчик Б',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'system',
            'own_company_id' => $companyBId,
            'transport_scope' => 'domestic',
            'is_default' => false,
            'vue_component' => 'SystemPrintFormTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_path' => 'print-form-templates/b.docx',
            'file_disk' => 'local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('settings.templates.update', $templateBId), [
            'code' => 'default_company_b',
            'name' => 'РФ заказчик Б',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'customer',
            'source_type' => 'system',
            'own_company_id' => $companyBId,
            'transport_scope' => 'domestic',
            'is_default' => true,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('settings.templates.index'));

        $this->assertDatabaseHas('print_form_templates', [
            'id' => $templateAId,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('print_form_templates', [
            'id' => $templateBId,
            'is_default' => true,
        ]);
    }

    public function test_setting_default_for_carrier_internal_template_does_not_clear_customer_internal_default(): void
    {
        $adminRoleId = $this->createRole('admin', 'Администратор');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);
        $companyId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Наша компания',
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customerTemplateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'customer_request_internal',
            'name' => 'Заявка заказчику',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'system',
            'own_company_id' => $companyId,
            'transport_scope' => 'domestic',
            'is_default' => true,
            'vue_component' => 'SystemPrintFormTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_path' => 'print-form-templates/customer.docx',
            'file_disk' => 'local',
            'settings' => json_encode([
                'variables' => ['cp_basic_terms_row_text'],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierTemplateId = DB::table('print_form_templates')->insertGetId([
            'code' => 'carrier_request_internal',
            'name' => 'Заявка перевозчику',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'system',
            'own_company_id' => $companyId,
            'transport_scope' => 'domestic',
            'is_default' => false,
            'vue_component' => 'SystemPrintFormTemplate',
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
            'version' => 1,
            'file_path' => 'print-form-templates/carrier.docx',
            'file_disk' => 'local',
            'settings' => json_encode([
                'variables' => ['dp_basic_terms_row_text'],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('settings.templates.update', $carrierTemplateId), [
            'code' => 'carrier_request_internal',
            'name' => 'Заявка перевозчику',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => 'internal',
            'source_type' => 'system',
            'own_company_id' => $companyId,
            'transport_scope' => 'domestic',
            'is_default' => true,
            'requires_internal_signature' => true,
            'requires_counterparty_signature' => false,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('settings.templates.index'));

        $this->assertDatabaseHas('print_form_templates', [
            'id' => $customerTemplateId,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('print_form_templates', [
            'id' => $carrierTemplateId,
            'is_default' => true,
        ]);
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
