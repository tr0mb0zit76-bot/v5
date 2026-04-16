<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentScheduleController extends Controller
{
    /**
     * Record a payment for a payment schedule item.
     */
    public function recordPayment(Request $request, PaymentSchedule $paymentSchedule)
    {
        $this->ensureCanManagePaymentSchedule($request);

        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'transaction_reference' => 'nullable|string|max:100',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $paidAmount = (float) $validated['paid_amount'];
            $remainingAmount = $paymentSchedule->remaining_amount > 0
                ? $paymentSchedule->remaining_amount
                : $paymentSchedule->amount;

            // Если это первый платеж
            if ($paymentSchedule->paid_amount == 0) {
                $paymentSchedule->paid_amount = $paidAmount;
                $paymentSchedule->remaining_amount = $remainingAmount - $paidAmount;
                $paymentSchedule->actual_date = $validated['payment_date'];
                $paymentSchedule->payment_method = $validated['payment_method'];
                $paymentSchedule->transaction_reference = $validated['transaction_reference'];

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
                // Создаем запись о частичном платеже
                $partialPayment = new PaymentSchedule;
                $partialPayment->order_id = $paymentSchedule->order_id;
                $partialPayment->party = $paymentSchedule->party;
                $partialPayment->type = $paymentSchedule->type;
                $partialPayment->amount = $paidAmount;
                $partialPayment->paid_amount = $paidAmount;
                $partialPayment->remaining_amount = 0;
                $partialPayment->planned_date = $validated['payment_date'];
                $partialPayment->actual_date = $validated['payment_date'];
                $partialPayment->status = 'paid';
                $partialPayment->payment_method = $validated['payment_method'];
                $partialPayment->transaction_reference = $validated['transaction_reference'];
                $partialPayment->parent_payment_id = $paymentSchedule->id;
                $partialPayment->is_partial = true;
                $partialPayment->counterparty_id = $paymentSchedule->counterparty_id;
                $partialPayment->notes = 'Частичный платеж: '.($validated['notes'] ?? '');
                $partialPayment->save();

                // Обновляем основной платеж
                $paymentSchedule->paid_amount += $paidAmount;
                $paymentSchedule->remaining_amount = max(0, $remainingAmount - $paymentSchedule->paid_amount);

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
     * Get partial payments for a payment schedule item.
     */
    public function getPartialPayments(PaymentSchedule $paymentSchedule)
    {
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
