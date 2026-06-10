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

#[Name('get_management_accounting_analytics')]
#[Description('Аналитика управленческого учёта: план/факт по статьям за месяц, квартал или год.')]
class GetManagementAccountingAnalyticsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingMcpService $management,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'period_type' => ['required', Rule::in(['month', 'quarter', 'year'])],
                'period_anchor' => ['nullable', 'date'],
            ]);

            return Response::json([
                'analytics' => $this->management->analytics(
                    $user,
                    (string) $validated['period_type'],
                    $validated['period_anchor'] ?? null,
                ),
            ]);
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
                ->enum(['month', 'quarter', 'year'])
                ->required(),
            'period_anchor' => $schema->string()
                ->description('Опорная дата периода (YYYY-MM-DD). По умолчанию — текущий месяц.'),
        ];
    }
}
