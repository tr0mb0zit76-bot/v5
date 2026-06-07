<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\PrintFormTemplatesMcpService;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_print_form_templates_insights')]
#[Description('Шаблоны печатных форм и базовые условия: список шаблонов, переменные DOCX, пункты условий и диагностика, почему они не попали в черновик. Для Юрика / settings_system.')]
class GetPrintFormTemplatesInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly PrintFormTemplatesMcpService $insights,
        private readonly ContractorPrintFormChangeRequestService $changes,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! $this->changes->canDirectManagePrintForm($user)) {
                throw new AuthenticationException('Нет доступа к шаблонам печатных форм и базовым условиям.');
            }

            $validated = $request->validate([
                'code' => ['nullable', 'string', 'max:120'],
                'query' => ['nullable', 'string', 'max:200'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            return Response::json($this->insights->insights(
                isset($validated['code']) ? (string) $validated['code'] : null,
                isset($validated['query']) ? (string) $validated['query'] : null,
                (int) ($validated['limit'] ?? 20),
            ));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()
                ->description('Точный код шаблона, например dz_s_perevozom_RF.'),
            'query' => $schema->string()
                ->description('Поиск по коду или названию, если code не задан.'),
            'limit' => $schema->integer()
                ->description('Сколько шаблонов вернуть при поиске (1–50).')
                ->min(1)
                ->max(50),
        ];
    }
}
