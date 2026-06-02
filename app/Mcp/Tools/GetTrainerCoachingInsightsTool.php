<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\SalesScripts\TrainerCoachingInsightsService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_trainer_coaching_insights')]
#[Description('Аналитика тренажёра продаж: тупики, зацикливание диалогов, hotspots по профилям и сценариям, рекомендации. Доступ: аналитика тренажёра или системные настройки.')]
class GetTrainerCoachingInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly TrainerCoachingInsightsService $insights,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canViewTrainerAnalytics($user) && ! RoleAccess::canViewAiAnalytics($user)) {
                throw new AuthenticationException('Нет доступа к аналитике тренажёра.');
            }

            $validated = $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'user_id' => ['nullable', 'integer', 'min:1'],
                'sample_limit' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $result = $this->insights->insights(
                $user,
                (int) ($validated['days'] ?? 30),
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                (int) ($validated['sample_limit'] ?? 15),
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
                ->description('Фильтр по менеджеру (только для admin / supervisor / settings_system).')
                ->min(1),
            'sample_limit' => $schema->integer()
                ->description('Сколько проблемных сессий вернуть в sample (5–50).')
                ->min(5)
                ->max(50),
        ];
    }
}
