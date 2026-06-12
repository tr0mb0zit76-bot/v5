<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingInsightsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_management_accounting_insights')]
#[Description('Управленческая аналитика уровня CFO: KPI, тренды к прошлому периоду, структура расходов, план/факт, риски разнесения выписки и рекомендации.')]
class GetManagementAccountingInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingInsightsService $insights,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'period_type' => ['nullable', Rule::in(['month', 'quarter', 'year'])],
                'period_anchor' => ['nullable', 'date'],
                'watchlist_limit' => ['nullable', 'integer', 'min:3', 'max:15'],
            ]);

            return Response::json(
                $this->insights->insights(
                    $user,
                    (string) ($validated['period_type'] ?? 'month'),
                    $validated['period_anchor'] ?? null,
                    (int) ($validated['watchlist_limit'] ?? 8),
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
            'period_type' => $schema->string()
                ->description('month | quarter | year')
                ->enum(['month', 'quarter', 'year']),
            'period_anchor' => $schema->string()
                ->description('Опорная дата периода (YYYY-MM-DD).'),
            'watchlist_limit' => $schema->integer()
                ->description('Сколько статей вернуть в watchlist (3–15).')
                ->min(3)
                ->max(15),
        ];
    }
}
