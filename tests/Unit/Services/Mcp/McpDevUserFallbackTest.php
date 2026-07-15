<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Mcp;

use App\Models\User;
use App\Services\Mcp\McpAccessGate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class McpDevUserFallbackTest extends TestCase
{
    public function test_dev_user_is_rejected_outside_local_and_testing(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        config([
            'app.env' => 'production',
            'mcp.dev_user_id' => $user->id,
        ]);
        Auth::forgetGuards();

        $this->expectException(AuthenticationException::class);

        app(McpAccessGate::class)->resolveUser();
    }

    public function test_mislabeled_local_production_host_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        config([
            'app.env' => 'local',
            'app.debug' => false,
            'app.url' => 'https://crm.avtoaliyans.ru',
            'mcp.dev_user_id' => $user->id,
        ]);
        Auth::forgetGuards();

        $this->expectException(AuthenticationException::class);

        app(McpAccessGate::class)->resolveUser();
    }

    public function test_dev_user_remains_available_in_testing_cli(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        config(['mcp.dev_user_id' => $user->id]);
        Auth::forgetGuards();

        $this->assertTrue(app(McpAccessGate::class)->resolveUser()->is($user));
    }
}
