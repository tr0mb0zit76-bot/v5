<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\SalesBook\SalesBookQualityInsightsService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_sales_book_quality_insights')]
#[Description('Качество Книги продаж: проблемные статьи, свежие замечания, черновики и подсказки редактору. Только для редакторов Книги продаж.')]
class GetSalesBookQualityInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly SalesBookQualityInsightsService $insights,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canWriteSalesBook($user)) {
                throw new AuthenticationException('Нет доступа к аналитике качества Книги продаж.');
            }

            $validated = $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            return Response::json($this->insights->insights(
                (int) ($validated['days'] ?? 30),
                (int) ($validated['limit'] ?? 10),
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
            'limit' => $schema->integer()
                ->description('Сколько проблемных статей и свежих замечаний вернуть (1-50).')
                ->min(1)
                ->max(50),
        ];
    }
}
