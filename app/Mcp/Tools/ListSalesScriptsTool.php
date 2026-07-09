<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\SalesScriptsMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_sales_scripts')]
#[Description('Список скриптов продаж, их версий, размера графа, числа прохождений и количества шаблонов блоков.')]
class ListSalesScriptsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly SalesScriptsMcpService $salesScripts,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'query' => ['nullable', 'string', 'max:160'],
            ]);

            return Response::json($this->salesScripts->list($user, $validated['query'] ?? null));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Необязательный поиск по названию или описанию.')
                ->max(160),
        ];
    }
}
