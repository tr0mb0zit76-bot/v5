<?php

namespace Tests\Unit;

use App\Enums\SalesScriptNodeKind;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTransition;
use App\Services\SalesScripts\SalesScriptBodyPlaceholderService;
use App\Services\SalesScripts\SalesScriptConversationGuidanceService;
use App\Services\SalesScripts\SalesScriptPlayPresentationService;
use App\Services\SalesScripts\SalesScriptPlaySessionService;
use Tests\TestCase;

class SalesScriptPlayPresentationServiceTest extends TestCase
{
    public function test_peeks_customer_choices_from_next_branch_node(): void
    {
        $say = new SalesScriptNode([
            'client_key' => 'intro',
            'kind' => SalesScriptNodeKind::Say,
            'body' => 'Спасибо, что нашли время.',
            'hint' => 'Задайте рамку встречи.',
        ]);

        $branch = new SalesScriptNode([
            'id' => 2,
            'client_key' => 'branch',
            'kind' => SalesScriptNodeKind::Branch,
            'body' => 'Служебная развилка',
            'hint' => null,
        ]);

        $reaction = new SalesScriptReactionClass([
            'id' => 5,
            'key' => 'positive_signal',
            'label' => 'Клиент позитивен',
        ]);

        $linear = new SalesScriptTransition([
            'id' => 10,
            'sales_script_reaction_class_id' => null,
        ]);
        $linear->setRelation('toNode', $branch);

        $choice = new SalesScriptTransition([
            'id' => 11,
            'sales_script_reaction_class_id' => 5,
            'customer_label' => 'Да, задавайте вопросы',
        ]);
        $choice->setRelation('reactionClass', $reaction);

        $playSession = $this->createMock(SalesScriptPlaySessionService::class);
        $playSession->method('outgoingTransitions')
            ->willReturnCallback(function (SalesScriptNode $node) use ($say, $linear, $branch, $choice): array {
                if ($node->client_key === $say->client_key) {
                    return [$linear];
                }

                if ($node->id === $branch->id) {
                    return [$choice];
                }

                return [];
            });

        $service = new SalesScriptPlayPresentationService(
            $playSession,
            new SalesScriptBodyPlaceholderService,
            new SalesScriptConversationGuidanceService,
        );
        $presentation = $service->build($say);

        $this->assertSame('Спасибо, что нашли время.', $presentation['operator_line']);
        $this->assertCount(1, $presentation['choices']);
        $this->assertSame('Да, задавайте вопросы', $presentation['choices'][0]['label']);
        $this->assertTrue($presentation['choices'][0]['compound']);
    }

    public function test_branch_node_shows_customer_choices_without_operator_line(): void
    {
        $branch = new SalesScriptNode([
            'client_key' => 'gatekeeper',
            'kind' => SalesScriptNodeKind::Branch,
            'body' => 'Инструкция редактору',
            'hint' => 'СПИН-S',
        ]);

        $reaction = new SalesScriptReactionClass([
            'id' => 3,
            'label' => 'Откладывает решение',
        ]);

        $transition = new SalesScriptTransition([
            'id' => 20,
            'sales_script_reaction_class_id' => 3,
            'customer_label' => 'Напишите на почту',
        ]);
        $transition->setRelation('reactionClass', $reaction);

        $playSession = $this->createMock(SalesScriptPlaySessionService::class);
        $playSession->method('outgoingTransitions')->willReturn([$transition]);

        $service = new SalesScriptPlayPresentationService(
            $playSession,
            new SalesScriptBodyPlaceholderService,
            new SalesScriptConversationGuidanceService,
        );
        $presentation = $service->build($branch);

        $this->assertNull($presentation['operator_line']);
        $this->assertTrue($presentation['is_branch_only']);
        $this->assertSame('Напишите на почту', $presentation['choices'][0]['label']);
        $this->assertTrue($presentation['choices'][0]['has_customer_phrase']);
    }

    public function test_reaction_without_customer_phrase_does_not_use_taxonomy_as_quote(): void
    {
        $say = new SalesScriptNode([
            'client_key' => 'intro',
            'kind' => SalesScriptNodeKind::Say,
            'body' => 'Добрый день!',
            'hint' => null,
        ]);

        $reaction = new SalesScriptReactionClass([
            'id' => 7,
            'label' => 'Сравнивает с другим перевозчиком',
        ]);

        $transition = new SalesScriptTransition([
            'id' => 30,
            'sales_script_reaction_class_id' => 7,
            'customer_label' => null,
        ]);
        $transition->setRelation('reactionClass', $reaction);

        $playSession = $this->createMock(SalesScriptPlaySessionService::class);
        $playSession->method('outgoingTransitions')->willReturn([$transition]);

        $service = new SalesScriptPlayPresentationService(
            $playSession,
            new SalesScriptBodyPlaceholderService,
            new SalesScriptConversationGuidanceService,
        );
        $presentation = $service->build($say);

        $this->assertFalse($presentation['choices'][0]['has_customer_phrase']);
        $this->assertSame('Фраза клиента не задана в редакторе', $presentation['choices'][0]['label']);
        $this->assertStringContainsString('Сравнивает с другим перевозчиком', (string) $presentation['choices'][0]['subtitle']);
    }

    public function test_choice_contains_effect_and_next_move_preview(): void
    {
        $current = new SalesScriptNode([
            'client_key' => 'price',
            'kind' => SalesScriptNodeKind::Branch,
            'body' => 'Клиент спрашивает цену.',
        ]);
        $next = new SalesScriptNode([
            'client_key' => 'reframe',
            'kind' => SalesScriptNodeKind::Say,
            'body' => 'Скажите: «Давайте сравним одинаковые условия и риски срыва».',
            'tags' => ['цена'],
        ]);
        $reaction = new SalesScriptReactionClass([
            'id' => 9,
            'key' => 'price_objection',
            'label' => 'Возражение по цене',
        ]);
        $transition = new SalesScriptTransition([
            'id' => 40,
            'sales_script_reaction_class_id' => 9,
            'customer_label' => 'У вас слишком дорого',
        ]);
        $transition->setRelation('reactionClass', $reaction);
        $transition->setRelation('toNode', $next);

        $playSession = $this->createMock(SalesScriptPlaySessionService::class);
        $playSession->method('outgoingTransitions')->willReturn([$transition]);

        $presentation = (new SalesScriptPlayPresentationService(
            $playSession,
            new SalesScriptBodyPlaceholderService,
            new SalesScriptConversationGuidanceService,
        ))->build($current);

        $choice = $presentation['choices'][0];
        $this->assertSame('risk', $choice['effect']);
        $this->assertSame(-1, $choice['momentum_delta']);
        $this->assertSame('«Давайте сравним одинаковые условия и риски срыва»', $choice['next_move_preview']);
        $this->assertSame('Переговоры о цене', $choice['next_phase']);
    }
}
