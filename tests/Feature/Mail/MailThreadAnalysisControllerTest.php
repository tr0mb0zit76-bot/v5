<?php

namespace Tests\Feature\Mail;

use App\Models\CommercialAiSuggestionLog;
use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\MailThreadAnalysisService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailThreadAnalysisControllerTest extends TestCase
{
    private function mailUser(): User
    {
        $role = Role::query()->create([
            'name' => 'mail_ai_operator',
            'visibility_areas' => ['mail', 'leads'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'email' => 'ai-manager@example.com',
        ]);
    }

    #[Test]
    public function user_can_summarize_accessible_thread_and_submit_feedback(): void
    {
        $user = $this->mailUser();

        $thread = MailThread::query()->create([
            'subject' => 'Ставка',
            'mailbox_user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        $analysis = Mockery::mock(MailThreadAnalysisService::class);
        $analysis->shouldReceive('summarizeThread')
            ->once()
            ->with($user, $thread->id, 20)
            ->andReturn([
                'thread_id' => $thread->id,
                'summary' => 'Клиент ждёт ставку.',
                'key_points' => ['Срок до пятницы'],
            ]);
        $this->app->instance(MailThreadAnalysisService::class, $analysis);

        $response = $this->actingAs($user)->postJson(route('mail.threads.ai.summarize', $thread));

        $response->assertOk();
        $response->assertJsonPath('summary', 'Клиент ждёт ставку.');
        $suggestionKey = (string) $response->json('suggestion_key');
        $this->assertNotSame('', $suggestionKey);

        $feedbackResponse = $this->actingAs($user)->postJson(route('mail.ai.feedback'), [
            'suggestion_key' => $suggestionKey,
            'rating' => 'helpful',
        ]);

        $feedbackResponse->assertOk();

        $log = CommercialAiSuggestionLog::query()->where('suggestion_key', $suggestionKey)->first();
        $this->assertNotNull($log);
        $this->assertSame('helpful', $log->rating);
    }

    #[Test]
    public function guest_cannot_use_mail_ai_endpoints(): void
    {
        $thread = MailThread::query()->create([
            'subject' => 'Test',
            'last_message_at' => now(),
        ]);

        $this->postJson(route('mail.threads.ai.summarize', $thread))->assertUnauthorized();
    }
}
