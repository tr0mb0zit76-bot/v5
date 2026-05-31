<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\TaskMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_task')]
#[Description('Создать задачу в CRM. Требует доступ к разделу «Задачи»; при scope «только свои» ответственным может быть только текущий пользователь.')]
class CreateTaskTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly TaskMcpService $tasks,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'responsible_id' => ['required', 'integer', 'min:1'],
                'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
                'description' => ['nullable', 'string', 'max:10000'],
                'due_at' => ['nullable', 'date'],
                'order_id' => ['nullable', 'integer', 'min:1'],
                'lead_id' => ['nullable', 'integer', 'min:1'],
                'contractor_id' => ['nullable', 'integer', 'min:1'],
                'meta' => ['nullable', 'array'],
            ]);

            try {
                $result = $this->tasks->create($user, [
                    'title' => (string) $validated['title'],
                    'responsible_id' => (int) $validated['responsible_id'],
                    'priority' => $validated['priority'] ?? 'medium',
                    'description' => $validated['description'] ?? null,
                    'due_at' => $validated['due_at'] ?? null,
                    'order_id' => $validated['order_id'] ?? null,
                    'lead_id' => $validated['lead_id'] ?? null,
                    'contractor_id' => $validated['contractor_id'] ?? null,
                    'meta' => $validated['meta'] ?? null,
                ]);
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
            'title' => $schema->string()
                ->description('Заголовок задачи.')
                ->max(255)
                ->required(),
            'responsible_id' => $schema->integer()
                ->description('ID пользователя-ответственного.')
                ->min(1)
                ->required(),
            'priority' => $schema->string()
                ->description('Приоритет: low, medium, high, critical. По умолчанию medium.')
                ->enum(['low', 'medium', 'high', 'critical']),
            'description' => $schema->string()
                ->description('Описание задачи.')
                ->max(10000),
            'due_at' => $schema->string()
                ->description('Срок (дата или datetime, Y-m-d или ISO 8601).')
                ->format('date-time'),
            'order_id' => $schema->integer()
                ->description('Привязка к заказу (должен быть доступен пользователю).')
                ->min(1),
            'lead_id' => $schema->integer()
                ->description('Привязка к лиду.')
                ->min(1),
            'contractor_id' => $schema->integer()
                ->description('Привязка к контрагенту.')
                ->min(1),
            'meta' => $schema->object()
                ->description('Произвольные метаданные (JSON-объект), например disposition_slot_key.'),
        ];
    }
}
