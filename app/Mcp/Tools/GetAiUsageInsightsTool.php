<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Ai\AiUsageAnalyticsService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_ai_usage_insights')]
#[Description('Аналитика AI: частые вопросы в command bar, слабые/неудачные ответы, статистика tools и intake. Только admin / settings_system.')]
class GetAiUsageInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly AiUsageAnalyticsService $analytics,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canViewAiAnalytics($user)) {
                throw new AuthenticationException('Нет доступа к аналитике AI.');
            }

            $validated = $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'top_limit' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $result = $this->analytics->insights(
                (int) ($validated['days'] ?? config('ai.analytics.insights_default_days', 30)),
                (int) ($validated['top_limit'] ?? 20),
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
            'top_limit' => $schema->integer()
                ->description('Сколько топ-вопросов и tools вернуть (5–50).')
                ->min(5)
                ->max(50),
        ];
    }
}
