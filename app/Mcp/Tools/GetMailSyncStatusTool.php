<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\MailMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_mail_sync_status')]
#[Description('Статус синхронизации почты: последний sync, ошибка IMAP, включена ли синхронизация. Для админов — по всей команде.')]
class GetMailSyncStatusTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly MailMcpService $mail,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user): Response {
            return Response::json($this->mail->syncStatus($user));
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
