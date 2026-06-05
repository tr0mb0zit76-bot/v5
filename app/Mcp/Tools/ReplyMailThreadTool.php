<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\MailMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('reply_mail_thread')]
#[Description('Ответить в существующую цепочку писем (thread_id из search_mail_threads / get_mail_thread).')]
class ReplyMailThreadTool extends Tool
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
                'body' => ['required', 'string', 'max:20000'],
                'to' => ['nullable', 'array', 'min:1'],
                'to.*' => ['required', 'email'],
                'cc' => ['nullable', 'array'],
                'cc.*' => ['email'],
            ]);

            try {
                $result = $this->mail->replyToThread(
                    $user,
                    (int) $validated['thread_id'],
                    (string) $validated['body'],
                    $validated['to'] ?? null,
                    $validated['cc'] ?? [],
                );
            } catch (ModelNotFoundException) {
                return Response::error('Цепочка писем не найдена.');
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
            'thread_id' => $schema->integer()->description('ID цепочки.')->min(1)->required(),
            'body' => $schema->string()->description('Текст ответа.')->max(20000)->required(),
            'to' => $schema->array()
                ->description('Получатели (если не указано — из последнего входящего).')
                ->items($schema->string()),
            'cc' => $schema->array()->description('Копия.')->items($schema->string()),
        ];
    }
}
