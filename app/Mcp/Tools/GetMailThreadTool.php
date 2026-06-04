<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\MailMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_mail_thread')]
#[Description('Цепочка писем и последние сообщения по thread_id (из search_mail_threads).')]
class GetMailThreadTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly MailMcpService $mail,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'thread_id' => ['required', 'integer', 'min:1'],
                'message_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            try {
                $result = $this->mail->getThread(
                    $user,
                    (int) $validated['thread_id'],
                    (int) ($validated['message_limit'] ?? 20),
                );
            } catch (ModelNotFoundException) {
                return Response::error('Цепочка писем не найдена.');
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
            'thread_id' => $schema->integer()
                ->description('ID цепочки из search_mail_threads.')
                ->min(1)
                ->required(),
            'message_limit' => $schema->integer()
                ->description('Сколько последних писем вернуть (1–50, по умолчанию 20).')
                ->min(1)
                ->max(50),
        ];
    }
}
