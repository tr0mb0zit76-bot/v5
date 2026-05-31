<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ContractorMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_contractor')]
#[Description('Карточка контрагента по id с учётом прав видимости.')]
class GetContractorTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ContractorMcpService $contractors,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'contractor_id' => ['required', 'integer', 'min:1'],
            ]);

            try {
                $contractor = $this->contractors->get($user, (int) $validated['contractor_id']);
            } catch (ModelNotFoundException) {
                return Response::error('Контрагент не найден или недоступен.');
            }

            return Response::json(['contractor' => $contractor]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contractor_id' => $schema->integer()
                ->description('ID контрагента в CRM.')
                ->min(1)
                ->required(),
        ];
    }
}
