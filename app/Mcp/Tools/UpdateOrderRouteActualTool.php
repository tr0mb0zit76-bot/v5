<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_order_route_actual')]
#[Description('Фактическая дата погрузки или выгрузки по маршруту заказа (не track_* и не order_date). «Груз забрали» = loading_actual.')]
class UpdateOrderRouteActualTool extends Tool
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
                'kind' => ['required', 'string', 'max:120'],
                'date' => ['required', 'string', 'max:32'],
                'leg_stage' => ['nullable', 'string', 'max:32'],
            ]);

            try {
                $result = $this->orders->updateRouteActual(
                    $user,
                    (int) $validated['order_id'],
                    (string) $validated['kind'],
                    (string) $validated['date'],
                    isset($validated['leg_stage']) ? (string) $validated['leg_stage'] : null,
                );
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();

                return Response::error(is_string($message) ? $message : 'Ошибка валидации.');
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
            'order_id' => $schema->integer()
                ->description('ID заказа.')
                ->min(1)
                ->required(),
            'kind' => $schema->string()
                ->description('loading_actual (фактическая погрузка / «груз забрали») или unloading_actual (фактическая выгрузка).')
                ->required(),
            'date' => $schema->string()
                ->description('Дата: Y-m-d или dd.mm.yyyy (например 15.05.2026).')
                ->required(),
            'leg_stage' => $schema->string()
                ->description('Плечо маршрута, по умолчанию leg_1.'),
        ];
    }
}
