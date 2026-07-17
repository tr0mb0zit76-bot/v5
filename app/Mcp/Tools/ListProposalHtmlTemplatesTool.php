<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ProposalHtmlTemplatesMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_proposal_html_templates')]
#[Description('Список HTML-шаблонов КП (модуль «Шаблоны КП»). Возвращает id/slug/name и подсказку по stock_assets. Для settings_system / admin.')]
class ListProposalHtmlTemplatesTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ProposalHtmlTemplatesMcpService $templates,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $this->templates->requireManageAccess($user);

            $validated = $request->validate([
                'query' => ['nullable', 'string', 'max:200'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return Response::json($this->templates->list(
                isset($validated['query']) ? (string) $validated['query'] : null,
                (int) ($validated['limit'] ?? 50),
            ));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Поиск по name/slug.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Лимит (по умолчанию 50).'),
        ];
    }
}
