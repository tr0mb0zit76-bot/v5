<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderIntakeMcpService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_order_intake_draft')]
#[Description('Черновик заявки заказчика (после POST /orders/intake/extract): wizard_patch, предупреждения, совпадения контрагентов.')]
class GetOrderIntakeDraftTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderIntakeMcpService $intake,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'draft_id' => ['required', 'integer', 'min:1'],
            ]);

            try {
                $draft = $this->intake->getDraft($user, (int) $validated['draft_id']);
            } catch (ModelNotFoundException) {
                return Response::error('Черновик заявки не найден.');
            } catch (AuthenticationException $exception) {
                return Response::error($exception->getMessage());
            }

            return Response::json(['draft' => $draft]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_id' => $schema->integer()
                ->description('ID черновика из ответа orders.intake.extract (поле draft_id).')
                ->min(1)
                ->required(),
        ];
    }
}
