<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\PrintFormTemplatesMcpService;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_print_form_basic_terms')]
#[Description('Общие базовые условия cp/dp из «Шаблоны → Базовые условия для договоров-заявок»: список пунктов по стороне (заказчик/перевозчик). Вызывай перед зеркалированием или правкой через upsert_print_form_basic_terms.')]
class GetPrintFormBasicTermsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly PrintFormTemplatesMcpService $templates,
        private readonly ContractorPrintFormChangeRequestService $changes,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! $this->changes->canDirectManagePrintForm($user)) {
                throw new AuthenticationException('Чтение базовых условий доступно администратору или области settings_system.');
            }

            $validated = $request->validate([
                'party' => ['nullable', 'string', Rule::in(['customer', 'carrier'])],
                'contractor_id' => ['nullable', 'integer', 'min:1'],
            ]);

            return Response::json($this->templates->readBasicTerms(
                isset($validated['party']) ? (string) $validated['party'] : null,
                isset($validated['contractor_id']) ? (int) $validated['contractor_id'] : null,
            ));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'party' => $schema->string()
                ->enum(['customer', 'carrier'])
                ->description('customer — заказчик (cp), carrier — перевозчик (dp). Без значения — обе стороны.'),
            'contractor_id' => $schema->integer()
                ->description('ID контрагента; без значения — общие условия CRM.')
                ->min(1),
        ];
    }
}
