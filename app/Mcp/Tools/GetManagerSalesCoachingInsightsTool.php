<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_manager_sales_coaching_insights')]
#[Description('Outcome Intelligence: паттерны проигрышей/выигрышей по лидам менеджера, гигиена сделки, idle vs активность на этапах, рекомендации. Без почты и телефонии.')]
class GetManagerSalesCoachingInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagerSalesCoachingInsightsService $insights,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canViewSalesCoachingInsights($user)) {
                throw new AuthenticationException('Нет доступа к коучингу по воронке.');
            }

            $validated = $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'user_id' => ['nullable', 'integer', 'min:1'],
                'sample_limit' => ['nullable', 'integer', 'min:3', 'max:25'],
            ]);

            $result = $this->insights->insights(
                $user,
                (int) ($validated['days'] ?? config('outcome_intelligence.coaching_default_days', 90)),
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                (int) ($validated['sample_limit'] ?? config('outcome_intelligence.coaching_sample_limit', 10)),
            );

            return Response::json($result);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->description('Глубина периода в днях (1–365).')
                ->min(1)
                ->max(365),
            'user_id' => $schema->integer()
                ->description('Фильтр по менеджеру (admin / supervisor / settings_system).')
                ->min(1),
            'sample_limit' => $schema->integer()
                ->description('Сколько контрастных пар lost/won вернуть (3–25).')
                ->min(3)
                ->max(25),
        ];
    }
}
