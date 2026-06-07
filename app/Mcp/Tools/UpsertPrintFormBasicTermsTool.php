<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\Contractor;
use App\Models\PrintFormBasicTerm;
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

#[Name('upsert_print_form_basic_terms')]
#[Description('Сохранить базовые условия cp/dp (глобальные или для контрагента). Прямая запись — только settings_system / admin; иначе используйте submit_contractor_print_form_change.')]
class UpsertPrintFormBasicTermsTool extends Tool
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
                throw new AuthenticationException('Прямое сохранение базовых условий доступно только администратору или settings_system.');
            }

            $validated = $request->validate([
                'party' => ['required', 'string', Rule::in([
                    PrintFormBasicTerm::PARTY_CUSTOMER,
                    PrintFormBasicTerm::PARTY_CARRIER,
                ])],
                'contractor_id' => ['nullable', 'integer', 'min:1'],
                'items' => ['present', 'array'],
                'items.*' => ['nullable', 'string', 'max:8000'],
            ]);

            $contractorId = isset($validated['contractor_id']) ? (int) $validated['contractor_id'] : null;

            if ($contractorId !== null && ! Contractor::query()->whereKey($contractorId)->exists()) {
                return Response::error('Контрагент не найден.');
            }

            $items = is_array($validated['items']) ? $validated['items'] : [];

            return Response::json($this->templates->upsertBasicTerms(
                (string) $validated['party'],
                $contractorId,
                $items,
            ));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'party' => $schema->string()->enum(['customer', 'carrier'])->required(),
            'contractor_id' => $schema->integer()->description('ID контрагента; без значения — общие условия.')->min(1),
            'items' => $schema->array()->items($schema->string())->description('Список текстов пунктов.'),
        ];
    }
}
