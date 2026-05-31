<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search_orders')]
#[Description('Поиск заказов по номеру, id или номеру заказчика. Возвращает краткий список доступных пользователю заказов.')]
class SearchOrdersTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderMcpService $orders,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'query' => ['nullable', 'string', 'max:120'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            ]);

            $result = $this->orders->search(
                $user,
                (string) ($validated['query'] ?? ''),
                (int) ($validated['limit'] ?? 15),
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
                ->description('Номер заказа, id или фрагмент номера заказчика. Пустая строка — последние заказы в пределах лимита.')
                ->max(120),
            'limit' => $schema->integer()
                ->description('Максимум записей (1–25).')
                ->min(1)
                ->max(25),
        ];
    }
}
