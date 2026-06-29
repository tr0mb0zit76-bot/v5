<?php

namespace Tests\Feature;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\Contractor;
use App\Models\ContractorInsightDraft;
use App\Models\ContractorPortrait;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Support\ActivityEventType;
use App\Support\ContractorPortraitDictionary;
use Tests\TestCase;

class ContractorInsightDraftTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ChatCompletionClient::class, fn (): ChatCompletionClient => new class implements ChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                return json_encode([
                    [
                        'field_key' => 'success_criteria',
                        'proposed_value' => 'Доставка без срывов сроков',
                        'confidence' => 0.82,
                    ],
                    [
                        'field_key' => 'price_sensitivity',
                        'proposed_value' => 'high',
                        'confidence' => 0.7,
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }
        });
    }

    public function test_extract_from_mail_creates_pending_drafts(): void
    {
        [$user, $contractor, $message] = $this->makeFixtures();

        $response = $this->actingAs($user)->postJson(
            route('contractors.insight-drafts.extract-mail', [$contractor, $message]),
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'drafts');

        $this->assertDatabaseHas('contractor_insight_drafts', [
            'contractor_id' => $contractor->id,
            'field_key' => 'success_criteria',
            'status' => ContractorInsightDraft::STATUS_PENDING,
            'source_type' => ContractorInsightDraft::SOURCE_MAIL_MESSAGE,
            'source_id' => $message->id,
        ]);
    }

    public function test_accept_applies_portrait_and_records_ledger_event(): void
    {
        [$user, $contractor, $message] = $this->makeFixtures();

        ContractorPortrait::query()->create([
            'contractor_id' => $contractor->id,
            'communication_style' => ContractorPortraitDictionary::UNKNOWN,
            'price_sensitivity' => ContractorPortraitDictionary::UNKNOWN,
            'preferred_channel' => ContractorPortraitDictionary::UNKNOWN,
            'decision_cadence' => ContractorPortraitDictionary::UNKNOWN,
            'relationship_trust' => ContractorPortraitDictionary::UNKNOWN,
            'coverage_pct' => 0,
        ]);

        $draft = ContractorInsightDraft::query()->create([
            'contractor_id' => $contractor->id,
            'field_key' => 'success_criteria',
            'proposed_value' => 'Без срывов сроков',
            'source_type' => ContractorInsightDraft::SOURCE_MAIL_MESSAGE,
            'source_id' => $message->id,
            'confidence' => 0.9,
            'status' => ContractorInsightDraft::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('contractors.insight-drafts.accept', [$contractor, $draft]),
        );

        $response->assertOk();
        $response->assertJsonPath('portrait.success_criteria', 'Без срывов сроков');

        $draft->refresh();
        $this->assertSame(ContractorInsightDraft::STATUS_ACCEPTED, $draft->status);
        $this->assertSame($user->id, $draft->reviewed_by);

        $this->assertDatabaseHas('activity_events', [
            'subject_type' => $contractor->getMorphClass(),
            'subject_id' => $contractor->id,
            'event_type' => ActivityEventType::PortraitInsightAccepted->value,
            'source_type' => $draft->getMorphClass(),
            'source_id' => $draft->id,
        ]);
    }

    public function test_reject_marks_draft_without_updating_portrait(): void
    {
        [$user, $contractor, $message] = $this->makeFixtures();

        $draft = ContractorInsightDraft::query()->create([
            'contractor_id' => $contractor->id,
            'field_key' => 'internal_notes',
            'proposed_value' => 'Клиент просит звонить утром',
            'source_type' => ContractorInsightDraft::SOURCE_MAIL_MESSAGE,
            'source_id' => $message->id,
            'status' => ContractorInsightDraft::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('contractors.insight-drafts.reject', [$contractor, $draft]),
        );

        $response->assertOk();
        $response->assertJsonPath('draft.status', ContractorInsightDraft::STATUS_REJECTED);

        $this->assertDatabaseMissing('activity_events', [
            'event_type' => ActivityEventType::PortraitInsightAccepted->value,
        ]);
    }

    /**
     * @return array{0: User, 1: Contractor, 2: MailMessage}
     */
    private function makeFixtures(): array
    {
        $role = Role::query()->create([
            'name' => 'manager-insight-'.uniqid(),
            'visibility_areas' => ['contractors', 'mail'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Manager',
            'email' => 'insight-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Insight',
            'owner_id' => $user->id,
        ]);

        $thread = MailThread::query()->create([
            'contractor_id' => $contractor->id,
            'mailbox_user_id' => $user->id,
            'subject' => 'Ставка',
        ]);

        $message = MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => 'inbound',
            'from_email' => 'client@example.com',
            'to_emails' => ['manager@test.local'],
            'body_text' => 'Нужна доставка без срывов, бюджет ограничен.',
            'subject' => 'Ставка',
            'sent_at' => now(),
        ]);

        return [$user, $contractor, $message];
    }
}
