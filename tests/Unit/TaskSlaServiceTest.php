<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Services\TaskSlaService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskSlaServiceTest extends TestCase
{
    public static function slaDeadlineProvider(): array
    {
        return [
            'explicit over due' => ['2026-04-10T12:00:00Z', '2026-04-11T15:00:00Z', '2026-04-11T15:00:00Z'],
            'falls back to due' => ['2026-04-10T12:00:00Z', null, '2026-04-10T12:00:00Z'],
            'empty due explicit null' => [null, null, null],
        ];
    }

    #[DataProvider('slaDeadlineProvider')]
    public function test_resolve_sla_deadline(?string $dueAt, ?string $explicit, ?string $expectedIso): void
    {
        $service = new TaskSlaService;
        $resolved = $service->resolveSlaDeadline($dueAt, $explicit);

        if ($expectedIso === null) {
            $this->assertNull($resolved);

            return;
        }

        $this->assertNotNull($resolved);
        $this->assertSame(
            Carbon::parse($expectedIso)->toIso8601String(),
            $resolved->toIso8601String()
        );
    }

    public function test_is_sla_breached_for_open_task_with_past_sla(): void
    {
        $service = new TaskSlaService;
        $task = new Task([
            'status' => 'new',
            'sla_deadline_at' => now()->subHour(),
        ]);

        $this->assertTrue($service->isSlaBreached($task));
    }

    public function test_is_sla_breached_false_when_done(): void
    {
        $service = new TaskSlaService;
        $task = new Task([
            'status' => 'done',
            'sla_deadline_at' => now()->subHour(),
        ]);

        $this->assertFalse($service->isSlaBreached($task));
    }
}
