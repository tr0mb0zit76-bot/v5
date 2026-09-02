<?php

declare(strict_types=1);

namespace App\Services\Contractor;

use App\Models\Contractor;
use App\Models\User;
use App\Support\OrderViewAuthorization;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Связанные заказы/документы контрагента (бывший DB:: в ContractorController).
 */
final class ContractorRelatedOrdersQuery
{
    /**
     * @return Collection<int, object>
     */
    public function recentOrdersForContractor(Contractor $contractor, ?User $viewer, int $limit = 20): Collection
    {
        $orderSelect = [
            'id',
            'order_number',
            'status',
            'order_date',
            'customer_rate',
            'customer_id',
            'carrier_id',
        ];
        if (Schema::hasColumn('orders', 'carrier_rate')) {
            $orderSelect[] = 'carrier_rate';
        }

        $orderRowsQuery = DB::table('orders')
            ->select($orderSelect)
            ->where(function ($query) use ($contractor): void {
                $query->where('customer_id', $contractor->id)
                    ->orWhere('carrier_id', $contractor->id);
            });

        if ($viewer !== null) {
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery(
                $orderRowsQuery,
                $viewer,
            );
        }

        return $orderRowsQuery
            ->orderByDesc('order_date')
            ->limit($limit)
            ->get();
    }

    public function relatedOrderDocumentsQuery(Contractor $contractor): Builder
    {
        $documentDateColumn = Schema::hasColumn('order_documents', 'document_date');

        return DB::table('order_documents')
            ->join('orders', 'orders.id', '=', 'order_documents.order_id')
            ->select(
                'order_documents.id',
                'order_documents.order_id',
                'order_documents.type',
                'order_documents.document_group',
                'order_documents.number',
                'order_documents.original_name',
                'order_documents.status',
                'order_documents.signature_status',
                'order_documents.file_path',
                'orders.order_number',
                'orders.customer_id',
                'orders.carrier_id',
            )
            ->when(
                $documentDateColumn,
                fn ($query) => $query->addSelect('order_documents.document_date')
            )
            ->where(function ($query) use ($contractor): void {
                $query->where('orders.customer_id', $contractor->id)
                    ->orWhere('orders.carrier_id', $contractor->id);
            })
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at')
            );
    }

    public function contractorHasOrders(Contractor $contractor): bool
    {
        $ordersQuery = DB::table('orders')
            ->where(function ($query) use ($contractor): void {
                $query->where('customer_id', $contractor->id)
                    ->orWhere('carrier_id', $contractor->id);
            });

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $ordersQuery->whereNull('deleted_at');
        }

        return $ordersQuery->exists();
    }
}
