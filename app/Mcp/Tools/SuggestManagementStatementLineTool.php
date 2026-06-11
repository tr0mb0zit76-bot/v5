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

#[Name('suggest_management_statement_line')]
#[Description('Подсказка разнесения для строки выписки: эвристики + правила; при нескольких заявках по контрагенту и сумме — candidates[].')]
class SuggestManagementStatementLineTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ManagementAccountingMcpService $management,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'line_id' => ['required', 'integer', 'min:1'],
            ]);

            return Response::json(
                $this->management->suggestLine($user, (int) $validated['line_id']),
            );
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'line_id' => $schema->integer()
                ->description('ID строки выписки.')
                ->min(1)
                ->required(),
        ];
    }
}
