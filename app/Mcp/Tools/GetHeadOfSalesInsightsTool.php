<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Commercial\HeadOfSalesInsightsService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_head_of_sales_insights')]
#[Description('Сводка для руководителя отдела продаж: маржа и объём по менеджерам, воронка лидов, скрипты, риски открытой воронки, мультимодальный микс, приоритетные действия.')]
class GetHeadOfSalesInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly HeadOfSalesInsightsService $insights,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canViewHeadOfSalesInsights($user)) {
                throw new AuthenticationException('Нет доступа к аналитике руководителя продаж.');
            }

            $validated = $request->validate([
                'days' => ['nullable', 'integer', 'min:7', 'max:365'],
                'user_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $result = $this->insights->insights(
                $user,
                (int) ($validated['days'] ?? 90),
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
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
                ->description('Глубина периода в днях (7–365), по умолчанию 90.')
                ->min(7)
                ->max(365),
            'user_id' => $schema->integer()
                ->description('Фильтр по менеджеру (руководитель / admin).')
                ->min(1),
        ];
    }
}
