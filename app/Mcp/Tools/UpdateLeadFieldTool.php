<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\LeadMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_lead_field')]
#[Description('Изменить одно поле лида (whitelist): title, description, source, transport_type, next_contact_at, status (без won/lost), loading/unloading_location, target_price/currency. Нужен mcp:write.')]
class UpdateLeadFieldTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly LeadMcpService $leads,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'lead_id' => ['required', 'integer', 'min:1'],
                'field' => ['required', 'string', 'in:'.implode(',', LeadMcpService::UPDATABLE_FIELDS)],
                'value' => ['nullable'],
            ]);

            try {
                $result = $this->leads->updateField(
                    $user,
                    (int) $validated['lead_id'],
                    (string) $validated['field'],
                    $validated['value'] ?? null,
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
            'lead_id' => $schema->integer()
                ->description('ID лида.')
                ->min(1)
                ->required(),
            'field' => $schema->string()
                ->description('Ключ поля: '.implode(', ', LeadMcpService::UPDATABLE_FIELDS).'.')
                ->enum(LeadMcpService::UPDATABLE_FIELDS)
                ->required(),
            'value' => $schema->string()
                ->description('Новое значение. null или пустая строка — очистить (где допустимо).'),
        ];
    }
}
