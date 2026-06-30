<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptTrainerMessage;
use App\Models\SalesScriptVersion;
use App\Services\SalesScripts\TrainerDialogHintService;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TrainerDialogHintServiceTest extends TestCase
{
    public function test_extracts_meaningful_terms(): void
    {
        $service = new TrainerDialogHintService;
        $terms = $service->extractTermsForTests(
            'Нам нужна ставка по маршруту Москва — Казань на завтра. Цена критична, сроки сжаты.',
        );

        $this->assertContains('нужна', $terms);
        $this->assertContains('ставка', $terms);
        $this->assertContains('маршруту', $terms);
        $this->assertContains('москва', $terms);
        $this->assertContains('казань', $terms);
        $this->assertContains('завтра', $terms);
        $this->assertContains('критична', $terms);
        $this->assertContains('сроки', $terms);
        $this->assertContains('сжаты', $terms);
    }

    public function test_contextual_hints_stay_near_current_graph_step(): void
    {
        $this->seed(SalesScriptsDemoSeeder::class);

        $service = app(TrainerDialogHintService::class);
        $scriptId = SalesScript::query()->where('title', 'Холодный звонок')->value('id');
        $version = SalesScriptVersion::query()
            ->where('sales_script_id', $scriptId)
            ->where('is_active', true)
            ->firstOrFail();
        $current = SalesScriptNode::query()
            ->where('sales_script_version_id', $version->id)
            ->where('client_key', 'gatekeeper_branch')
            ->firstOrFail();

        $hints = $service->contextualNodeHints(
            (int) $version->id,
            (int) $current->id,
            new Collection([
                new SalesScriptTrainerMessage([
                    'content' => 'Перевозчик нас устраивает, но если есть конкретное предложение — напишите на почту.',
                ]),
            ]),
            6,
        );

        $keys = array_column($hints, 'client_key');

        $this->assertContains('clarify_contact', $keys);
        $this->assertNotContains('proposal_frame', $keys);
        $this->assertNotContains('procedure_probe', $keys);
    }
}
