<?php

namespace App\Support;

use Illuminate\Support\Collection;

class OrderGridOneCSummaryResolver
{
    public function __construct(
        private readonly OrderFleetTransportDetailsResolver $transportDetails,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function enrich(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        return $rows->map(function (array $row): array {
            $transport = $this->transportDetails->resolveForGridRow($row);

            $summary = OrderClipboardSummaryFormatter::format(
                isset($row['company_code']) ? (string) $row['company_code'] : null,
                isset($row['customer_name']) ? (string) $row['customer_name'] : null,
                isset($row['order_number']) ? (string) $row['order_number'] : null,
                $row['order_date'] ?? null,
                $row['customer_rate'] ?? null,
                isset($row['customer_payment_form']) ? (string) $row['customer_payment_form'] : null,
                isset($row['loading_point']) ? (string) $row['loading_point'] : null,
                isset($row['last_unloading_point']) ? (string) $row['last_unloading_point'] : null,
                $transport['tractor_brand'],
                $transport['tractor_plate'],
                $transport['trailer_brand'],
                $transport['trailer_plate'],
                $transport['driver_name'],
            );

            $row['one_c_summary'] = $summary;
            $row['clipboard_summary'] = $summary;

            unset($row['performers']);

            return $row;
        });
    }
}
