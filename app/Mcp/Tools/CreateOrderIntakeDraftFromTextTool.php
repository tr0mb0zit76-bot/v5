<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\OrderIntakeMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_order_intake_draft_from_text')]
#[Description('Создать черновик заявки на заказ из текста. Возвращает draft_id, wizard_path и wizard_patch. Откройте мастер по wizard_path.')]
class CreateOrderIntakeDraftFromTextTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderIntakeMcpService $intake,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'instruction' => ['required', 'string', 'min:10', 'max:20000'],
            ]);

            try {
                $result = $this->intake->createDraftFromText($user, (string) $validated['instruction']);
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
            'instruction' => $schema->string()
                ->description('Текст заявки: адреса погрузки/выгрузки, груз, ставка заказчика и перевозчика, НДС, сроки оплаты.')
                ->min(10)
                ->max(20000)
                ->required(),
        ];
    }
}
