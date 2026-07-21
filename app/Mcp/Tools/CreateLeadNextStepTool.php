<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\LeadMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_lead_next_step')]
#[Description('Запланировать следующий контакт по лиду: создаёт задачу (как «Запланировать следующий контакт» в мастере) и при due_at обновляет next_contact_at. Нужны области leads+tasks и mcp:write.')]
class CreateLeadNextStepTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly LeadMcpService $leads,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'lead_id' => ['required', 'integer', 'min:1'],
                'title' => ['required', 'string', 'max:255'],
                'due_at' => ['nullable', 'date'],
                'description' => ['nullable', 'string', 'max:10000'],
                'responsible_id' => ['nullable', 'integer', 'min:1'],
                'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            ]);

            try {
                $result = $this->leads->createNextStep($user, (int) $validated['lead_id'], [
                    'title' => (string) $validated['title'],
                    'due_at' => $validated['due_at'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'responsible_id' => isset($validated['responsible_id']) ? (int) $validated['responsible_id'] : null,
                    'priority' => $validated['priority'] ?? 'high',
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
            'lead_id' => $schema->integer()
                ->description('ID лида.')
                ->min(1)
                ->required(),
            'title' => $schema->string()
                ->description('Заголовок задачи следующего шага.')
                ->max(255)
                ->required(),
            'due_at' => $schema->string()
                ->description('Срок контакта (дата/datetime). Если задан — пишется в next_contact_at лида.')
                ->format('date-time'),
            'description' => $schema->string()
                ->description('Описание задачи.')
                ->max(10000),
            'responsible_id' => $schema->integer()
                ->description('Ответственный за задачу. По умолчанию — ответственный лида или текущий пользователь.')
                ->min(1),
            'priority' => $schema->string()
                ->description('Приоритет задачи. По умолчанию high.')
                ->enum(['low', 'medium', 'high', 'critical']),
        ];
    }
}
