<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\ManagementAccounting\CashFlowExecutiveBriefService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_cash_flow_executive_brief')]
#[Description('Комплексный CFO-бриф по кассе: чистый ДДС по выпискам с даты, помесячно, топ контрагентов, структура расходов, ДЗ/КЗ CRM, платежи без заказа, pending, диагноз и действия. Для вопросов «сколько свободных денег / куда ушли / дебиторка».')]
class GetCashFlowExecutiveBriefTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly CashFlowExecutiveBriefService $brief,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canAccessManagementAccounting($user)) {
                throw new AuthenticationException('Нет доступа к управленческому учёту.');
            }

            $validated = $request->validate([
                'from_date' => ['nullable', 'date'],
                'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            ]);

            return Response::json(
                $this->brief->brief(
                    $user,
                    isset($validated['from_date']) ? (string) $validated['from_date'] : null,
                    isset($validated['to_date']) ? (string) $validated['to_date'] : null,
                ),
            );
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from_date' => $schema->string()
                ->description('Начало периода YYYY-MM-DD. По умолчанию — config/management_accounting.executive_brief_default_from или дата первого заказа.'),
            'to_date' => $schema->string()
                ->description('Конец периода YYYY-MM-DD. По умолчанию — последняя дата в выписке.'),
        ];
    }
}
