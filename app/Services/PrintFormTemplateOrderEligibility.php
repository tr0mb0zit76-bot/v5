<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintFormTemplate;
use Illuminate\Support\Facades\Schema;

/**
 * Проверяет, можно ли применить шаблон печатной формы к заказу (контрагент, активность, файл).
 */
final class PrintFormTemplateOrderEligibility
{
    /**
     * @return list<int>
     */
    public function contractorIdsForOrder(Order $order): array
    {
        $ids = collect([
            $order->customer_id,
            $order->carrier_id,
            $order->own_company_id,
        ]);

        if ($order->relationLoaded('legs') && Schema::hasTable('leg_contractor_assignments')) {
            foreach ($order->legs as $leg) {
                $cid = $leg->contractorAssignment?->contractor_id;
                if ($cid !== null) {
                    $ids->push($cid);
                }
            }
        }

        return $ids->filter(fn (mixed $value): bool => is_int($value) || ctype_digit((string) $value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    public function isTemplateAvailableForOrder(PrintFormTemplate $template, Order $order): bool
    {
        if (! $template->is_active || blank($template->file_path) || $template->entity_type !== 'order') {
            return false;
        }

        if ($template->contractor_id === null) {
            return true;
        }

        return in_array($template->contractor_id, $this->contractorIdsForOrder($order), true);
    }
}
