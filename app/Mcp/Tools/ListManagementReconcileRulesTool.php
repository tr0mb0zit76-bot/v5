<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ManagementAccountingMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_management_reconcile_rules')]
#[Description('Активные правила автоматического разнесения выписки.')]
class ListManagementReconcileRulesTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingMcpService $management,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return Response::json([
                'rules' => $this->management->listRules($user, (int) ($validated['limit'] ?? 30)),
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Максимум правил (1–100, по умолчанию 30).')
                ->min(1)
                ->max(100),
        ];
    }
}
