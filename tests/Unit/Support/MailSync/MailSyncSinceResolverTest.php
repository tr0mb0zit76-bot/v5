<?php

namespace Tests\Unit\Support\MailSync;

use App\Support\MailSync\MailSyncSinceResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailSyncSinceResolverTest extends TestCase
{
    #[Test]
    public function it_uses_explicit_days_override(): void
    {
        CarbonImmutable::setTestNow('2026-06-10 12:00:00');

        $since = MailSyncSinceResolver::resolve(
            lastSyncAt: CarbonImmutable::parse('2026-06-09 12:00:00'),
            daysOverride: 7,
        );

        $this->assertSame('2026-06-03 12:00:00', $since->toDateTimeString());

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function it_uses_incremental_window_after_first_sync(): void
    {
        CarbonImmutable::setTestNow('2026-06-10 12:00:00');

        $since = MailSyncSinceResolver::resolve(
            lastSyncAt: CarbonImmutable::parse('2026-06-10 10:00:00'),
            daysOverride: null,
            initialDays: 30,
            overlapHours: 24,
        );

        $this->assertSame('2026-06-09 10:00:00', $since->toDateTimeString());

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function it_uses_initial_days_when_never_synced(): void
    {
        CarbonImmutable::setTestNow('2026-06-10 12:00:00');

        $since = MailSyncSinceResolver::resolve(
            lastSyncAt: null,
            daysOverride: null,
            initialDays: 30,
            overlapHours: 24,
        );

        $this->assertSame('2026-05-11 12:00:00', $since->toDateTimeString());

        CarbonImmutable::setTestNow();
    }
}
