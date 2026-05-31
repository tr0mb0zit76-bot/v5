<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_order')]
#[Description('Карточка заказа по id с учётом прав пользователя (финансы — только при доступе).')]
class GetOrderTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderMcpService $orders,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'order_id' => ['required', 'integer', 'min:1'],
            ]);

            try {
                $order = $this->orders->get($user, (int) $validated['order_id']);
            } catch (ModelNotFoundException) {
                return Response::error('Заказ не найден или недоступен.');
            }

            return Response::json(['order' => $order]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_id' => $schema->integer()
                ->description('ID заказа в CRM.')
                ->min(1)
                ->required(),
        ];
    }
}
