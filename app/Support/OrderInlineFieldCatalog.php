<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class OrderInlineFieldCatalog
{
    /**
     * @var list<string>
     */
    public const MANUAL_STATUS_CODES = [
        'new',
        'in_progress',
        'documents',
        'payment',
        'closed',
        'cancelled',
        'disruption',
        'draft',
        'pending',
        'confirmed',
        'completed',
    ];

    /**
     * @var list<string>
     */
    public const ALLOWED_FIELDS = [
        'customer_rate',
        'carrier_rate',
        'additional_expenses',
        'insurance',
        'bonus',
        'invoice_number',
        'upd_number',
        'waybill_number',
        'track_number_customer',
        'track_sent_date_customer',
        'track_received_date_customer',
        'track_number_carrier',
        'track_sent_date_carrier',
        'track_received_date_carrier',
        'customer_payment_form',
        'carrier_payment_form',
        'manual_status',
        'order_date',
    ];

    /**
     * @var list<string>
     */
    public const FINANCIAL_FIELDS = [
        'customer_rate',
        'carrier_rate',
        'additional_expenses',
        'insurance',
        'bonus',
        'customer_payment_form',
        'carrier_payment_form',
    ];

    /**
     * @return list<string>
     */
    public static function allowedFields(): array
    {
        return self::ALLOWED_FIELDS;
    }

    /**
     * @throws ValidationException
     */
    public static function validate(User $user, Order $order, string $field, mixed $value): void
    {
        if (! in_array($field, self::ALLOWED_FIELDS, true)) {
            throw ValidationException::withMessages([
                'field' => 'Поле недоступно для изменения через ассистента.',
            ]);
        }

        if (self::isFinancialField($field) && ! OrderFinancialEditAuthorization::userMayEditFinancialFields($user, $order)) {
            throw ValidationException::withMessages([
                'field' => 'Изменение стоимости и финансовых условий недоступно для заказа в статусе «Выполняется».',
            ]);
        }

        if ($field === 'manual_status') {
            if (! $user->isAdmin() && ! $user->isSupervisor()) {
                throw ValidationException::withMessages([
                    'field' => 'Ручной статус могут менять только руководитель или администратор.',
                ]);
            }

            if ($value === null || $value === '' || $value === 'null') {
                return;
            }

            $code = (string) $value;
            if (! in_array($code, self::MANUAL_STATUS_CODES, true)) {
                throw ValidationException::withMessages([
                    'value' => 'Недопустимое значение ручного статуса.',
                ]);
            }

            if ($code === 'disruption') {
                $validator = validator([]);
                OrderDisruptionGuard::validateMarkDisrupted($user, $order, $validator, 'value');

                if ($validator->errors()->isNotEmpty()) {
                    throw ValidationException::withMessages($validator->errors()->toArray());
                }
            }

            return;
        }

        if (! in_array($field, ['customer_payment_form', 'carrier_payment_form'], true)) {
            return;
        }

        if ($value === null || $value === '' || $value === 'null') {
            return;
        }

        $codes = PaymentFormDictionary::allowedCodesForValidation();
        if (! in_array((string) $value, $codes, true)) {
            throw ValidationException::withMessages([
                'value' => 'Недопустимая форма оплаты.',
            ]);
        }
    }

    /**
     * @return array{field: string, value: mixed}
     */
    public static function normalizePayload(string $field, mixed $value): array
    {
        return [
            'field' => $field,
            'value' => self::normalizeValue($field, $value),
        ];
    }

    public static function normalizeValue(string $field, mixed $value): mixed
    {
        if ($value === '' || $value === 'null') {
            return null;
        }

        if (in_array($field, ['customer_rate', 'carrier_rate', 'additional_expenses', 'insurance', 'bonus'], true)) {
            return $value === null ? null : round((float) $value, 2);
        }

        if (in_array($field, [
            'track_sent_date_customer',
            'track_received_date_customer',
            'track_sent_date_carrier',
            'track_received_date_carrier',
            'order_date',
        ], true)) {
            return blank($value) ? null : $value;
        }

        if (in_array($field, ['customer_payment_form', 'carrier_payment_form'], true)) {
            if (blank($value)) {
                return null;
            }

            return PaymentFormDictionary::normalizeForStorage((string) $value) ?? (string) $value;
        }

        if ($field === 'manual_status') {
            return blank($value) ? null : (string) $value;
        }

        return blank($value) ? null : trim((string) $value);
    }

    public static function isFinancialField(string $field): bool
    {
        return in_array($field, self::FINANCIAL_FIELDS, true);
    }
}
