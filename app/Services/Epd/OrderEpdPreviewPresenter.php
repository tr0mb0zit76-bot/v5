<?php

declare(strict_types=1);

namespace App\Services\Epd;

use App\Models\Order;

final class OrderEpdPreviewPresenter
{
    public function __construct(
        private readonly EtrnDraftBuilder $etrnDraftBuilder,
        private readonly ExpeditionReceiptDraftBuilder $expeditionReceiptDraftBuilder,
    ) {}

    /**
     * @return array{etrn: array<string, mixed>, expedition_receipt: array<string, mixed>}|null
     */
    public function forOrder(?Order $order): ?array
    {
        if ($order === null) {
            return null;
        }

        return [
            'etrn' => $this->etrnDraftBuilder->build($order),
            'expedition_receipt' => $this->expeditionReceiptDraftBuilder->build($order),
        ];
    }
}
