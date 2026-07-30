<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Support\OptimisticConcurrency;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OptimisticConcurrencyTest extends TestCase
{
    public function test_matching_timestamp_passes(): void
    {
        $task = new Task;
        $task->updated_at = now()->startOfSecond();

        OptimisticConcurrency::assertUnchanged($task, $task->updated_at->toIso8601String());

        $this->assertTrue(true);
    }

    public function test_mismatch_throws_validation_for_inertia_like_request(): void
    {
        $this->expectException(ValidationException::class);

        $task = new Task;
        $task->updated_at = now()->startOfSecond();

        $this->withoutExceptionHandling();
        request()->headers->set('X-Inertia', 'true');
        request()->headers->set('Accept', 'text/html, application/xhtml+xml');

        OptimisticConcurrency::assertUnchanged($task, now()->subMinute()->toIso8601String());
    }

    public function test_mismatch_throws_json_409_for_api_request(): void
    {
        $task = new Task;
        $task->updated_at = now()->startOfSecond();

        request()->headers->set('Accept', 'application/json');

        try {
            OptimisticConcurrency::assertUnchanged($task, now()->subMinute()->toIso8601String());
            $this->fail('Expected HttpResponseException');
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
            $this->assertSame(409, $response->getStatusCode());
            $payload = $response->getData(true);
            $this->assertSame('concurrency_conflict', $payload['code'] ?? null);
        }
    }

    public function test_empty_expected_skips_check(): void
    {
        $task = new Task;
        $task->updated_at = now();

        OptimisticConcurrency::assertUnchanged($task, null);
        OptimisticConcurrency::assertUnchanged($task, '');

        $this->assertTrue(true);
    }
}
