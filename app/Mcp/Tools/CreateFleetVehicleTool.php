<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\FleetMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_fleet_vehicle')]
#[Description('Создать авто (модалка «Авто» в заказе/флоте). Требует owner_contractor_id владельца ТС и хотя бы госномер или марку.')]
class CreateFleetVehicleTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly FleetMcpService $fleet,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'owner_contractor_id' => ['required', 'integer', 'min:1'],
                'tractor_brand' => ['nullable', 'string', 'max:120'],
                'trailer_brand' => ['nullable', 'string', 'max:120'],
                'tractor_plate' => ['nullable', 'string', 'max:32'],
                'trailer_plate' => ['nullable', 'string', 'max:32'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ]);

            try {
                $result = $this->fleet->createVehicle($user, $validated);
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
            'owner_contractor_id' => $schema->integer()
                ->description('ID контрагента-владельца ТС (перевозчик).')
                ->min(1)
                ->required(),
            'tractor_brand' => $schema->string()->max(120),
            'trailer_brand' => $schema->string()->max(120),
            'tractor_plate' => $schema->string()->description('Госномер тягача.')->max(32),
            'trailer_plate' => $schema->string()->description('Госномер прицепа.')->max(32),
            'notes' => $schema->string()->max(5000),
        ];
    }
}
