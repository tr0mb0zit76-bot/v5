<?php

namespace Tests\Feature;

use App\Console\Commands\PullOneCBankStatementCommand;
use Carbon\Carbon;
use Tests\TestCase;

class PullOneCBankStatementCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_lookback_covers_seven_days_before_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Europe/Samara'));

        $this->assertSame(7, PullOneCBankStatementCommand::DEFAULT_LOOKBACK_DAYS);
        $this->assertSame(
            '2026-08-10',
            now()->subDays(PullOneCBankStatementCommand::DEFAULT_LOOKBACK_DAYS)->toDateString(),
        );
        $this->assertSame('2026-08-17', now()->toDateString());
    }
}
