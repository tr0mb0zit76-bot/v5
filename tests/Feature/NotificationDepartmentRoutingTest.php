<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use App\Models\Department;
use App\Models\User;
use App\Notifications\CabinetInAppNotification;
use App\Notifications\Channels\NtfyChannel;
use App\Services\CabinetNotifier;
use App\Services\Contractor\ContractorLimitApprovalService;
use App\Support\UserDepartmentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationDepartmentRoutingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function contractor_limit_approval_notifies_only_department_supervisor(): void
    {
        $departmentA = Department::query()->where('sort_order', 1)->firstOrFail();
        $departmentB = Department::query()->where('sort_order', 2)->firstOrFail();

        $supervisorA = User::factory()->create();
        UserDepartmentSync::sync($supervisorA, (int) $departmentA->id, [(int) $departmentA->id]);

        $supervisorB = User::factory()->create();
        UserDepartmentSync::sync($supervisorB, (int) $departmentB->id, [(int) $departmentB->id]);

        $manager = User::factory()->create();
        UserDepartmentSync::sync($manager, (int) $departmentA->id, []);

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Тест',
            'inn' => '7707083893',
            'debt_limit' => 0,
            'is_verified' => false,
        ]);

        $assessment = ContractorRiskAssessment::query()->create([
            'contractor_id' => $contractor->id,
            'model_version' => 'v2',
            'status' => ContractorRiskAssessment::STATUS_PENDING_APPROVAL,
            'submission_reason' => ContractorLimitApprovalService::REASON_LIMIT_ZERO,
            'submitted_by' => $manager->id,
            'submitted_at' => now(),
        ]);

        app(CabinetNotifier::class)->notifyContractorLimitApprovalRequested(
            $contractor,
            $assessment,
            $manager,
        );

        $this->assertSame(1, $supervisorA->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $supervisorB->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function approval_notification_includes_ntfy_channel_when_enabled(): void
    {
        config([
            'notifications.ntfy_enabled' => true,
            'notifications.approval_kinds' => ['contractor_limit_approval'],
        ]);

        $user = User::factory()->create();
        $notification = new CabinetInAppNotification(
            'contractor_limit_approval',
            'Согласование',
            'Текст',
            '/contractors/1',
            [],
        );

        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains(NtfyChannel::class, $channels);
    }

    #[Test]
    public function ntfy_channel_posts_to_configured_topic(): void
    {
        config([
            'notifications.ntfy_enabled' => true,
            'ntfy.base_url' => 'https://ntfy.test',
        ]);

        Http::fake();

        $user = User::factory()->create([
            'ntfy_topic' => 'crm-u-test-topic',
        ]);

        $notification = new CabinetInAppNotification(
            'contractor_limit_approval',
            'Согласование',
            'Текст',
            '/contractors/1',
            [],
        );

        app(NtfyChannel::class)->send($user, $notification);

        Http::assertSentCount(1);
    }

    #[Test]
    public function task_notification_does_not_use_ntfy_channel(): void
    {
        config([
            'notifications.ntfy_enabled' => true,
            'notifications.approval_kinds' => ['contractor_limit_approval'],
        ]);

        $user = User::factory()->create([
            'ntfy_topic' => 'crm-u-test-topic',
        ]);

        $notification = new CabinetInAppNotification(
            'task_assigned',
            'Задача',
            'Текст',
            '/tasks',
            [],
        );

        $channels = $notification->via($user);

        $this->assertSame(['database'], $channels);
    }
}
