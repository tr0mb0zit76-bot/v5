<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\LeadMcpService;
use App\Support\LeadStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search_leads')]
#[Description('Поиск лидов по номеру, id, заголовку, имени контрагента или ответственного. Учитывает scope раздела «Лиды».')]
class SearchLeadsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly LeadMcpService $leads,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'query' => ['nullable', 'string', 'max:120'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
                'status' => ['nullable', 'string', 'in:'.implode(',', LeadStatus::values())],
            ]);

            try {
                $result = $this->leads->search(
                    $user,
                    (string) ($validated['query'] ?? ''),
                    (int) ($validated['limit'] ?? 15),
                    isset($validated['status']) ? (string) $validated['status'] : null,
                );
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();

                return Response::error(is_string($message) ? $message : 'Ошибка валидации.');
            }

            return Response::json($result);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Номер, id, заголовок, контрагент или ответственный. Пустая строка — последние лиды в пределах лимита.')
                ->max(120),
            'limit' => $schema->integer()
                ->description('Максимум записей (1–25).')
                ->min(1)
                ->max(25),
            'status' => $schema->string()
                ->description('Фильтр по статусу: new, qualification, calculation, proposal_ready, proposal_sent, negotiation, won, lost, on_hold.')
                ->enum(LeadStatus::values()),
        ];
    }
}
