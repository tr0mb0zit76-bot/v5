<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\ContractorMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_contractor')]
#[Description('Создать контрагента в CRM. Достаточно type и name; при полном ИНН без названия — автозаполнение из DaData. Владелец — текущий пользователь.')]
class CreateContractorTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly ContractorMcpService $contractors,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'type' => ['nullable', 'string', 'in:customer,carrier,contractor,both'],
                'name' => ['nullable', 'string', 'max:255'],
                'inn' => ['nullable', 'string', 'max:20'],
                'kpp' => ['nullable', 'string', 'max:20'],
                'ogrn' => ['nullable', 'string', 'max:20'],
                'okpo' => ['nullable', 'string', 'max:20'],
                'legal_form' => ['nullable', 'string', 'in:ooo,zao,ao,ip,samozanyaty,other'],
                'full_name' => ['nullable', 'string', 'max:255'],
                'legal_address' => ['nullable', 'string', 'max:255'],
                'actual_address' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'contact_person' => ['nullable', 'string', 'max:255'],
                'autofill_from_inn' => ['nullable', 'boolean'],
            ]);

            try {
                $result = $this->contractors->create($user, $validated);
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
            'type' => $schema->string()
                ->description('Тип: customer (заказчик), carrier (перевозчик), contractor, both. По умолчанию customer.')
                ->enum(['customer', 'carrier', 'contractor', 'both']),
            'name' => $schema->string()
                ->description('Краткое название. Можно не указывать, если передан полный ИНН (10/12 цифр) — подставится из DaData.')
                ->max(255),
            'inn' => $schema->string()
                ->description('ИНН (10 или 12 цифр).')
                ->max(20),
            'kpp' => $schema->string()->max(20),
            'ogrn' => $schema->string()->max(20),
            'okpo' => $schema->string()->max(20),
            'legal_form' => $schema->string()
                ->enum(['ooo', 'zao', 'ao', 'ip', 'samozanyaty', 'other']),
            'full_name' => $schema->string()->max(255),
            'legal_address' => $schema->string()->max(255),
            'actual_address' => $schema->string()->max(255),
            'phone' => $schema->string()->max(50),
            'email' => $schema->string()->format('email')->max(255),
            'contact_person' => $schema->string()->max(255),
            'autofill_from_inn' => $schema->boolean()
                ->description('Подставить реквизиты по ИНН из DaData, если name пустой. По умолчанию true.'),
        ];
    }
}
