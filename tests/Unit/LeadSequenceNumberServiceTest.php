<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Leads\LeadSequenceNumberService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadSequenceNumberServiceTest extends TestCase
{
    public function test_next_lead_number_uses_date_prefix(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Таблица leads недоступна.');
        }

        $number = app(LeadSequenceNumberService::class)->nextLeadNumber();

        $this->assertMatchesRegularExpression('/^LD-\d{6}-\d{3}$/', $number);
    }

    public function test_next_task_number_uses_date_prefix(): void
    {
        $number = app(LeadSequenceNumberService::class)->nextTaskNumber();

        $this->assertMatchesRegularExpression('/^TSK-\d{6}-\d{3}$/', $number);
    }
}
