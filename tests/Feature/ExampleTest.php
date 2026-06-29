<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get($this->showcaseUrl('/'));

        $response->assertOk();
    }

    private function showcaseUrl(string $path = '/'): string
    {
        $host = config('app.showcase_hosts')[0] ?? 'v5.local';

        return 'http://'.$host.$path;
    }
}
