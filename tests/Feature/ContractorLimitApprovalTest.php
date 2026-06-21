<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use App\Models\User;
use App\Services\Contractor\ContractorLimitApprovalService;
use App\Support\UserDepartmentSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorLimitApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'notifications',
            'department_user',
            'departments',
            'contractor_risk_assessments',
            'contractor_risk_snapshots',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('customer');
            $table->string('name');
            $table->string('inn', 20)->nullable();
            $table->decimal('debt_limit', 12, 2)->nullable();
            $table->string('debt_limit_currency', 3)->default('RUB');
            $table->boolean('stop_on_limit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_own_company')->default(false);
            $table->timestamps();
        });

        Schema::create('contractor_risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('inn', 12);
            $table->string('model_version', 16);
            $table->json('normalized_data');
            $table->json('scoring_result');
            $table->boolean('checko_from_cache')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_risk_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_version', 16);
            $table->string('status', 32)->default('draft');
            $table->string('outcome', 32)->nullable();
            $table->unsignedTinyInteger('draft_score')->nullable();
            $table->string('draft_grade', 2)->nullable();
            $table->string('draft_tier', 16)->nullable();
            $table->unsignedInteger('draft_recommended_debt_limit_rub')->nullable();
            $table->unsignedTinyInteger('draft_recommended_postpayment_days')->nullable();
            $table->decimal('applied_debt_limit_rub', 14, 2)->nullable();
            $table->unsignedTinyInteger('applied_postpayment_days')->nullable();
            $table->string('applied_schedule_target', 16)->nullable();
            $table->json('edit_delta')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_reason', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_approvals')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'department_id']);
        });
    }

    #[Test]
    public function manager_can_submit_new_contractor_for_limit_approval_and_notify_supervisor(): void
    {
        $supervisorRoleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'display_name' => 'Руководитель',
            'visibility_areas' => json_encode(['contractors'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'visibility_areas' => json_encode(['contractors'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supervisor = User::factory()->create(['role_id' => $supervisorRoleId]);
        $manager = User::factory()->create(['role_id' => $managerRoleId]);

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Подразделение 1',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        UserDepartmentSync::sync($supervisor, (int) $departmentId, [(int) $departmentId]);
        UserDepartmentSync::sync($manager, (int) $departmentId, []);

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Новый клиент',
            'inn' => '7707083893',
            'debt_limit' => 0,
            'is_verified' => false,
        ]);

        $response = $this->actingAs($manager)->postJson(
            route('contractors.limit-approval.request', $contractor),
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('can_request_limit_approval', false);

        $this->assertDatabaseHas('contractor_risk_assessments', [
            'contractor_id' => $contractor->id,
            'status' => ContractorRiskAssessment::STATUS_PENDING_APPROVAL,
            'submission_reason' => ContractorLimitApprovalService::REASON_LIMIT_ZERO,
            'submitted_by' => $manager->id,
        ]);

        $this->assertSame(1, $supervisor->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function resolve_reason_detects_unverified_new_card(): void
    {
        $contractor = Contractor::query()->make([
            'type' => 'customer',
            'name' => 'Test',
            'debt_limit' => 500_000,
            'is_verified' => false,
            'verified_at' => null,
            'is_own_company' => false,
        ]);
        $contractor->id = 1;

        $service = app(ContractorLimitApprovalService::class);

        $this->assertSame(
            ContractorLimitApprovalService::REASON_NEW_CARD,
            $service->resolveReason($contractor, 0),
        );
    }
}
