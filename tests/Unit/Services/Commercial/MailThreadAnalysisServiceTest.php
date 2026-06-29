<?php

namespace Tests\Unit\Services\Commercial;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\Agents\AiRequestGate;
use App\Services\Commercial\MailThreadAnalysisService;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Services\Mcp\MailMcpService;
use App\Services\Mcp\McpAccessGate;
use App\Support\AiChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailThreadAnalysisServiceTest extends TestCase
{
    #[Test]
    public function it_summarizes_mail_thread_via_llm(): void
    {
        $user = $this->makeMailUser();

        $mail = $this->createMock(MailMcpService::class);
        $mail->expects($this->once())
            ->method('getThread')
            ->with($user, 5, 10)
            ->willReturn([
                'thread' => [
                    'thread_id' => 5,
                    'subject' => 'Ставка Москва — Казань',
                    'lead_id' => null,
                    'contractor_id' => 12,
                ],
                'messages' => [
                    [
                        'direction' => 'inbound',
                        'body_text' => 'Нужна доставка до пятницы',
                        'sent_at' => '2026-06-01T10:00:00+00:00',
                        'body_purged' => false,
                    ],
                ],
            ]);

        $chat = new class implements ChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                return json_encode([
                    'summary' => 'Клиент просит доставку до пятницы.',
                    'key_points' => ['Срок — пятница'],
                    'open_questions' => ['Ставка не названа'],
                    'participants' => [
                        ['direction' => 'inbound', 'label' => 'клиент', 'last_at' => '2026-06-01T10:00:00+00:00'],
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }
        };

        $gate = $this->createMock(AiRequestGate::class);
        $gate->method('channelFor')->willReturn(AiChannel::ExternalLarge);

        $service = new MailThreadAnalysisService(
            $mail,
            app(McpAccessGate::class),
            $chat,
            app(ExternalLlmPayloadSanitizer::class),
            $gate,
        );

        $result = $service->summarizeThread($user, 5, 10);

        $this->assertSame(5, $result['thread_id']);
        $this->assertSame('Ставка Москва — Казань', $result['subject']);
        $this->assertStringContainsString('пятниц', mb_strtolower($result['summary']));
        $this->assertSame(['Срок — пятница'], $result['key_points']);
    }

    #[Test]
    public function it_drafts_reply_with_normalized_tone(): void
    {
        $user = $this->makeMailUser();

        $mail = $this->createMock(MailMcpService::class);
        $mail->method('getThread')->willReturn([
            'thread' => ['thread_id' => 7, 'subject' => 'Re: Заявка'],
            'messages' => [
                [
                    'direction' => 'inbound',
                    'body_text' => 'Жду расчёт',
                    'sent_at' => '2026-06-02T09:00:00+00:00',
                    'body_purged' => false,
                ],
            ],
        ]);

        $chat = new class implements ChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                return '{"subject":"Re: Заявка","body":"Добрый день! Подготовим расчёт сегодня."}';
            }
        };

        $gate = $this->createMock(AiRequestGate::class);
        $gate->method('channelFor')->willReturn(AiChannel::ExternalLarge);

        $service = new MailThreadAnalysisService(
            $mail,
            app(McpAccessGate::class),
            $chat,
            app(ExternalLlmPayloadSanitizer::class),
            $gate,
        );

        $result = $service->draftReply($user, 7, 'unknown-tone');

        $this->assertSame('neutral', $result['tone']);
        $this->assertSame('Re: Заявка', $result['subject']);
        $this->assertStringContainsString('расчёт', $result['body']);
    }

    #[Test]
    public function it_suggests_lead_next_step_from_mail_context(): void
    {
        $user = $this->makeMailUser(['mail', 'leads']);

        $lead = Lead::query()->create([
            'number' => 'L-10',
            'status' => 'in_progress',
            'responsible_id' => $user->id,
            'title' => 'Москва — Екатеринбург',
            'lead_qualification' => ['need' => 'FTL срочно'],
        ]);

        $mail = $this->createMock(MailMcpService::class);
        $mail->expects($this->once())
            ->method('getThread')
            ->willReturn([
                'thread' => ['thread_id' => 99, 'subject' => 'Срочная заявка'],
                'messages' => [
                    [
                        'direction' => 'inbound',
                        'body_text' => 'Нужен расчёт сегодня',
                        'sent_at' => '2026-06-03T08:00:00+00:00',
                        'body_purged' => false,
                    ],
                ],
            ]);

        $chat = new class implements ChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                return json_encode([
                    'next_step' => 'Отправить расчёт сегодня до 17:00',
                    'rationale' => 'Клиент ждёт расчёт',
                    'suggested_task_title' => 'Подготовить КП по лиду L-10',
                    'urgency' => 'high',
                ], JSON_UNESCAPED_UNICODE);
            }
        };

        $gate = $this->createMock(AiRequestGate::class);
        $gate->method('channelFor')->willReturn(AiChannel::ExternalLarge);

        $service = new MailThreadAnalysisService(
            $mail,
            app(McpAccessGate::class),
            $chat,
            app(ExternalLlmPayloadSanitizer::class),
            $gate,
        );

        $result = $service->suggestLeadNextStep($user, $lead->id, 99);

        $this->assertSame($lead->id, $result['lead_id']);
        $this->assertSame(99, $result['thread_id']);
        $this->assertSame('high', $result['urgency']);
        $this->assertStringContainsString('расчёт', $result['next_step']);
    }

    /**
     * @param  list<string>  $areas
     */
    private function makeMailUser(array $areas = ['mail']): User
    {
        $roleId = Role::query()->create([
            'name' => 'mail-manager-'.uniqid(),
            'visibility_areas' => $areas,
        ])->id;

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Mail Manager',
            'email' => 'mail-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
