<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\LeadMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_lead')]
#[Description('Карточка лида: поля, operational_brief (gaps/next_move), прогресс БП, открытые задачи, wizard_path.')]
class GetLeadTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly LeadMcpService $leads,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'lead_id' => ['required', 'integer', 'min:1'],
            ]);

            $result = $this->leads->get($user, (int) $validated['lead_id']);

            return Response::json($result);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()
                ->description('ID лида.')
                ->min(1)
                ->required(),
        ];
    }
}
