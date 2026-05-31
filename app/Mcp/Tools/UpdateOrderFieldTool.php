<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderMcpService;
use App\Support\OrderInlineFieldCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_order_field')]
#[Description('Изменить одно поле заказа (whitelist как в inline-редактировании грида): ставки, трек-номера, даты, ручной статус и т.д.')]
class UpdateOrderFieldTool extends Tool
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
                'field' => ['required', 'string', 'in:'.implode(',', OrderInlineFieldCatalog::allowedFields())],
                'value' => ['nullable'],
            ]);

            try {
                $result = $this->orders->updateField(
                    $user,
                    (int) $validated['order_id'],
                    (string) $validated['field'],
                    $validated['value'] ?? null,
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
            'field' => $schema->string()
                ->description('Имя поля: customer_rate, carrier_rate, manual_status, track_number_customer, order_date и др.')
                ->enum(OrderInlineFieldCatalog::allowedFields())
                ->required(),
            'value' => $schema->string()
                ->description('Новое значение. null или пустая строка — очистить поле.'),
        ];
    }
}
