<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\TaskMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_task')]
#[Description('Карточка задачи по id с учётом прав видимости.')]
class GetTaskTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly TaskMcpService $tasks,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'task_id' => ['required', 'integer', 'min:1'],
            ]);

            try {
                $task = $this->tasks->get($user, (int) $validated['task_id']);
            } catch (ModelNotFoundException) {
                return Response::error('Задача не найдена или недоступна.');
            }

            return Response::json(['task' => $task]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->description('ID задачи в CRM.')
                ->min(1)
                ->required(),
        ];
    }
}
