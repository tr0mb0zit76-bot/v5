<?php

namespace App\Services\Mcp;

use App\Models\OrderIntakeDraft;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

class OrderIntakeMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
    ) {}

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
            'note' => 'Черновик создан через POST /orders/intake/extract в мастере заказа. Для применения в CRM используйте wizard_patch в UI или создайте заказ вручную.',
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
            'created_at' => optional($draft->created_at)?->toIso8601String(),
        ];
    }
}
