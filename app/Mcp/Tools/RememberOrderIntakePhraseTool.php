<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderIntakeMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('remember_order_intake_phrase')]
#[Description('Запомнить формулировку пользователя для распознавания заявок (после уточнения в диалоге).')]
class RememberOrderIntakePhraseTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderIntakeMcpService $intake,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'source_phrase' => ['required', 'string', 'min:2', 'max:255'],
                'canonical_value' => ['required', 'string', 'min:1', 'max:255'],
                'field' => ['required', 'string', 'in:payment_terms,own_company,general'],
            ]);

            $result = $this->intake->rememberPhrase(
                $user,
                (string) $validated['source_phrase'],
                (string) $validated['canonical_value'],
                (string) $validated['field'],
            );

            if (! ($result['ok'] ?? false)) {
                return Response::error((string) ($result['message'] ?? 'Не удалось сохранить фразу.'));
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
            'source_phrase' => $schema->string()
                ->description('Как пользователь сформулировал (например «оплата через месяц»).')
                ->min(2)
                ->max(255)
                ->required(),
            'canonical_value' => $schema->string()
                ->description('Как понимать в CRM (например «30 календарных дней» или «Автоальянс»).')
                ->min(1)
                ->max(255)
                ->required(),
            'field' => $schema->string()
                ->description('payment_terms | own_company | general')
                ->enum(['payment_terms', 'own_company', 'general'])
                ->required(),
        ];
    }
}
