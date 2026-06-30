<?php

namespace Tests\Unit;

use App\Enums\SalesScriptNodeKind;
use App\Models\SalesScriptNode;
use App\Services\SalesScripts\TrainerScenarioGuidanceService;
use Tests\TestCase;

class TrainerScenarioGuidanceServiceTest extends TestCase
{
    public function test_skips_linear_next_step_when_building_client_reaction_hints(): void
    {
        $node = new SalesScriptNode([
            'client_key' => 'clarify_contact',
            'kind' => SalesScriptNodeKind::Ask,
            'body' => 'Спросить имя и почту.',
        ]);
        $node->id = 10;

        $hints = app(TrainerScenarioGuidanceService::class)->build($node, [
            'operator_kind' => 'ask',
            'operator_line' => 'Спросить имя и почту.',
            'coaching_hint' => null,
            'step_key' => 'clarify_contact',
            'choices' => [
                [
                    'label' => 'Далее: Зафиксировать следующий шаг',
                    'subtitle' => 'Следующий шаг сценария',
                    'sales_script_reaction_class_id' => null,
                    'has_customer_phrase' => false,
                ],
                [
                    'label' => 'Пришлите предложение на почту',
                    'subtitle' => 'Нужна информация',
                    'sales_script_reaction_class_id' => 5,
                    'has_customer_phrase' => true,
                ],
            ],
        ]);

        $clientOptions = array_values(array_filter(
            $hints,
            fn (array $hint): bool => $hint['source'] === 'graph_client_option',
        ));

        $this->assertCount(1, $clientOptions);
        $this->assertSame('Пришлите предложение на почту', $clientOptions[0]['excerpt']);
    }
}
