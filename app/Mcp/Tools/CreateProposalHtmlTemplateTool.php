<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ProposalHtmlTemplatesMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_proposal_html_template')]
#[Description('Создать HTML-шаблон КП. mode=cold — короткое письмо с title/intro/points/cta и картинкой (stock_asset или hero_image URL). mode=clone — клон rich-шаблона (base_slug=parallel-import) + text_replacements и images[{find,url}]. Нужен mcp:write. settings_system.')]
class CreateProposalHtmlTemplateTool extends Tool
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
                'mode' => ['nullable', 'string', Rule::in(['cold', 'clone'])],
                'name' => ['required', 'string', 'max:200'],
                'slug' => ['nullable', 'string', 'max:120'],
                'is_active' => ['nullable', 'boolean'],
                'base_slug' => ['nullable', 'string', 'max:120'],
                'preheader' => ['nullable', 'string', 'max:500'],
                'title' => ['nullable', 'string', 'max:300'],
                'intro' => ['nullable', 'string', 'max:5000'],
                'angle' => ['nullable', 'string', 'max:5000'],
                'points' => ['nullable', 'array', 'max:12'],
                'points.*' => ['nullable', 'string', 'max:500'],
                'cta' => ['nullable', 'string', 'max:2000'],
                'stock_asset' => ['nullable', 'string', 'max:120'],
                'hero_image' => ['nullable', 'string', 'max:2000'],
                'text_replacements' => ['nullable', 'array'],
                'images' => ['nullable', 'array', 'max:20'],
            ]);

            return Response::json($this->templates->create($user, $validated));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()->enum(['cold', 'clone'])
                ->description('cold = новый макет письма; clone = копия rich EmailMaker (по умолчанию cold).'),
            'name' => $schema->string()->description('Название шаблона в CRM.')->required(),
            'slug' => $schema->string()->description('Опциональный slug.'),
            'is_active' => $schema->boolean(),
            'base_slug' => $schema->string()->description('Для clone: например parallel-import.'),
            'preheader' => $schema->string()->description('Cold: preheader.'),
            'title' => $schema->string()->description('Cold: заголовок блока.'),
            'intro' => $schema->string()->description('Cold: вступление.'),
            'angle' => $schema->string()->description('Cold: второй абзац.'),
            'points' => $schema->array()->items($schema->string())->description('Cold: маркеры (до 12).'),
            'cta' => $schema->string()->description('Cold: призыв к действию.'),
            'stock_asset' => $schema->string()->description('Cold: route.svg, customs.svg, chemical.svg, …'),
            'hero_image' => $schema->string()->description('Cold: URL или /assets/… картинки героя.'),
            'text_replacements' => $schema->object()->description('Clone: {"старый текст":"новый"}.'),
            'images' => $schema->array()->description('Clone/update: [{find,url}] или порядок URL для замены img src.'),
        ];
    }
}
