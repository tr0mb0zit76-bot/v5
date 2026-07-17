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

#[Name('update_proposal_html_template')]
#[Description('Обновить HTML-шаблон КП: name, is_active, text_replacements, images[{find,url}], опционально html_body целиком. Нормализует mailto менеджера. mcp:write + settings_system.')]
class UpdateProposalHtmlTemplateTool extends Tool
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
                'name' => ['nullable', 'string', 'max:200'],
                'is_active' => ['nullable', 'boolean'],
                'text_replacements' => ['nullable', 'array'],
                'images' => ['nullable', 'array', 'max:20'],
                'html_body' => ['nullable', 'string', 'max:500000'],
                'css_inline' => ['nullable', 'string', 'max:200000'],
            ]);

            $key = $validated['id'] ?? $validated['slug'] ?? null;
            if ($key === null || $key === '') {
                return Response::error('Укажите id или slug шаблона.');
            }

            return Response::json($this->templates->update($key, $validated));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1),
            'slug' => $schema->string(),
            'name' => $schema->string(),
            'is_active' => $schema->boolean(),
            'text_replacements' => $schema->object()->description('{"фрагмент HTML/текста":"замена"}'),
            'images' => $schema->array()->description('[{find,url}] — find = текущий src или substring.'),
            'html_body' => $schema->string()->description('Полная замена HTML (осторожно).'),
            'css_inline' => $schema->string(),
        ];
    }
}
