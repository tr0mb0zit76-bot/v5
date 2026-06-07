<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\Contractor;
use App\Models\PrintFormBasicTerm;
use App\Models\User;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Support\RoleAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('submit_contractor_print_form_change')]
#[Description('Отправить базовые условия контрагента на согласование руководителю (задача + уведомление). Для менеджеров без settings_system.')]
class SubmitContractorPrintFormChangeTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ContractorPrintFormChangeRequestService $changes,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! RoleAccess::canAccessVisibilityArea($user, 'contractors')) {
                return Response::error('Нет доступа к контрагентам.');
            }

            $validated = $request->validate([
                'contractor_id' => ['required', 'integer', 'min:1'],
                'party' => ['required', 'string', Rule::in([
                    PrintFormBasicTerm::PARTY_CUSTOMER,
                    PrintFormBasicTerm::PARTY_CARRIER,
                ])],
                'items' => ['required', 'array', 'min:1'],
                'items.*' => ['required', 'string', 'max:8000'],
                'manager_notes' => ['nullable', 'string', 'max:5000'],
                'yurik_summary' => ['nullable', 'string', 'max:10000'],
            ]);

            $contractor = Contractor::query()->find((int) $validated['contractor_id']);

            if ($contractor === null) {
                return Response::error('Контрагент не найден.');
            }

            try {
                $change = $this->changes->submitBasicTermsChange(
                    $contractor,
                    (string) $validated['party'],
                    $validated['items'],
                    $user,
                    $validated['manager_notes'] ?? null,
                    $validated['yurik_summary'] ?? null,
                );
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();

                return Response::error(is_string($message) ? $message : 'Ошибка валидации.');
            }

            return Response::json([
                'change_request' => $this->changes->serializeRequest($change),
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contractor_id' => $schema->integer()->min(1)->required(),
            'party' => $schema->string()->enum(['customer', 'carrier'])->required(),
            'items' => $schema->array()->items($schema->string())->required(),
            'manager_notes' => $schema->string()->max(5000),
            'yurik_summary' => $schema->string()->max(10000)->description('Краткое заключение Юрика для руководителя.'),
        ];
    }
}
