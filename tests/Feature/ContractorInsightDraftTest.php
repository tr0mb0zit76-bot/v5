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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorInsightDraftTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'activity_events',
            'contractor_insight_drafts',
            'mail_messages',
            'mail_threads',
            'contractor_portraits',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('customer');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_portraits', function (Blueprint $table): void {
            $table->foreignId('contractor_id')->primary()->constrained('contractors')->cascadeOnDelete();
            $table->string('communication_style', 32)->default('unknown');
            $table->string('price_sensitivity', 32)->default('unknown');
            $table->string('preferred_channel', 32)->default('unknown');
            $table->string('decision_cadence', 32)->default('unknown');
            $table->string('relationship_trust', 32)->default('unknown');
            $table->text('success_criteria')->nullable();
            $table->json('typical_objections')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedTinyInteger('coverage_pct')->default(0);
            $table->timestamp('portrait_updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mail_threads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('mailbox_user_id')->nullable();
            $table->string('subject')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_thread_id')->constrained('mail_threads')->cascadeOnDelete();
            $table->string('direction')->default('inbound');
            $table->text('body_text')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_insight_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->json('proposed_value');
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_events', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('event_type');
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });

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
            'body_text' => 'Нужна доставка без срывов, бюджет ограничен.',
            'subject' => 'Ставка',
            'sent_at' => now(),
        ]);

        return [$user, $contractor, $message];
    }
}
