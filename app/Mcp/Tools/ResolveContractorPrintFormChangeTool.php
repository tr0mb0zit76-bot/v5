<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\ContractorPrintFormChangeRequest;
use App\Models\User;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('resolve_contractor_print_form_change')]
#[Description('Утвердить, отклонить или вернуть на согласование с контрагентом заявку на изменение базовых условий. Для руководителя / receives_approvals.')]
class ResolveContractorPrintFormChangeTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ContractorPrintFormChangeRequestService $changes,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! $this->changes->canApprovePrintFormChanges($user)) {
                throw new AuthenticationException('Нет прав на согласование изменений формы.');
            }

            $validated = $request->validate([
                'change_request_id' => ['required', 'integer', 'min:1'],
                'action' => ['required', 'string', Rule::in(['approve', 'reject', 'needs_counterparty'])],
                'reason' => ['nullable', 'string', 'max:2000'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $changeRequest = ContractorPrintFormChangeRequest::query()->find((int) $validated['change_request_id']);

            if ($changeRequest === null) {
                return Response::error('Заявка не найдена.');
            }

            try {
                $action = (string) $validated['action'];

                if ($action === 'approve') {
                    $changeRequest = $this->changes->approve($changeRequest, $user);
                } elseif ($action === 'reject') {
                    $changeRequest = $this->changes->reject(
                        $changeRequest,
                        $user,
                        (string) ($validated['reason'] ?? 'Без комментария'),
                    );
                } else {
                    $changeRequest = $this->changes->markNeedsCounterparty(
                        $changeRequest,
                        $user,
                        $validated['notes'] ?? null,
                    );
                }
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();

                return Response::error(is_string($message) ? $message : 'Ошибка валидации.');
            }

            return Response::json([
                'change_request' => $this->changes->serializeRequest($changeRequest),
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'change_request_id' => $schema->integer()->min(1)->required(),
            'action' => $schema->string()->enum(['approve', 'reject', 'needs_counterparty'])->required(),
            'reason' => $schema->string()->max(2000)->description('Обязательно при reject.'),
            'notes' => $schema->string()->max(2000),
        ];
    }
}
