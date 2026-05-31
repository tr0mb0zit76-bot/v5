<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\SalesBookMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search_sales_book_articles')]
#[Description('Поиск страниц Книги продаж по заголовку. Требует доступ к разделу «Книга продаж».')]
class SearchSalesBookArticlesTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly SalesBookMcpService $salesBook,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'query' => ['nullable', 'string', 'max:120'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $result = $this->salesBook->search(
                $user,
                (string) ($validated['query'] ?? ''),
                (int) ($validated['limit'] ?? 20),
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
            'query' => $schema->string()
                ->description('Фрагмент заголовка страницы. Пустая строка — первые страницы в пределах лимита.')
                ->max(120),
            'limit' => $schema->integer()
                ->description('Максимум записей (1–50).')
                ->min(1)
                ->max(50),
        ];
    }
}
