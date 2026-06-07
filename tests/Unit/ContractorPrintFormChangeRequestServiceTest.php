<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ContractorPrintFormChangeRequest;
use App\Models\PrintFormBasicTerm;
use App\Models\User;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Services\PrintForm\PrintFormBasicTermsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ContractorPrintFormChangeRequestServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'contractor_print_form_change_requests',
            'print_form_basic_terms',
            'contractors',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('print_form_basic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('party', 16);
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('contractor_print_form_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('party', 16);
            $table->string('change_type', 32)->default('basic_terms');
            $table->string('status', 32)->default('pending_approval');
            $table->json('payload')->nullable();
            $table->text('manager_notes')->nullable();
            $table->text('yurik_summary')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->timestamps();
        });
    }

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
        $user = User::query()->create([
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
        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-'.uniqid('', true).'@test.local',
        ]);

        $mock = Mockery::mock($user)->makePartial();
        $mock->shouldReceive('isAdmin')->andReturn(false);
        $mock->shouldReceive('isSupervisor')->andReturn(false);

        return $mock;
    }
}
