<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\SalesBookMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_sales_book_article')]
#[Description('Полный текст страницы Книги продаж по id (markdown). Используй после search_sales_book_articles.')]
class GetSalesBookArticleTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly SalesBookMcpService $salesBook,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'article_id' => ['required', 'integer', 'min:1'],
                'max_chars' => ['nullable', 'integer', 'min:500', 'max:50000'],
                'format' => ['nullable', 'string', 'in:markdown,blocks,both'],
            ]);

            try {
                $result = $this->salesBook->get(
                    $user,
                    (int) $validated['article_id'],
                    isset($validated['max_chars']) ? (int) $validated['max_chars'] : null,
                    (string) ($validated['format'] ?? 'markdown'),
                );
            } catch (ModelNotFoundException) {
                return Response::error('Страница Книги продаж не найдена.');
            }

            return Response::json($result);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article_id' => $schema->integer()
                ->description('ID страницы из search_sales_book_articles.')
                ->min(1)
                ->required(),
            'max_chars' => $schema->integer()
                ->description('Лимит символов текста (по умолчанию из конфигурации).')
                ->min(500)
                ->max(50000),
            'format' => $schema->string()
                ->description('Формат ответа: markdown (по умолчанию), blocks или both.')
                ->enum(['markdown', 'blocks', 'both']),
        ];
    }
}
