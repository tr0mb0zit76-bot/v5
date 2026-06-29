<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ContractorPrintFormChangeRequest;
use App\Models\PrintFormBasicTerm;
use App\Models\User;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Services\PrintForm\PrintFormBasicTermsService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ContractorPrintFormChangeRequestServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_admin_can_directly_sync_basic_terms(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Прямое']);
        $admin = $this->adminUser();

        app(ContractorPrintFormChangeRequestService::class)->syncBasicTermsDirectly(
            $contractor,
            PrintFormBasicTerm::PARTY_CUSTOMER,
            ['Пункт 1', 'Пункт 2'],
            $admin,
        );

        $rows = app(PrintFormBasicTermsService::class)->listRows(
            PrintFormBasicTerm::PARTY_CUSTOMER,
            (int) $contractor->id,
        );

        $this->assertCount(2, $rows);
        $this->assertSame('Пункт 1', $rows[0]['body']);
        $this->assertSame('Пункт 2', $rows[1]['body']);
    }

    public function test_submit_creates_pending_change_request(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Заявка']);
        $manager = $this->managerUser();

        $change = app(ContractorPrintFormChangeRequestService::class)->submitBasicTermsChange(
            $contractor,
            PrintFormBasicTerm::PARTY_CARRIER,
            ['Особое условие перевозчика'],
            $manager,
            'Просьба согласовать',
        );

        $this->assertSame(ContractorPrintFormChangeRequest::STATUS_PENDING_APPROVAL, $change->status);
        $this->assertSame(PrintFormBasicTerm::PARTY_CARRIER, $change->party);
        $this->assertSame(['items' => ['Особое условие перевозчика']], $change->payload);
        $this->assertSame('Просьба согласовать', $change->manager_notes);
    }

    public function test_approve_applies_basic_terms_to_contractor(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Утверждение']);
        $manager = $this->managerUser();
        $admin = $this->adminUser();

        $service = app(ContractorPrintFormChangeRequestService::class);

        $change = $service->submitBasicTermsChange(
            $contractor,
            PrintFormBasicTerm::PARTY_CUSTOMER,
            ['Утверждённый пункт'],
            $manager,
        );

        $approved = $service->approve($change, $admin);

        $this->assertSame(ContractorPrintFormChangeRequest::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->reviewed_at);

        $rows = app(PrintFormBasicTermsService::class)->listRows(
            PrintFormBasicTerm::PARTY_CUSTOMER,
            (int) $contractor->id,
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Утверждённый пункт', $rows[0]['body']);
    }

    public function test_reject_marks_request_rejected(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Отказ']);
        $manager = $this->managerUser();
        $admin = $this->adminUser();

        $service = app(ContractorPrintFormChangeRequestService::class);

        $change = $service->submitBasicTermsChange(
            $contractor,
            PrintFormBasicTerm::PARTY_CUSTOMER,
            ['Спорный пункт'],
            $manager,
        );

        $rejected = $service->reject($change, $admin, 'Формулировка не согласована');

        $this->assertSame(ContractorPrintFormChangeRequest::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Формулировка не согласована', $rejected->rejection_reason);
    }

    public function test_blocks_second_pending_submission_for_same_contractor(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Дубль']);
        $manager = $this->managerUser();

        $service = app(ContractorPrintFormChangeRequestService::class);

        $service->submitBasicTermsChange(
            $contractor,
            PrintFormBasicTerm::PARTY_CUSTOMER,
            ['Первый пункт'],
            $manager,
        );

        $this->expectException(ValidationException::class);

        $service->submitBasicTermsChange(
            $contractor,
            PrintFormBasicTerm::PARTY_CARRIER,
            ['Второй пункт'],
            $manager,
        );
    }

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid('', true).'@test.local',
        ]);

        $mock = Mockery::mock($user)->makePartial();
        $mock->shouldReceive('isAdmin')->andReturn(true);
        $mock->shouldReceive('isSupervisor')->andReturn(false);

        return $mock;
    }

    private function managerUser(): User
    {
        $user = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager-'.uniqid('', true).'@test.local',
        ]);

        $mock = Mockery::mock($user)->makePartial();
        $mock->shouldReceive('isAdmin')->andReturn(false);
        $mock->shouldReceive('isSupervisor')->andReturn(false);

        return $mock;
    }
}
