<?php

namespace App\Support;

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
}
