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

#[Name('search_mail_threads')]
#[Description('Поиск цепочек почты (IMAP sync): тема, текст, email, ящик сотрудника (mailbox_owner / mailbox_user_id).')]
class SearchMailThreadsTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly MailMcpService $mail,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'query' => ['nullable', 'string', 'max:500'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
                'mailbox_user_id' => ['nullable', 'integer', 'min:1'],
                'mailbox_owner' => ['nullable', 'string', 'max:120'],
            ]);

            $result = $this->mail->searchThreads(
                $user,
                (string) ($validated['query'] ?? ''),
                (int) ($validated['limit'] ?? 15),
                isset($validated['mailbox_user_id']) ? (int) $validated['mailbox_user_id'] : null,
                isset($validated['mailbox_owner']) ? (string) $validated['mailbox_owner'] : null,
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
                ->description('Тема, текст письма, email контрагента. Фамилия сотрудника → фильтр по его ящику (admin). Пусто — последние цепочки.')
                ->max(500),
            'limit' => $schema->integer()
                ->description('1–50, по умолчанию 15.')
                ->min(1)
                ->max(50),
            'mailbox_user_id' => $schema->integer()
                ->description('ID сотрудника-владельца ящика (users.id). Для admin/supervisor.')
                ->min(1),
            'mailbox_owner' => $schema->string()
                ->description('Фрагмент ФИО или email сотрудника — ящик для поиска («Садыков», «ved@…»).')
                ->max(120),
        ];
    }
}
