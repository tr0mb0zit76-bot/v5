<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\AiAgentCatalog;
use Tests\TestCase;

class AiAgentCatalogTest extends TestCase
{
    public function test_options_for_user_includes_jarvis(): void
    {
        $user = User::factory()->create();

        $options = AiAgentCatalog::optionsForUser($user);

        $this->assertNotEmpty($options);
        $this->assertSame('jarvis', $options[0]['slug'] ?? null);
    }

    public function test_resolve_unknown_slug_falls_back_to_default(): void
    {
        $user = User::factory()->create();

        $persona = AiAgentCatalog::resolveForUser($user, 'unknown-agent');

        $this->assertSame('jarvis', $persona['slug']);
    }
}
