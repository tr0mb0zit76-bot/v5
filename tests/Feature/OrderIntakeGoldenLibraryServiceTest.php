<?php

namespace Tests\Feature;

use App\Enums\OrderIntakeGoldenRecordStatus;
use App\Models\Order;
use App\Models\OrderIntakeDraft;
use App\Models\OrderIntakeGoldenRecord;
use App\Models\User;
use App\Services\OrderIntakeGoldenLibraryService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OrderIntakeGoldenLibraryServiceTest extends TestCase
{
    private OrderIntakeGoldenLibraryService $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->library = app(OrderIntakeGoldenLibraryService::class);
    }

    public function test_open_pending_creates_record_with_proposed_snapshot(): void
    {
        $user = User::factory()->create();
        $draft = $this->makeDraft($user);

        $this->library->openPendingForDraft(
            $user,
            $draft,
            'Перевозка Москва — Казань, оплата 30 дней',
            'text',
            ['customer' => ['name' => 'ООО Тест']],
            ['route_points' => []],
            [],
        );

        $record = OrderIntakeGoldenRecord::query()->where('order_intake_draft_id', $draft->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(OrderIntakeGoldenRecordStatus::Pending, $record->status);
        $this->assertStringContainsString('Москва', (string) $record->user_instruction);
        $this->assertSame(['route_points' => []], $record->proposed_snapshot['wizard_patch'] ?? null);
    }

    public function test_dialog_learning_from_cache_merges_on_open_pending(): void
    {
        $user = User::factory()->create();
        Cache::put('order_intake_dialog_learnings:'.$user->id, [[
            'source_phrase' => 'через месяц',
            'canonical_value' => '30 календарных дней',
            'field' => 'payment_terms',
            'recorded_at' => now()->toIso8601String(),
        ]], 3600);

        $draft = $this->makeDraft($user);

        $this->library->openPendingForDraft($user, $draft, 'Текст заявки', 'mcp', [], [], []);

        $record = OrderIntakeGoldenRecord::query()->where('order_intake_draft_id', $draft->id)->first();

        $this->assertCount(1, $record?->dialog_learnings ?? []);
        $this->assertNull(Cache::get('order_intake_dialog_learnings:'.$user->id));
    }

    public function test_remember_phrase_appends_to_pending_record(): void
    {
        $user = User::factory()->create();
        $draft = $this->makeDraft($user);

        $this->library->openPendingForDraft($user, $draft, 'Заявка', 'text', [], [], []);
        $this->library->activateDraft($user, $draft->id);

        $result = $this->library->recordDialogLearning(
            $user,
            'наша компания Автоальянс',
            'Автоальянс',
            'own_company',
        );

        $this->assertTrue($result['ok']);
        $record = OrderIntakeGoldenRecord::query()->where('order_intake_draft_id', $draft->id)->first();
        $this->assertCount(1, $record?->dialog_learnings ?? []);
    }

    public function test_commit_moves_record_to_library_and_links_order(): void
    {
        $user = User::factory()->create();
        $draft = $this->makeDraft($user);

        $this->library->openPendingForDraft($user, $draft, 'Заявка', 'text', [], ['client_id' => 1], []);

        $order = Order::factory()->create();
        $committed = $this->library->commit($user, $draft->id, $order->id, ['client_id' => 99, 'order_number' => 'TEST-1']);

        $this->assertTrue($committed);
        $record = OrderIntakeGoldenRecord::query()->where('order_intake_draft_id', $draft->id)->first();
        $this->assertSame(OrderIntakeGoldenRecordStatus::Committed, $record?->status);
        $this->assertSame($order->id, $record?->order_id);
        $this->assertSame(99, $record?->applied_snapshot['client_id'] ?? null);
        $this->assertSame($order->id, $draft->fresh()?->order_id);
    }

    public function test_discard_removes_pending_record(): void
    {
        $user = User::factory()->create();
        $draft = $this->makeDraft($user);

        $this->library->openPendingForDraft($user, $draft, 'Заявка', 'text', [], [], []);

        $this->assertTrue($this->library->discard($user, $draft->id));
        $this->assertNull(OrderIntakeGoldenRecord::query()->where('order_intake_draft_id', $draft->id)->first());
    }

    public function test_new_pending_discards_other_pending_for_same_user(): void
    {
        $user = User::factory()->create();
        $oldDraft = $this->makeDraft($user);
        $newDraft = $this->makeDraft($user);

        $this->library->openPendingForDraft($user, $oldDraft, 'Старая', 'text', [], [], []);
        $this->library->openPendingForDraft($user, $newDraft, 'Новая', 'text', [], [], []);

        $this->assertNull(OrderIntakeGoldenRecord::query()->where('order_intake_draft_id', $oldDraft->id)->first());
        $this->assertNotNull(OrderIntakeGoldenRecord::query()->where('order_intake_draft_id', $newDraft->id)->first());
    }

    private function makeDraft(User $user): OrderIntakeDraft
    {
        return OrderIntakeDraft::query()->create([
            'user_id' => $user->id,
            'source_original_name' => 'test.txt',
            'source_text_hash' => hash('sha256', 'sample'),
            'source_text_length' => 6,
            'extracted_payload' => [],
            'wizard_patch' => [],
            'warnings' => [],
            'matched_contractors' => [],
        ]);
    }
}
