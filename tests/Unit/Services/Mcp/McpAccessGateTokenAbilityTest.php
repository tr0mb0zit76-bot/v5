<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Mcp;

use App\Models\User;
use App\Services\Mcp\McpAccessGate;
use App\Support\McpTokenAbilities;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpAccessGateTokenAbilityTest extends TestCase
{
    public function test_read_only_token_allows_read_ability_check(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test', [McpTokenAbilities::READ]);
        $user->withAccessToken($token->accessToken);

        app(McpAccessGate::class)->requireTokenAbility($user, McpTokenAbilities::READ);

        $this->assertTrue(true);
    }

    public function test_read_only_token_rejects_write_ability(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test', [McpTokenAbilities::READ]);
        $user->withAccessToken($token->accessToken);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('mcp:write');

        app(McpAccessGate::class)->requireTokenAbility($user, McpTokenAbilities::WRITE);
    }

    public function test_full_access_token_allows_write(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test', [McpTokenAbilities::FULL]);
        $user->withAccessToken($token->accessToken);

        app(McpAccessGate::class)->requireTokenAbility($user, McpTokenAbilities::WRITE);

        $this->assertTrue(true);
    }

    public function test_no_token_skips_ability_gate_for_dev_stdio(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        app(McpAccessGate::class)->requireTokenAbility($user, McpTokenAbilities::WRITE);

        $this->assertTrue(true);
    }
}
