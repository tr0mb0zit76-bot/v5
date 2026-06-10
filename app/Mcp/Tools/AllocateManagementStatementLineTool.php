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

#[Name('allocate_management_statement_line')]
#[Description('Разнести строку выписки (операционный платёж, ФОТ или статья). Опционально запомнить правило по ключевому слову.')]
class AllocateManagementStatementLineTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingMcpService $management,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'line_id' => ['required', 'integer', 'min:1'],
                'allocation_type' => ['required', Rule::in(['operational', 'payroll', 'category'])],
                'category_id' => ['nullable', 'integer', 'min:1'],
                'payment_schedule_id' => ['nullable', 'integer', 'min:1'],
                'user_id' => ['nullable', 'integer', 'min:1'],
                'amount' => ['nullable', 'numeric', 'min:0.01'],
                'notes' => ['nullable', 'string', 'max:500'],
                'remember_keyword' => ['nullable', 'string', 'min:2', 'max:128'],
                'remember_notes' => ['nullable', 'string', 'max:255'],
            ]);

            return Response::json(
                $this->management->allocateLine($user, (int) $validated['line_id'], $validated),
            );
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'line_id' => $schema->integer()
                ->description('ID строки выписки.')
                ->min(1)
                ->required(),
            'allocation_type' => $schema->string()
                ->description('operational — график оплат; payroll — ФОТ; category — статья расхода/дохода')
                ->enum(['operational', 'payroll', 'category'])
                ->required(),
            'category_id' => $schema->integer()->description('ID статьи management_expense_categories')->min(1),
            'payment_schedule_id' => $schema->integer()->description('ID графика оплат (для operational)')->min(1),
            'user_id' => $schema->integer()->description('ID сотрудника (для payroll)')->min(1),
            'amount' => $schema->number()->description('Сумма разнесения (по умолчанию сумма строки)')->min(0.01),
            'notes' => $schema->string()->description('Комментарий')->max(500),
            'remember_keyword' => $schema->string()
                ->description('Фрагмент назначения платежа для обучения правила (например «комиссия сбера»)')
                ->min(2)
                ->max(128),
            'remember_notes' => $schema->string()->description('Пояснение к правилу')->max(255),
        ];
    }
}
