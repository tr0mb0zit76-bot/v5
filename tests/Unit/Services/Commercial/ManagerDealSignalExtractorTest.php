<?php

namespace Tests\Unit\Services\Commercial;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadProcessStageLog;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\ManagerDealSignalExtractor;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagerDealSignalExtractorTest extends TestCase
{
    #[Test]
    public function it_marks_long_quiet_qualification_stage_as_idle_dwell(): void
    {
        if (! Schema::hasTable('lead_process_stage_logs') || ! Schema::hasTable('business_process_stages')) {
            $this->markTestSkipped('lead process stage tables are missing.');
        }

        $process = BusinessProcess::query()->create([
            'name' => 'OI Funnel',
            'slug' => 'oi-funnel-idle-'.uniqid(),
            'is_active' => true,
        ]);

        $qualification = BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Квалификация',
            'sequence' => 10,
            'duration_days' => 2,
        ]);

        $lead = Lead::factory()->create([
            'status' => 'lost',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $qualification->id,
        ]);

        LeadProcessStageLog::query()->create([
            'lead_id' => $lead->id,
            'business_process_stage_id' => $qualification->id,
            'entered_at' => now()->subDays(5),
            'exited_at' => now()->subDays(1),
        ]);

        $signal = (new ManagerDealSignalExtractor(new ActivityLedgerService))->extract($lead->fresh());

        $this->assertTrue($signal['has_idle_qualification_dwell']);
        $this->assertSame('idle_dwell', $signal['stage_patterns'][0]['pattern'] ?? null);
        $this->assertTrue($signal['stage_patterns'][0]['is_qualification_stage'] ?? false);
    }

    #[Test]
    public function it_marks_busy_qualification_stage_as_active_work(): void
    {
        if (! Schema::hasTable('lead_process_stage_logs') || ! Schema::hasTable('lead_activities')) {
            $this->markTestSkipped('lead activity tables are missing.');
        }

        $process = BusinessProcess::query()->create([
            'name' => 'OI Funnel Active',
            'slug' => 'oi-funnel-active-'.uniqid(),
            'is_active' => true,
        ]);

        $qualification = BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Квалификация',
            'sequence' => 10,
            'duration_days' => 2,
        ]);

        $lead = Lead::factory()->create([
            'status' => 'won',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $qualification->id,
        ]);

        $enteredAt = now()->subDays(3);
        $exitedAt = now()->subDay();

        LeadProcessStageLog::query()->create([
            'lead_id' => $lead->id,
            'business_process_stage_id' => $qualification->id,
            'entered_at' => $enteredAt,
            'exited_at' => $exitedAt,
        ]);

        foreach (['call', 'note', 'email'] as $type) {
            LeadActivity::query()->create([
                'lead_id' => $lead->id,
                'type' => $type,
                'subject' => 'activity '.$type,
                'content' => 'activity '.$type,
            ]);
        }

        LeadActivity::query()
            ->where('lead_id', $lead->id)
            ->update([
                'created_at' => $enteredAt->copy()->addHour(),
                'updated_at' => $enteredAt->copy()->addHour(),
            ]);

        $signal = (new ManagerDealSignalExtractor(new ActivityLedgerService))->extract($lead->fresh());

        $this->assertFalse($signal['has_idle_qualification_dwell']);
        $this->assertSame('active_work', $signal['stage_patterns'][0]['pattern'] ?? null);
    }
}
