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

#[Name('list_management_statement_imports')]
#[Description('Список импортов банковских выписок для управленческого учёта (доступ по флагу can_management_accounting).')]
class ListManagementStatementImportsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingMcpService $management,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            return Response::json([
                'imports' => $this->management->listImports($user, (int) ($validated['limit'] ?? 20)),
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
                ->description('Максимум записей (1–50, по умолчанию 20).')
                ->min(1)
                ->max(50),
        ];
    }
}
