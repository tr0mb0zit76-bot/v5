<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\Agents\CommandBarAttachmentService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommandBarAttachmentServiceTest extends TestCase
{
    #[Test]
    public function it_detects_order_intake_intent_from_user_message(): void
    {
        $service = $this->service();
        $user = $this->userWithAreas(['orders']);

        $batch = [
            'items' => [],
            'combined_text' => 'Москва — Казань, 20 т',
            'truncated' => false,
            'hard_failure' => false,
            'failure_message' => null,
        ];

        $assessment = $service->assess($user, 'Создай заявку на перевозку из файла', $batch);

        $this->assertSame('order_intake', $assessment['intent']);
        $this->assertSame([], $assessment['blockers']);
    }

    #[Test]
    public function it_blocks_order_intake_when_orders_area_missing(): void
    {
        $service = $this->service();
        $user = $this->userWithAreas(['tasks']);

        $batch = [
            'items' => [],
            'combined_text' => 'Маршрут и груз',
            'truncated' => false,
            'hard_failure' => false,
            'failure_message' => null,
        ];

        $assessment = $service->assess($user, 'Создай заявку', $batch);

        $this->assertSame('order_intake', $assessment['intent']);
        $this->assertSame('visibility_orders', $assessment['blockers'][0]['code'] ?? null);

        $gap = $service->detectCapabilityGap(
            'Вам это недоступно: нет области «Заказы».',
            $assessment,
            [],
        );

        $this->assertSame('access', $gap['kind'] ?? null);
        $this->assertSame('visibility_orders', $gap['code'] ?? null);
    }

    #[Test]
    public function it_defaults_yurik_persona_to_basic_terms_intent(): void
    {
        $service = $this->service();
        $user = $this->userWithAreas(['settings_system']);

        $batch = [
            'items' => [],
            'combined_text' => 'Пункт 1. Срок оплаты 30 дней',
            'truncated' => false,
            'hard_failure' => false,
            'failure_message' => null,
        ];

        $assessment = $service->assess($user, 'Сохрани для заказчика', $batch, ['slug' => 'yurik']);

        $this->assertSame('basic_terms', $assessment['intent']);
    }

    #[Test]
    public function it_records_capability_gap_when_assistant_refuses_without_tools(): void
    {
        $service = $this->service();
        $user = $this->userWithAreas(['orders']);

        $batch = [
            'items' => [],
            'combined_text' => 'Москва — СПб',
            'truncated' => false,
            'hard_failure' => false,
            'failure_message' => null,
        ];

        $assessment = $service->assess($user, 'Оформи заявку', $batch);

        $gap = $service->detectCapabilityGap(
            'Пока не могу этого делать — уточните ставку.',
            $assessment,
            [],
        );

        $this->assertSame('capability', $gap['kind'] ?? null);
        $this->assertSame('assistant_refused_without_tool', $gap['code'] ?? null);
    }

    private function service(): CommandBarAttachmentService
    {
        return app(CommandBarAttachmentService::class);
    }

    /**
     * @param  list<string>  $areas
     */
    private function userWithAreas(array $areas): User
    {
        $role = Role::query()->create([
            'name' => 'test_'.uniqid(),
            'display_name' => 'Test',
            'permissions' => [],
            'visibility_areas' => $areas,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
