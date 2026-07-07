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

#[Name('upsert_sales_book_article')]
#[Description('Создать или обновить дочернюю страницу Книги продаж под указанным родителем (по заголовку родителя). Требует sales_book_write.')]
class UpsertSalesBookArticleTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly SalesBookMcpService $salesBook,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'parent_title' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'markdown_content' => ['nullable', 'required_without:blocks', 'string', 'max:500000'],
                'blocks' => ['nullable', 'required_without:markdown_content', 'array', 'max:200'],
                'blocks.*' => ['array'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'create_parent_if_missing' => ['nullable', 'boolean'],
            ]);

            $result = $this->salesBook->upsertChildPage(
                $user,
                (string) $validated['parent_title'],
                (string) $validated['title'],
                (string) ($validated['markdown_content'] ?? ''),
                isset($validated['sort_order']) ? (int) $validated['sort_order'] : null,
                [],
                (bool) ($validated['create_parent_if_missing'] ?? false),
                $validated['blocks'] ?? [],
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
            'parent_title' => $schema->string()
                ->description('Точный заголовок родительской страницы, например «Руководство по CRM».')
                ->max(255),
            'title' => $schema->string()
                ->description('Заголовок дочерней страницы, например «Документы».')
                ->max(255),
            'markdown_content' => $schema->string()
                ->description('Полный текст страницы в Markdown. Можно не передавать, если переданы blocks.')
                ->max(500000),
            'blocks' => $schema->array()
                ->items($schema->object())
                ->description('Структурные блоки v1. Поддерживаются heading, paragraph, list, todo_list, table, code, quote, image.'),
            'sort_order' => $schema->integer()
                ->description('Порядок среди соседних страниц (необязательно).')
                ->min(0)
                ->max(1000000),
            'create_parent_if_missing' => $schema->boolean()
                ->description('Создать корневую родительскую страницу, если её ещё нет в Книге продаж.'),
        ];
    }
}
