<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\ContractorOperationalStatusService;
use App\Support\ContractorWorkStatus;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class ContractorOperationalStatusServiceTest extends TestCase
{
    public function test_verification_expires_after_three_months(): void
    {
        $service = app(ContractorOperationalStatusService::class);

        $this->assertTrue($service->isVerificationExpired(Carbon::parse('2025-01-01')));
        $this->assertFalse($service->isVerificationExpired(now()->subMonths(2)));
    }

    public function test_inactivity_sets_automatic_work_pause(): void
    {
        $service = app(ContractorOperationalStatusService::class);

        $contractor = new Contractor([
            'is_active' => true,
            'work_status' => ContractorWorkStatus::ACTIVE,
            'work_pause_is_automatic' => false,
            'is_verified' => false,
        ]);

        $method = new ReflectionMethod($service, 'applyOperationalRules');
        $method->setAccessible(true);
        $method->invoke($service, $contractor, Carbon::parse('2025-01-01'));

        $this->assertSame(ContractorWorkStatus::WORK_PAUSE, $contractor->work_status);
        $this->assertTrue($contractor->work_pause_is_automatic);
    }

    public function test_work_ban_is_not_overridden_by_inactivity(): void
    {
        $service = app(ContractorOperationalStatusService::class);

        $contractor = new Contractor([
            'is_active' => true,
            'work_status' => ContractorWorkStatus::WORK_BAN,
            'work_pause_is_automatic' => false,
        ]);

        $method = new ReflectionMethod($service, 'applyOperationalRules');
        $method->setAccessible(true);
        $method->invoke($service, $contractor, null);

        $this->assertSame(ContractorWorkStatus::WORK_BAN, $contractor->work_status);
    }
}
