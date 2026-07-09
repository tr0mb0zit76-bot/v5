<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\SalesScriptsMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_sales_script_graph')]
#[Description('Полный граф версии скрипта: узлы, живые фразы клиента, переходы, направление разговора и предпросмотр следующего хода.')]
class GetSalesScriptGraphTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly SalesScriptsMcpService $salesScripts,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'script_id' => ['required', 'integer', 'min:1'],
                'version_id' => ['nullable', 'integer', 'min:1'],
            ]);

            try {
                return Response::json($this->salesScripts->graph(
                    $user,
                    (int) $validated['script_id'],
                    isset($validated['version_id']) ? (int) $validated['version_id'] : null,
                ));
            } catch (ModelNotFoundException) {
                return Response::error('Скрипт или версия не найдены.');
            }
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->integer()
                ->description('ID скрипта из list_sales_scripts.')
                ->min(1)
                ->required(),
            'version_id' => $schema->integer()
                ->description('Конкретная версия; без параметра берётся активная или последняя.')
                ->min(1),
        ];
    }
}
