<?php

namespace Tests\Unit;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\LeadProcessStageLog;
use App\Services\ActivityLedgerService;
use App\Services\BusinessProcessAnalyticsService;
use App\Services\LeadBusinessProcessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessProcessAnalyticsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'lead_process_stage_logs',
            'leads',
            'business_process_stages',
            'business_processes',
        ]);

        Schema::create('business_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('business_process_stages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_process_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('stage_goal', 500)->nullable();
            $table->text('success_criteria')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->unsignedSmallInteger('duration_days')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->string('terminal_outcome', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new');
            $table->string('title');
            $table->unsignedBigInteger('business_process_id')->nullable();
            $table->unsignedBigInteger('business_process_stage_id')->nullable();
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('stage_due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_process_stage_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('business_process_stage_id');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_health_overview_flags_missing_playbook_and_bottleneck(): void
    {
        $process = BusinessProcess::query()->create([
            'name' => 'Тестовая воронка',
            'slug' => 'test-funnel',
            'is_active' => true,
        ]);

        $stageA = BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Квалификация',
            'sequence' => 10,
            'duration_days' => 2,
        ]);

        BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Расчёт',
            'sequence' => 20,
            'duration_days' => 3,
            'description' => 'Есть playbook',
        ]);

        $lead = Lead::query()->create([
            'number' => 'L-001',
            'title' => 'Тест',
            'status' => 'new',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $stageA->id,
            'stage_entered_at' => now()->subDays(5),
        ]);

        LeadProcessStageLog::query()->create([
            'lead_id' => $lead->id,
            'business_process_stage_id' => $stageA->id,
            'entered_at' => now()->subDays(10),
            'exited_at' => now()->subDays(4),
            'due_at' => now()->subDays(8),
        ]);

        $service = new BusinessProcessAnalyticsService(new LeadBusinessProcessService(app(ActivityLedgerService::class)));

        $health = $service->healthOverview(90);

        $this->assertCount(1, $health['processes']);
        $this->assertSame('Тестовая воронка', $health['processes'][0]['name']);
        $this->assertNotEmpty($health['recommendations']);

        $messages = array_column($health['recommendations'], 'message');
        $this->assertTrue(
            collect($messages)->contains(fn (string $message): bool => str_contains($message, 'без инструкции')),
        );
    }
}
