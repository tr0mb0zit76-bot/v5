<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IssueMcpTokenCommandTest extends TestCase
{
    public function test_issue_token_sets_default_expiration(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = User::factory()->create([
            'is_active' => true,
        ]);

        Artisan::call('mcp:issue-token', [
            'user' => (string) $user->id,
            '--days' => 90,
        ]);

        $tokenRow = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->where('name', 'mcp-cursor')
            ->first();

        $this->assertNotNull($tokenRow);
        $this->assertNotNull($tokenRow->expires_at);
        $this->assertTrue(now()->addDays(89)->lessThan($tokenRow->expires_at));
        $this->assertTrue(now()->addDays(91)->greaterThan($tokenRow->expires_at));
    }

    public function test_issue_token_defaults_to_read_only_abilities(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = User::factory()->create(['is_active' => true]);

        Artisan::call('mcp:issue-token', [
            'user' => (string) $user->id,
        ]);

        $tokenRow = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->where('name', 'mcp-cursor')
            ->first();

        $this->assertNotNull($tokenRow);
        $this->assertSame(['mcp:read'], json_decode((string) $tokenRow->abilities, true));
    }

    public function test_issue_token_write_flag_adds_write_ability(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table is unavailable.');
        }

        $user = User::factory()->create(['is_active' => true]);

        Artisan::call('mcp:issue-token', [
            'user' => (string) $user->id,
            '--write' => true,
        ]);

        $tokenRow = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->where('name', 'mcp-cursor')
            ->first();

        $this->assertNotNull($tokenRow);
        $this->assertEqualsCanonicalizing(
            ['mcp:read', 'mcp:write'],
            json_decode((string) $tokenRow->abilities, true),
        );
    }
}
