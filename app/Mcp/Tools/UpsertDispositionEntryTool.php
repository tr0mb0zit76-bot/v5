<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\DispositionMcpService;
use App\Support\DispositionSlot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('upsert_disposition_entry')]
#[Description('Записать или обновить ячейку диспозиции (утро/вечер: место и комментарий) по заказу «в пути». Требует доступ к заказам.')]
class UpsertDispositionEntryTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly DispositionMcpService $disposition,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'order_id' => ['required', 'integer', 'min:1'],
                'date' => ['required', 'date'],
                'slot' => ['required', 'string', 'in:'.implode(',', DispositionSlot::values())],
                'location' => ['nullable', 'string', 'max:500'],
                'comment' => ['nullable', 'string', 'max:5000'],
            ]);

            try {
                $result = $this->disposition->upsertEntry(
                    $user,
                    (int) $validated['order_id'],
                    (string) $validated['date'],
                    (string) $validated['slot'],
                    array_key_exists('location', $validated) ? (string) ($validated['location'] ?? '') : null,
                    array_key_exists('comment', $validated) ? (string) ($validated['comment'] ?? '') : null,
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
                ->description('ID заказа в статусе «в пути».')
                ->min(1)
                ->required(),
            'date' => $schema->string()
                ->description('Календарный день (Y-m-d).')
                ->format('date')
                ->required(),
            'slot' => $schema->string()
                ->description('Слот: morning (утро) или evening (вечер).')
                ->enum(DispositionSlot::values())
                ->required(),
            'location' => $schema->string()
                ->description('Местоположение (город/точка). Для закрытия напоминания достаточно заполнить это поле.')
                ->max(500),
            'comment' => $schema->string()
                ->description('Комментарий; при изменении попадает в ленту заказа.')
                ->max(5000),
        ];
    }
}
