<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderIntakeMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_order_intake_drafts')]
#[Description('Последние черновики заявок (после распознавания в мастере заказа).')]
class ListOrderIntakeDraftsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderIntakeMcpService $intake,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            ]);

            $drafts = $this->intake->listRecentDrafts($user, (int) ($validated['limit'] ?? 10));

            return Response::json(['drafts' => $drafts]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Сколько последних черновиков вернуть (1–25, по умолчанию 10).')
                ->min(1)
                ->max(25),
        ];
    }
}
