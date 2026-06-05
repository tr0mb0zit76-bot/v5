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

#[Name('create_fleet_driver')]
#[Description('Создать водителя (модалка «Водитель» в заказе/флоте). Требует carrier_contractor_id перевозчика и ФИО.')]
class CreateFleetDriverTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly FleetMcpService $fleet,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'carrier_contractor_id' => ['required', 'integer', 'min:1'],
                'full_name' => ['required', 'string', 'max:255'],
                'passport_series' => ['nullable', 'string', 'max:16'],
                'passport_number' => ['nullable', 'string', 'max:32'],
                'passport_issued_by' => ['nullable', 'string', 'max:500'],
                'passport_issued_at' => ['nullable', 'date'],
                'phone' => ['nullable', 'string', 'max:50'],
                'license_number' => ['nullable', 'string', 'max:64'],
                'license_categories' => ['nullable', 'string', 'max:64'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ]);

            try {
                $result = $this->fleet->createDriver($user, $validated);
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
            'carrier_contractor_id' => $schema->integer()
                ->description('ID контрагента-перевозчика.')
                ->min(1)
                ->required(),
            'full_name' => $schema->string()
                ->description('ФИО водителя.')
                ->max(255)
                ->required(),
            'passport_series' => $schema->string()->max(16),
            'passport_number' => $schema->string()->max(32),
            'passport_issued_by' => $schema->string()->max(500),
            'passport_issued_at' => $schema->string()->format('date'),
            'phone' => $schema->string()->max(50),
            'license_number' => $schema->string()->max(64),
            'license_categories' => $schema->string()->max(64),
            'notes' => $schema->string()->max(5000),
        ];
    }
}
