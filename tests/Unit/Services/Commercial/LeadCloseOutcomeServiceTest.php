<?php

namespace Tests\Unit\Services\Commercial;

use App\Enums\LeadCloseOutcomeFlag;
use App\Models\Lead;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\LeadCloseOutcomeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadCloseOutcomeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['leads']);

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new');
            $table->string('title');
            $table->string('close_outcome_primary_flag')->nullable();
            $table->json('close_outcome_secondary_flags')->nullable();
            $table->string('lost_reason')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_apply_syncs_status_from_lost_close_outcome_flag(): void
    {
        $lead = Lead::query()->create([
            'number' => 'LD-OUT-1',
            'status' => 'won',
            'title' => 'Неверный won',
            'close_outcome_primary_flag' => null,
        ]);

        $service = new LeadCloseOutcomeService($this->createMock(ActivityLedgerService::class));

        $service->apply($lead, LeadCloseOutcomeFlag::LostOther, null, 'Клиент отказался');

        $lead->refresh();

        $this->assertSame('lost', $lead->status);
        $this->assertSame('lost_other', $lead->close_outcome_primary_flag);
        $this->assertSame('Клиент отказался', $lead->lost_reason);
    }
}
