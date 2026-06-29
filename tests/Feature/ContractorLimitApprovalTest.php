<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use App\Models\User;
use App\Services\Contractor\ContractorLimitApprovalService;
use App\Support\UserDepartmentSync;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorLimitApprovalTest extends TestCase
{
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
