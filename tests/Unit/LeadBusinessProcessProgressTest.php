<?php

namespace Tests\Unit;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Services\ActivityLedgerService;
use App\Services\LeadBusinessProcessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadBusinessProcessProgressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'lead_process_stage_logs',
            'lead_activities',
            'activity_events',
            'leads',
            'business_process_stages',
            'business_processes',
        ]);

        Schema::create('business_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('business_process_stages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_process_id');
            $table->string('name');
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
            $table->timestamp('process_started_at')->nullable();
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

    public function test_terminal_refusal_stage_reports_full_progress(): void
    {
        $process = BusinessProcess::query()->create([
            'name' => 'Тест',
            'slug' => 'test-process',
            'is_active' => true,
        ]);

        foreach ([
            ['name' => 'Шаг 1', 'sequence' => 10],
            ['name' => 'Шаг 2', 'sequence' => 20],
            ['name' => 'Шаг 3', 'sequence' => 30],
            ['name' => 'Отказ', 'sequence' => 40, 'is_terminal' => true, 'terminal_outcome' => 'lost'],
            ['name' => 'Подписание', 'sequence' => 50, 'is_terminal' => true, 'terminal_outcome' => 'won'],
        ] as $stageData) {
            BusinessProcessStage::query()->create([
                'business_process_id' => $process->id,
                ...$stageData,
            ]);
        }

        $refusalStage = BusinessProcessStage::query()
            ->where('business_process_id', $process->id)
            ->where('name', 'Отказ')
            ->firstOrFail();

        $lead = Lead::query()->create([
            'number' => 'LD-TEST-1',
            'status' => 'negotiation',
            'title' => 'Тестовый лид',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $refusalStage->id,
            'process_started_at' => now(),
            'stage_entered_at' => now(),
        ]);

        $payload = $this->processService()->progressPayload($lead);

        $this->assertNotNull($payload);
        $this->assertSame(100, $payload['progress_percent']);
        $this->assertTrue(
            collect($payload['stages'])->every(fn (array $stage): bool => $stage['state'] === 'completed'),
        );
    }

    private function processService(): LeadBusinessProcessService
    {
        return new LeadBusinessProcessService($this->createMock(ActivityLedgerService::class));
    }
}
