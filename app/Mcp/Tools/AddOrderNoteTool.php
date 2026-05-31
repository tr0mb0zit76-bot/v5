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

#[Name('add_order_note')]
#[Description('Добавить заметку в ленту активности заказа. Требует доступ к заказам.')]
class AddOrderNoteTool extends Tool
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
                'body' => ['required', 'string', 'max:5000'],
                'title' => ['nullable', 'string', 'max:255'],
            ]);

            try {
                $result = $this->orders->addNote(
                    $user,
                    (int) $validated['order_id'],
                    (string) $validated['body'],
                    isset($validated['title']) ? (string) $validated['title'] : null,
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
            'body' => $schema->string()
                ->description('Текст заметки для ленты заказа.')
                ->max(5000)
                ->required(),
            'title' => $schema->string()
                ->description('Заголовок события в ленте. По умолчанию «Заметка».')
                ->max(255),
        ];
    }
}
