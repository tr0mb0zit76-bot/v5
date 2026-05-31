<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderDocumentMcpService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_order_documents')]
#[Description('Список документов заказа по order_id с учётом прав раздела «Документы».')]
class ListOrderDocumentsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderDocumentMcpService $documents,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'order_id' => ['required', 'integer', 'min:1'],
            ]);

            try {
                $result = $this->documents->listForOrder($user, (int) $validated['order_id']);
            } catch (ModelNotFoundException) {
                return Response::error('Заказ не найден.');
            } catch (AuthenticationException $exception) {
                return Response::error($exception->getMessage());
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
            'order_id' => $schema->integer()
                ->description('ID заказа в CRM.')
                ->min(1)
                ->required(),
        ];
    }
}
