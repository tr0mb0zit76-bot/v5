<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SalesScript;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptVersion;
use App\Services\SalesScripts\TrainerRubricService;
use Tests\TestCase;

class TrainerRubricServiceTest extends TestCase
{
    public function test_resolves_price_and_conflict_rubrics_from_script_context(): void
    {
        $service = new TrainerRubricService;

        $priceSession = $this->sessionForScript('Тренажёр: цена и конкурент', ['тренажёр', 'цена', 'конкурент']);
        $conflictSession = $this->sessionForScript('Проблемный рейс / удержание клиента', ['претензия', 'удержание']);

        $this->assertSame('price', $service->forSession($priceSession)['key']);
        $this->assertSame('conflict', $service->forSession($conflictSession)['key']);
    }

    /**
     * @param  list<string>  $tags
     */
    private function sessionForScript(string $title, array $tags): SalesScriptPlaySession
    {
        $script = SalesScript::query()->create([
            'title' => $title,
            'description' => null,
            'channel' => 'phone',
            'tags' => $tags,
        ]);

        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'published_at' => now(),
            'is_active' => true,
            'entry_node_key' => 'intro',
        ]);

        return SalesScriptPlaySession::query()->create([
            'sales_script_version_id' => $version->id,
            'is_trainer' => true,
            'started_at' => now(),
        ]);
    }
}
