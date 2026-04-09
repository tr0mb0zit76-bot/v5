<?php

namespace App\Support;

/**
 * Статусы согласования печатной заявки (source = print_template).
 */
final class OrderDocumentWorkflowStatus
{
    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const FINALIZED = 'finalized';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DRAFT,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::REJECTED,
            self::FINALIZED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::DRAFT => 'Черновик',
            self::PENDING_APPROVAL => 'На согласовании',
            self::APPROVED => 'Согласовано',
            self::REJECTED => 'Отклонено',
            self::FINALIZED => 'Подписано (PDF)',
        ];
    }

    public static function label(string $value): string
    {
        return self::labels()[$value] ?? $value;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::labels() as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }
}
