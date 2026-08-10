<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueHealthCommandTest extends TestCase
{
    #[Test]
    public function queue_health_reports_sync_mode(): void
    {
        Config::set('queue.default', 'sync');

        $this->artisan('queue:health')
            ->expectsOutputToContain('QUEUE_CONNECTION: sync')
            ->assertSuccessful();
    }
}
