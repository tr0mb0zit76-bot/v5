<?php

namespace App\Support;

use App\Models\PrintFormBasicTerm;

/**
 * Контекст генерации печатной формы заказа (плечо маршрута, перевозчик, режим таблицы плеч).
 */
final readonly class OrderPrintFormContext
{
    public function __construct(
        public ?string $legStage = null,
        public ?int $carrierContractorId = null,
        public bool $routeLegsAsTableRows = false,
        public ?string $printParty = null,
        public ?int $carrierSlot = null,
        public ?string $documentVerificationCode = null,
        public ?int $orderDocumentId = null,
        public bool $intercompanySubcontract = false,
    ) {}

    public static function forCustomerLeg(string $legStage): self
    {
        return new self(legStage: $legStage);
    }

    public static function forCarrierContractor(int $contractorId, bool $routeLegsAsTableRows = false): self
    {
        return new self(
            carrierContractorId: $contractorId,
            routeLegsAsTableRows: $routeLegsAsTableRows,
        );
    }

    public static function forCarrierSingleContractorMultiLeg(int $contractorId): self
    {
        return new self(
            carrierContractorId: $contractorId,
            routeLegsAsTableRows: true,
        );
    }

    /**
     * Межфирменная заявка: печати 2-й руки, контрагент = 1-я рука, сумма = customer_rate × 0.95.
     */
    public static function forIntercompanySubcontract(): self
    {
        return new self(
            printParty: PrintFormBasicTerm::PARTY_CARRIER,
            intercompanySubcontract: true,
        );
    }

    /**
     * Предпросмотр шаблона в мастере/настройках: QR рисуется, но ссылка не ведёт на реальный документ.
     */
    public static function forTemplatePreview(int $orderId): self
    {
        $suffix = str_pad((string) max(1, $orderId), 10, '0', STR_PAD_LEFT);

        return new self(
            documentVerificationCode: 'PREVIEW'.$suffix,
            orderDocumentId: max(1, $orderId),
        );
    }
}
