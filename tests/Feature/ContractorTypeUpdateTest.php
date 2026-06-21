<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorTypeUpdateTest extends TestCase
{
    public function test_contractor_update_persists_contractor_type(): void
    {
        $user = User::query()->first();
        if ($user === null) {
            $this->markTestSkipped('No users in database.');
        }

        $contractor = Contractor::query()->where('type', 'carrier')->first();
        if ($contractor === null) {
            $contractor = Contractor::query()->create([
                'type' => 'carrier',
                'name' => 'Test Carrier '.uniqid(),
                'is_active' => true,
            ]);
        }

        $originalType = $contractor->type;

        $payload = [
            'type' => 'contractor',
            'name' => $contractor->name,
            'is_active' => true,
            'work_status' => $contractor->work_status ?? 'active',
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => false,
            'is_own_company' => false,
            'is_non_resident' => false,
            'has_english_requisites' => false,
            'bank_accounts' => [],
            'contacts' => [],
            'interactions' => [],
            'documents' => [],
            'activity_types' => [],
        ];

        if (Schema::hasColumn('contractors', 'specializations')) {
            $payload['specializations'] = [];
        }

        if (Schema::hasColumn('contractors', 'transport_requirements')) {
            $payload['transport_requirements'] = [];
        }

        $response = $this->actingAs($user)->patch(route('contractors.update', $contractor), $payload);

        $response->assertRedirect(route('contractors.show', $contractor));

        $contractor->refresh();

        $this->assertSame('contractor', $contractor->type);

        $contractor->update(['type' => $originalType]);
    }

    public function test_update_contractor_succeeds_with_empty_placeholder_nested_rows(): void
    {
        $user = User::query()->first();
        if ($user === null) {
            $this->markTestSkipped('No users in database.');
        }

        if (! Schema::hasTable('contractor_contacts') || ! Schema::hasTable('contractor_documents')) {
            $this->markTestSkipped('Nested contractor tables are unavailable.');
        }

        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик '.uniqid(),
            'inn' => null,
            'is_active' => true,
            'is_own_company' => false,
            'stop_on_limit' => false,
        ]);

        $schedule = [
            'has_prepayment' => false,
            'prepayment_ratio' => 50,
            'prepayment_days' => 0,
            'prepayment_mode' => 'fttn',
            'postpayment_days' => 5,
            'postpayment_mode' => 'ottn',
        ];

        $payload = [
            'type' => 'contractor',
            'name' => 'ООО Перевозчик обновлён',
            'phone' => '+7 900 000-00-01',
            'short_description' => 'Описание после сохранения.',
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => false,
            'default_customer_payment_form' => 'vat',
            'default_customer_payment_schedule' => $schedule,
            'default_carrier_payment_form' => 'no_vat',
            'default_carrier_payment_schedule' => $schedule,
            'is_active' => true,
            'is_own_company' => false,
            'is_non_resident' => false,
            'has_english_requisites' => false,
            'bank_accounts' => [],
            'activity_types' => [],
            'contacts' => [
                [
                    'full_name' => '',
                    'position' => '',
                    'phone' => '',
                    'email' => '',
                    'is_primary' => false,
                    'notes' => '',
                ],
                [
                    'full_name' => 'Контакт для сделок',
                    'position' => '',
                    'phone' => '',
                    'email' => '',
                    'is_primary' => true,
                    'notes' => '',
                ],
            ],
            'documents' => [
                [
                    'type' => '',
                    'title' => '',
                    'number' => '',
                    'document_date' => '',
                    'status' => '',
                    'notes' => '',
                ],
                [
                    'type' => 'other',
                    'title' => 'Доверенность',
                    'number' => '',
                    'document_date' => '',
                    'status' => '',
                    'notes' => '',
                ],
            ],
            'interactions' => [
                [
                    'contacted_at' => '',
                    'channel' => 'phone',
                    'subject' => '',
                    'summary' => '',
                    'result' => '',
                ],
            ],
        ];

        if (Schema::hasColumn('contractors', 'specializations')) {
            $payload['specializations'] = [];
        }

        if (Schema::hasColumn('contractors', 'transport_requirements')) {
            $payload['transport_requirements'] = [];
        }

        $response = $this->actingAs($user)->patch(route('contractors.update', $contractor), $payload);

        $response->assertRedirect(route('contractors.show', $contractor));

        $this->assertDatabaseHas('contractors', [
            'id' => $contractor->id,
            'name' => 'ООО Перевозчик обновлён',
            'type' => 'contractor',
            'phone' => '+7 900 000-00-01',
        ]);

        $this->assertDatabaseHas('contractor_contacts', [
            'contractor_id' => $contractor->id,
            'full_name' => 'Контакт для сделок',
        ]);

        $this->assertDatabaseHas('contractor_documents', [
            'contractor_id' => $contractor->id,
            'title' => 'Доверенность',
        ]);

        $contractor->delete();
    }

    public function test_contractor_type_update_succeeds_when_work_status_is_automatic_pause(): void
    {
        $user = User::query()->first();
        if ($user === null) {
            $this->markTestSkipped('No users in database.');
        }

        if (! Schema::hasColumn('contractors', 'work_status')) {
            $this->markTestSkipped('Column contractors.work_status is unavailable.');
        }

        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Пауза перевозчик '.uniqid(),
            'is_active' => true,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'work_status' => 'work_pause',
            'work_pause_is_automatic' => true,
        ]);

        $payload = [
            'type' => 'contractor',
            'name' => $contractor->name,
            'is_active' => true,
            'work_status' => 'work_pause',
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => false,
            'is_own_company' => false,
            'is_non_resident' => false,
            'has_english_requisites' => false,
            'bank_accounts' => [],
            'contacts' => [],
            'interactions' => [],
            'documents' => [],
            'activity_types' => [],
        ];

        if (Schema::hasColumn('contractors', 'specializations')) {
            $payload['specializations'] = [];
        }

        if (Schema::hasColumn('contractors', 'transport_requirements')) {
            $payload['transport_requirements'] = [];
        }

        $response = $this->actingAs($user)->patch(route('contractors.update', $contractor), $payload);

        $response->assertRedirect(route('contractors.show', $contractor));

        $contractor->refresh();

        $this->assertSame('contractor', $contractor->type);
        $this->assertSame('work_pause', $contractor->work_status);

        $contractor->delete();
    }
}
