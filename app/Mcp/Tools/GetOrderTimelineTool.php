<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\Order;
use App\Models\User;
use App\Services\Mcp\McpAccessGate;
use App\Services\OrderActivityTimelineService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_order_timeline')]
#[Description('Лента событий заказа: диспозиция, статусы, задачи, документы (с учётом прав).')]
class GetOrderTimelineTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly McpAccessGate $access,
        private readonly OrderActivityTimelineService $timeline,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'order_id' => ['required', 'integer', 'min:1'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            try {
                $order = $this->resolveOrder($user, (int) $validated['order_id']);
            } catch (ModelNotFoundException) {
                return Response::error('Заказ не найден или недоступен.');
            }

            $limit = isset($validated['limit']) ? (int) $validated['limit'] : 50;

            return Response::json([
                'order_id' => $order->id,
                'events' => $this->timeline->timelineForOrder($order, $limit),
            ]);
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
            'limit' => $schema->integer()
                ->description('Максимум событий в ответе (1–100).')
                ->min(1)
                ->max(100),
        ];
    }

    private function resolveOrder(User $user, int $orderId): Order
    {
        $this->access->requireOrdersArea($user);

        $builder = Order::query()->whereKey($orderId);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        $this->access->applyOrdersScope($builder, $user);

        $order = $builder->first();

        if ($order === null) {
            throw new ModelNotFoundException;
        }

        return $order;
    }
}
