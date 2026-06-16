<?php

namespace Tests\Unit;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Services\BusinessProcessPlaybookSeederService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessProcessPlaybookSeederServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['business_process_stages', 'business_processes']);

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
            $table->timestamps();
        });
    }

    public function test_seeder_fills_default_transport_intake_playbook(): void
    {
        $process = BusinessProcess::query()->create([
            'name' => 'Получение деталей по перевозке',
            'slug' => 'transport-intake',
            'is_active' => true,
        ]);

        BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Получение деталей по перевозке',
            'sequence' => 10,
        ]);

        $result = app(BusinessProcessPlaybookSeederService::class)->seed(true);

        $this->assertSame(1, $result['processes']);
        $this->assertSame(1, $result['stages']);

        $stage = BusinessProcessStage::query()->firstOrFail();
        $this->assertNotNull($stage->stage_goal);
        $this->assertStringContainsString('Действия менеджера', (string) $stage->description);
        $this->assertNotNull($stage->success_criteria);
    }
}
