<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Колонки сквозного pipeline для заказов (read-model).
 */
enum EndToEndOrderPipelineColumn: string
{
    case Preparation = 'order_preparation';
    case InTransit = 'in_transit';
    case Documents = 'documents';
    case Payment = 'payment';
    case Closed = 'closed';
    case AccountingHandoff = 'accounting_handoff';
    case Disruption = 'disruption';

    public function label(): string
    {
        return match ($this) {
            self::Preparation => 'Подготовка',
            self::InTransit => 'В пути',
            self::Documents => 'Документы',
            self::Payment => 'Оплаты',
            self::Closed => 'Закрыт',
            self::AccountingHandoff => 'Принято бухгалтерией',
            self::Disruption => 'Срыв / отмена',
        };
    }

    /**
     * @return list<self>
     */
    public static function boardOrder(): array
    {
        return [
            self::Preparation,
            self::InTransit,
            self::Documents,
            self::Payment,
            self::Closed,
            self::AccountingHandoff,
            self::Disruption,
        ];
    }
}
