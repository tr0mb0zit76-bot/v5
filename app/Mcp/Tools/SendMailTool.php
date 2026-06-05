<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\User;
use App\Services\Mcp\MailMcpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('send_mail')]
#[Description('Отправить исходящее письмо из CRM (SMTP). Возвращает thread_id и message_id.')]
class SendMailTool extends Tool
{
    use LogsMcpToolCalls;

    public function __construct(
        private readonly MailMcpService $mail,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            $validated = $request->validate([
                'subject' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:20000'],
                'to' => ['required', 'array', 'min:1'],
                'to.*' => ['required', 'email'],
                'cc' => ['nullable', 'array'],
                'cc.*' => ['email'],
                'lead_id' => ['nullable', 'integer', 'min:1'],
                'order_id' => ['nullable', 'integer', 'min:1'],
            ]);

            try {
                $result = $this->mail->sendMail(
                    $user,
                    (string) $validated['subject'],
                    (string) $validated['body'],
                    $validated['to'],
                    $validated['cc'] ?? [],
                    isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
                    isset($validated['order_id']) ? (int) $validated['order_id'] : null,
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
            'subject' => $schema->string()->description('Тема письма.')->max(255)->required(),
            'body' => $schema->string()->description('Текст письма.')->max(20000)->required(),
            'to' => $schema->array()
                ->description('Адреса получателей.')
                ->items($schema->string())
                ->required(),
            'cc' => $schema->array()->description('Копия.')->items($schema->string()),
            'lead_id' => $schema->integer()->description('Привязка к лиду (необязательно).')->min(1),
            'order_id' => $schema->integer()->description('Привязка к заказу (необязательно).')->min(1),
        ];
    }
}
