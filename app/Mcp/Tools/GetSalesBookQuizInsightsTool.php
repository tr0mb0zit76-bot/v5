<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\SalesBook\SalesBookQuizInsightsService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_sales_book_quiz_insights')]
#[Description('Статистика тестов Книги продаж. Руководитель видит всех; менеджер — только свои попытки.')]
class GetSalesBookQuizInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly SalesBookQuizInsightsService $insights,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canViewSalesBookQuizInsights($user)) {
                throw new AuthenticationException('Нет доступа к статистике тестов Книги продаж.');
            }

            $validated = $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'article_id' => ['nullable', 'integer', 'min:1'],
                'user_id' => ['nullable', 'integer', 'min:1'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $requestedUserId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
            $userId = RoleAccess::resolveSalesBookQuizInsightsUserId($user, $requestedUserId);

            return Response::json($this->insights->insights(
                (int) ($validated['days'] ?? 30),
                isset($validated['article_id']) ? (int) $validated['article_id'] : null,
                $userId,
                (int) ($validated['limit'] ?? 20),
            ));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->description('Период анализа в днях (1-365).')
                ->min(1)
                ->max(365),
            'article_id' => $schema->integer()
                ->description('Ограничить статистику одной страницей с тестом.')
                ->min(1),
            'user_id' => $schema->integer()
                ->description('Фильтр по сотруднику (только admin / supervisor).')
                ->min(1),
            'limit' => $schema->integer()
                ->description('Сколько строк вернуть в каждой секции (1-100).')
                ->min(1)
                ->max(100),
        ];
    }
}
