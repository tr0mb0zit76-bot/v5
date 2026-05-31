<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ContractorMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search_contractors')]
#[Description('Поиск контрагентов по названию, ИНН или id. Учитывает видимость справочника.')]
class SearchContractorsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ContractorMcpService $contractors,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'query' => ['nullable', 'string', 'max:120'],
                'type' => ['nullable', 'string', 'in:customer,carrier,contractor,both'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            ]);

            $result = $this->contractors->search(
                $user,
                (string) ($validated['query'] ?? ''),
                (int) ($validated['limit'] ?? 15),
                isset($validated['type']) ? (string) $validated['type'] : null,
            );

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
                ->description('Название, ИНН или id. Пустая строка — первые записи в пределах лимита.')
                ->max(120),
            'type' => $schema->string()
                ->description('Фильтр: customer, carrier, contractor, both.')
                ->enum(['customer', 'carrier', 'contractor', 'both']),
            'limit' => $schema->integer()
                ->description('Максимум записей (1–25).')
                ->min(1)
                ->max(25),
        ];
    }
}
