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

#[Name('get_proposal_html_template')]
#[Description('Карточка HTML-шаблона КП: плейсхолдеры, список img src, mailto менеджера. include_html=true — полный HTML (большой). id или slug.')]
class GetProposalHtmlTemplateTool extends Tool
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
                'id' => ['nullable', 'integer', 'min:1'],
                'slug' => ['nullable', 'string', 'max:120'],
                'include_html' => ['nullable', 'boolean'],
            ]);

            $key = $validated['id'] ?? $validated['slug'] ?? null;
            if ($key === null || $key === '') {
                return Response::error('Укажите id или slug шаблона.');
            }

            return Response::json($this->templates->get(
                $key,
                (bool) ($validated['include_html'] ?? false),
            ));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->description('ID шаблона.'),
            'slug' => $schema->string()->description('Slug, например parallel-import.'),
            'include_html' => $schema->boolean()->description('Вернуть полный html_body (по умолчанию false).'),
        ];
    }
}
