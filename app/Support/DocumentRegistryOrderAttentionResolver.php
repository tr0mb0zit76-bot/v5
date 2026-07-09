<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Services\OrderDocumentRequirementService;
use Illuminate\Support\Collection;

/**
 * Подсветка заказов в реестре «Документы»: выгрузка проставлена, обязательные документы не закрыты.
 */
final class DocumentRegistryOrderAttentionResolver
{
    public function __construct(
        private readonly OrderDocumentRequirementService $documentRequirementService,
    ) {}

    /**
     * @return array{
     *     missing_documents_after_unloading: bool,
     *     missing_document_labels: list<string>,
     * }
     */
    public function payloadForOrder(Order $order): array
    {
        $milestones = RoutePointActualMilestones::forOrder($order);

        if ($milestones['actual_unloading'] === null) {
            return [
                'missing_documents_after_unloading' => false,
                'missing_document_labels' => [],
            ];
        }

        $missingLabels = $this->missingDocumentLabels($order);

        return [
            'missing_documents_after_unloading' => $missingLabels !== [],
            'missing_document_labels' => $missingLabels,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array{
     *     missing_documents_after_unloading: bool,
     *     missing_document_labels: list<string>,
     * }>
     */
    public function mapForOrders(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $map = [];

        foreach ($orders as $order) {
            $map[(int) $order->id] = $this->payloadForOrder($order);
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function missingDocumentLabels(Order $order): array
    {
        $checklist = $this->documentRequirementService->checklistForOrder($order);

        return collect($checklist)
            ->reject(fn (array $item): bool => (bool) ($item['completed'] ?? false))
            ->pluck('label')
            ->filter(static fn (mixed $label): bool => is_string($label) && trim($label) !== '')
            ->map(static fn (string $label): string => trim($label))
            ->values()
            ->all();
    }
}
