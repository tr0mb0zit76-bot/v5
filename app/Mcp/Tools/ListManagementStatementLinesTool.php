<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ManagementAccountingMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_management_statement_lines')]
#[Description('Строки выписки по import_id: описание, сумма, статус разнесения и подсказки матчинга.')]
class ListManagementStatementLinesTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingMcpService $management,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'import_id' => ['required', 'integer', 'min:1'],
                'status' => ['nullable', 'string', Rule::in(['pending', 'allocated', 'skipped'])],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return Response::json([
                'lines' => $this->management->listLines(
                    $user,
                    (int) $validated['import_id'],
                    $validated['status'] ?? null,
                    (int) ($validated['limit'] ?? 50),
                ),
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'import_id' => $schema->integer()
                ->description('ID импорта выписки.')
                ->min(1)
                ->required(),
            'status' => $schema->string()
                ->description('Фильтр: pending | allocated | skipped')
                ->enum(['pending', 'allocated', 'skipped']),
            'limit' => $schema->integer()
                ->description('Максимум строк (1–100, по умолчанию 50).')
                ->min(1)
                ->max(100),
        ];
    }
}
