<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentScheduleController extends Controller
{
    /**
     * Record a payment for a payment schedule item.
     */
    public function recordPayment(Request $request, PaymentSchedule $paymentSchedule): JsonResponse
    {
        $this->ensureCanManagePaymentSchedule($request);

        if (! Schema::hasColumn('payment_schedules', 'paid_amount')
            || ! Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            return response()->json([
                'success' => false,
                'message' => 'Таблица графика не содержит полей учёта оплат (paid_amount / remaining_amount). Выполните миграции.',
            ], 422);
        }

        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'transaction_reference' => 'nullable|string|max:100',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $paidAmount = (float) $paymentSchedule->paid_amount;
            $remainingTotal = (float) ($paymentSchedule->remaining_amount > 0
                ? $paymentSchedule->remaining_amount
                : $paymentSchedule->amount);
            $incomingPaid = (float) $validated['paid_amount'];

            // Если это первый платеж
            if ($paidAmount <= 0.0) {
                $paymentSchedule->paid_amount = $incomingPaid;
                $paymentSchedule->remaining_amount = max(0, $remainingTotal - $incomingPaid);
                $paymentSchedule->actual_date = $validated['payment_date'];

                if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                    $paymentSchedule->payment_method = $validated['payment_method'];
                }

                if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                    $paymentSchedule->transaction_reference = $validated['transaction_reference'];
                }

                if (! empty($validated['notes'])) {
                    $paymentSchedule->notes = ($paymentSchedule->notes ? $paymentSchedule->notes."\n" : '').
                        'Платеж: '.$validated['notes'];
                }

                // Если оплачена полная сумма
                if ($paymentSchedule->remaining_amount <= 0) {
                    $paymentSchedule->status = 'paid';
                    $paymentSchedule->remaining_amount = 0;

                    // Обновляем статус заказа, если все платежи оплачены
                    $this->updateOrderPaymentStatus($paymentSchedule->order_id);
                } else {
                    $paymentSchedule->status = 'pending';
                }

                $paymentSchedule->save();
            } else {
                if (! Schema::hasColumn('payment_schedules', 'parent_payment_id')
                    || ! Schema::hasColumn('payment_schedules', 'is_partial')) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Для повторных платежей нужны колонки parent_payment_id и is_partial. Выполните миграции.',
                    ], 422);
                }

                // Создаем запись о частичном платеже
                $partialPayment = new PaymentSchedule;
                $partialPayment->order_id = $paymentSchedule->order_id;
                $partialPayment->party = $paymentSchedule->party;
                $partialPayment->type = $paymentSchedule->type;
                $partialPayment->amount = $incomingPaid;
                $partialPayment->paid_amount = $incomingPaid;
                $partialPayment->remaining_amount = 0;
                $partialPayment->planned_date = $validated['payment_date'];
                $partialPayment->actual_date = $validated['payment_date'];
                $partialPayment->status = 'paid';

                if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                    $partialPayment->payment_method = $validated['payment_method'];
                }

                if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                    $partialPayment->transaction_reference = $validated['transaction_reference'];
                }

                $partialPayment->parent_payment_id = $paymentSchedule->id;
                $partialPayment->is_partial = true;

                if (Schema::hasColumn('payment_schedules', 'counterparty_id')) {
                    $partialPayment->counterparty_id = $paymentSchedule->counterparty_id;
                }

                if (Schema::hasColumn('payment_schedules', 'invoice_number')) {
                    $partialPayment->invoice_number = $paymentSchedule->invoice_number;
                }

                $partialPayment->notes = 'Частичный платеж: '.($validated['notes'] ?? '');
                $partialPayment->save();

                // Обновляем основной платеж (остаток = сумма строки минус накопленная оплата)
                $paymentSchedule->paid_amount += $incomingPaid;
                $paymentSchedule->remaining_amount = max(
                    0,
                    (float) $paymentSchedule->amount - (float) $paymentSchedule->paid_amount
                );

                if ($paymentSchedule->remaining_amount <= 0) {
                    $paymentSchedule->status = 'paid';
                    $paymentSchedule->remaining_amount = 0;

                    // Обновляем статус заказа, если все платежи оплачены
                    $this->updateOrderPaymentStatus($paymentSchedule->order_id);
                }

                $paymentSchedule->save();
            }

            DB::commit();

            PaymentScheduleAutomaticStatus::refreshForOrder((int) $paymentSchedule->order_id);

            return response()->json([
                'success' => true,
                'message' => 'Платеж успешно зарегистрирован',
                'payment_schedule' => $paymentSchedule->fresh(),
                'partial_payment' => isset($partialPayment) ? $partialPayment : null,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при регистрации платежа: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update invoice number (for bank statement matching) on a payment schedule row.
     */
    public function updateInvoiceNumber(Request $request, PaymentSchedule $paymentSchedule): JsonResponse
    {
        $this->ensureCanManagePaymentSchedule($request);

        if (! Schema::hasColumn('payment_schedules', 'invoice_number')) {
            return response()->json([
                'success' => false,
                'message' => 'Колонка invoice_number отсутствует. Выполните миграции.',
            ], 422);
        }

        $validated = $request->validate([
            'invoice_number' => 'nullable|string|max:120',
        ]);

        $paymentSchedule->invoice_number = $validated['invoice_number'] ?? null;
        $paymentSchedule->save();

        PaymentScheduleAutomaticStatus::refreshForOrder((int) $paymentSchedule->order_id);

        return response()->json([
            'success' => true,
            'payment_schedule' => $paymentSchedule->fresh(),
        ]);
    }

    /**
     * Get partial payments for a payment schedule item.
     */
    public function getPartialPayments(PaymentSchedule $paymentSchedule): JsonResponse
    {
        if (! Schema::hasColumn('payment_schedules', 'parent_payment_id')
            || ! Schema::hasColumn('payment_schedules', 'is_partial')) {
            return response()->json([
                'success' => true,
                'partial_payments' => [],
            ]);
        }

        $partialPayments = PaymentSchedule::where('parent_payment_id', $paymentSchedule->id)
            ->where('is_partial', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'partial_payments' => $partialPayments,
        ]);
    }

    /**
     * Update order payment status when all payments are completed.
     */
    private function updateOrderPaymentStatus($orderId): void
    {
        if (! Schema::hasColumn('orders', 'payment_status')) {
            return;
        }

        $order = Order::find($orderId);

        if (! $order) {
            return;
        }

        // Проверяем, все ли платежи по заказу оплачены
        $pendingPayments = PaymentSchedule::where('order_id', $orderId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($pendingPayments === 0) {
            // Обновляем статус заказа на "оплачено"
            $order->payment_status = 'paid';
            $order->save();
        }
    }

    /**
     * Cancel a payment schedule item.
     */
    public function cancel(Request $request, PaymentSchedule $paymentSchedule)
    {
        $this->ensureCanManagePaymentSchedule($request);

        $paymentSchedule->status = 'cancelled';
        $paymentSchedule->save();

        PaymentScheduleAutomaticStatus::refreshForOrder((int) $paymentSchedule->order_id);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Платеж отменен',
                'payment_schedule' => $paymentSchedule,
            ]);
        }

        return back()->with('success', 'Платеж отменен');
    }

    /**
     * Restore a cancelled payment schedule item.
     */
    public function restore(Request $request, PaymentSchedule $paymentSchedule)
    {
        $this->ensureCanManagePaymentSchedule($request);

        if ($paymentSchedule->status !== 'cancelled') {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Платеж не отменен',
                ], 400);
            }

            return back()->with('error', 'Платеж не отменен');
        }

        $paymentSchedule->status = 'pending';
        $paymentSchedule->save();

        PaymentScheduleAutomaticStatus::refreshForOrder((int) $paymentSchedule->order_id);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Платеж восстановлен',
                'payment_schedule' => $paymentSchedule,
            ]);
        }

        return back()->with('success', 'Платеж восстановлен');
    }

    private function ensureCanManagePaymentSchedule(Request $request): void
    {
        abort_unless(RoleAccess::canAccessFinanceSalary($request->user()), 403);
    }
}
