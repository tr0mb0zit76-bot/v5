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

#[Name('extract_order_draft_from_document')]
#[Description('Извлечь черновик заявки из файла (PDF/DOCX/изображение). content_base64 + file_name.')]
class ExtractOrderDraftFromDocumentTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly OrderIntakeMcpService $intake,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'file_name' => ['required', 'string', 'max:255'],
                'content_base64' => ['required', 'string', 'min:16'],
                'mime_type' => ['nullable', 'string', 'max:120'],
            ]);

            try {
                $result = $this->intake->extractDraftFromDocument(
                    $user,
                    (string) $validated['file_name'],
                    (string) $validated['content_base64'],
                    (string) ($validated['mime_type'] ?? 'application/octet-stream'),
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
            'file_name' => $schema->string()->description('Имя файла, напр. zayavka.pdf')->max(255)->required(),
            'content_base64' => $schema->string()->description('Содержимое файла в base64.')->required(),
            'mime_type' => $schema->string()->description('MIME, напр. application/pdf')->max(120),
        ];
    }
}
