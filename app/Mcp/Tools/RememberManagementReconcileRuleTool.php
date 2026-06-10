<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ManagementAccountingMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('remember_management_reconcile_rule')]
#[Description('Сохранить правило разнесения по ключевому слову в назначении платежа (обучение на исправлениях).')]
class RememberManagementReconcileRuleTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingMcpService $management,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'keyword' => ['required', 'string', 'min:2', 'max:128'],
                'direction' => ['nullable', Rule::in(['in', 'out'])],
                'allocation_type' => ['required', Rule::in(['operational', 'payroll', 'category'])],
                'category_id' => ['nullable', 'integer', 'min:1'],
                'user_id' => ['nullable', 'integer', 'min:1'],
                'order_number' => ['nullable', 'string', 'max:32'],
                'payment_schedule_id' => ['nullable', 'integer', 'min:1'],
                'notes' => ['nullable', 'string', 'max:255'],
                'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
            ]);

            $rule = $this->management->rememberRule($user, $validated);

            return Response::json([
                'rule' => [
                    'id' => $rule->id,
                    'keyword' => $rule->keyword,
                    'direction' => $rule->direction,
                    'allocation_type' => $rule->allocation_type,
                    'category_id' => $rule->category_id,
                    'user_id' => $rule->user_id,
                    'order_number' => $rule->order_number,
                    'payment_schedule_id' => $rule->payment_schedule_id,
                    'notes' => $rule->notes,
                    'priority' => $rule->priority,
                ],
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->description('Подстрока в назначении платежа (регистр не важен)')
                ->min(2)
                ->max(128)
                ->required(),
            'direction' => $schema->string()
                ->description('in | out — необязательно, если правило для обоих направлений')
                ->enum(['in', 'out']),
            'allocation_type' => $schema->string()
                ->description('operational | payroll | category')
                ->enum(['operational', 'payroll', 'category'])
                ->required(),
            'category_id' => $schema->integer()->description('Статья (для category/payroll)')->min(1),
            'user_id' => $schema->integer()->description('Сотрудник (для payroll)')->min(1),
            'order_number' => $schema->string()->description('Номер заявки (для operational)')->max(32),
            'payment_schedule_id' => $schema->integer()->description('График оплат (для operational)')->min(1),
            'notes' => $schema->string()->description('Пояснение')->max(255),
            'priority' => $schema->integer()->description('Приоритет (выше = раньше), по умолчанию 100')->min(1)->max(999),
        ];
    }
}
