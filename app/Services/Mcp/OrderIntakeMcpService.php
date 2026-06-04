<?php

namespace App\Services\Mcp;

use App\Models\OrderIntakeDraft;
use App\Models\User;
use App\Services\OrderIntakeLearnedPhrasesService;
use App\Services\Orders\OrderDocumentIntakeService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

class OrderIntakeMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly OrderDocumentIntakeService $intakeExtractor,
        private readonly OrderIntakeLearnedPhrasesService $learnedPhrases,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createDraftFromText(User $user, string $instruction): array
    {
        $this->access->requireOrdersArea($user);

        return $this->intakeExtractor->extractFromText($user, $instruction, 'mcp:instruction');
    }

    /**
     * @return array{ok: bool, id: int, message: string}
     */
    public function rememberPhrase(User $user, string $sourcePhrase, string $canonicalValue, string $field): array
    {
        $this->access->requireOrdersArea($user);

        return $this->learnedPhrases->remember($user, $sourcePhrase, $canonicalValue, $field);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDraft(User $user, int $draftId): array
    {
        $this->access->requireOrdersArea($user);

        if (! Schema::hasTable('order_intake_drafts')) {
            throw new ModelNotFoundException('Таблица черновиков заявок недоступна.');
        }

        $draft = OrderIntakeDraft::query()->findOrFail($draftId);

        if ((int) $draft->user_id !== (int) $user->id && ! $this->canViewOtherUsersDrafts($user)) {
            throw new AuthenticationException('Нет доступа к этому черновику заявки.');
        }

        return $this->serializeDraft($draft);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentDrafts(User $user, int $limit = 10): array
    {
        $this->access->requireOrdersArea($user);

        if (! Schema::hasTable('order_intake_drafts')) {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $query = OrderIntakeDraft::query()
            ->orderByDesc('id')
            ->limit($limit);

        if (! $this->canViewOtherUsersDrafts($user)) {
            $query->where('user_id', $user->id);
        }

        return $query
            ->get()
            ->map(fn (OrderIntakeDraft $draft): array => $this->serializeDraftSummary($draft))
            ->all();
    }

    private function canViewOtherUsersDrafts(User $user): bool
    {
        return RoleAccess::canAccessSettingsSystem($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDraft(OrderIntakeDraft $draft): array
    {
        return [
            'draft_id' => $draft->id,
            'user_id' => $draft->user_id,
            'order_id' => $draft->order_id,
            'source_original_name' => $draft->source_original_name,
            'confidence' => $draft->confidence,
            'warnings' => $draft->warnings ?? [],
            'wizard_patch' => $draft->wizard_patch ?? [],
            'matched_contractors' => $draft->matched_contractors ?? [],
            'extracted' => $draft->extracted_payload ?? [],
            'created_at' => optional($draft->created_at)?->toIso8601String(),
            'note' => 'Черновик заявки: из файла (POST /orders/intake/extract) или из текста (create_order_intake_draft_from_text). Откройте мастер заказа и примените по draft_id.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDraftSummary(OrderIntakeDraft $draft): array
    {
        return [
            'draft_id' => $draft->id,
            'user_id' => $draft->user_id,
            'source_original_name' => $draft->source_original_name,
            'confidence' => $draft->confidence,
            'summary' => $this->draftSummaryLine($draft),
            'wizard_patch' => $draft->wizard_patch ?? [],
            'matched_contractors' => $draft->matched_contractors ?? [],
            'created_at' => optional($draft->created_at)?->toIso8601String(),
        ];
    }

    private function draftSummaryLine(OrderIntakeDraft $draft): string
    {
        $patch = is_array($draft->wizard_patch) ? $draft->wizard_patch : [];
        $parts = [];

        $points = is_array($patch['route_points'] ?? null) ? $patch['route_points'] : [];
        $loading = collect($points)->firstWhere('type', 'loading');
        $unloading = collect($points)->firstWhere('type', 'unloading');

        if (is_array($loading) && filled($loading['address'] ?? null)) {
            $parts[] = 'погрузка: '.$loading['address'];
        }

        if (is_array($unloading) && filled($unloading['address'] ?? null)) {
            $parts[] = 'выгрузка: '.$unloading['address'];
        }

        $cargo = is_array($patch['cargo_items'] ?? null) ? ($patch['cargo_items'][0] ?? null) : null;

        if (is_array($cargo) && filled($cargo['name'] ?? null)) {
            $parts[] = 'груз: '.$cargo['name'];
        }

        return $parts !== [] ? implode(' · ', $parts) : (string) $draft->source_original_name;
    }
}
