<?php

namespace App\Services\LoadBoard;

use App\Models\LoadBoardPost;
use App\Models\Order;
use App\Models\ProcurementCase;
use Illuminate\Support\Facades\Schema;

class ProcurementCaseSyncService
{
    public function __construct(
        private readonly ProcurementCaseLinkService $links,
    ) {}

    public function ensureForPost(LoadBoardPost $post): ?ProcurementCase
    {
        if (! Schema::hasTable('procurement_cases')) {
            return null;
        }

        $post->loadMissing(['order:id,order_owner_id,manager_id,dispatcher_id,own_company_id,order_number', 'lead:id,number,title']);

        $existing = ProcurementCase::query()
            ->where('load_board_post_id', $post->id)
            ->first();

        if ($existing instanceof ProcurementCase) {
            return $this->syncExistingCase($existing, $post);
        }

        $order = $post->order;
        $linkMetadata = $this->links->bootstrapLinksMetadata($post);

        return ProcurementCase::query()->create([
            'load_board_post_id' => $post->id,
            'lead_id' => $post->lead_id,
            'order_id' => $post->order_id,
            'order_owner_id' => $this->resolveOrderOwnerId($post, $order),
            'buyer_id' => $post->buyer_id,
            'dispatcher_id' => $this->resolveDispatcherId($order),
            'buying_own_company_id' => $order?->own_company_id,
            'status' => $post->status,
            'metadata' => [
                'source' => 'load_board_post',
                'post_id' => $post->id,
                ...$linkMetadata,
            ],
        ]);
    }

    public function syncPostStatus(LoadBoardPost $post): void
    {
        if (! Schema::hasTable('procurement_cases')) {
            return;
        }

        ProcurementCase::query()
            ->where('load_board_post_id', $post->id)
            ->update([
                'status' => $post->status,
                'buyer_id' => $post->buyer_id,
            ]);
    }

    public function caseForPost(LoadBoardPost $post): ?ProcurementCase
    {
        if (! Schema::hasTable('procurement_cases')) {
            return null;
        }

        return ProcurementCase::query()
            ->where('load_board_post_id', $post->id)
            ->first();
    }

    private function syncExistingCase(ProcurementCase $case, LoadBoardPost $post): ProcurementCase
    {
        $order = $post->order;

        $case->forceFill([
            'lead_id' => $post->lead_id ?? $case->lead_id,
            'order_id' => $post->order_id ?? $case->order_id,
            'order_owner_id' => $this->resolveOrderOwnerId($post, $order) ?? $case->order_owner_id,
            'buyer_id' => $post->buyer_id,
            'dispatcher_id' => $this->resolveDispatcherId($order) ?? $case->dispatcher_id,
            'buying_own_company_id' => $order?->own_company_id ?? $case->buying_own_company_id,
            'status' => $post->status,
        ])->save();

        return $case->fresh();
    }

    private function resolveOrderOwnerId(LoadBoardPost $post, ?Order $order): ?int
    {
        if ($post->seller_id !== null) {
            return (int) $post->seller_id;
        }

        if ($order === null) {
            return null;
        }

        if (Schema::hasColumn('orders', 'order_owner_id') && $order->order_owner_id !== null) {
            return (int) $order->order_owner_id;
        }

        return $order->manager_id !== null ? (int) $order->manager_id : null;
    }

    private function resolveDispatcherId(?Order $order): ?int
    {
        if ($order === null || ! Schema::hasColumn('orders', 'dispatcher_id')) {
            return null;
        }

        return $order->dispatcher_id !== null ? (int) $order->dispatcher_id : null;
    }
}
