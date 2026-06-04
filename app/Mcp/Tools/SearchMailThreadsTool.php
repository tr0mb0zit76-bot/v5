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
#[Description('Поиск цепочек почты (входящие/исходящие из IMAP sync): тема, текст, email.')]
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
                'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            ]);

            $result = $this->mail->searchThreads(
                $user,
                (string) ($validated['query'] ?? ''),
                (int) ($validated['limit'] ?? 15),
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
                ->description('Фрагмент темы, текста письма или email. Пусто — последние цепочки.')
                ->max(500),
            'limit' => $schema->integer()
                ->description('1–25, по умолчанию 15.')
                ->min(1)
                ->max(25),
        ];
    }
}
