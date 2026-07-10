<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use App\Services\Finance\PaymentSchedulePaymentReversalService;
use App\Support\OrderViewAuthorization;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleSettlementStatus;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentScheduleController extends Controller
{
    public function __construct(
        private readonly PaymentSchedulePaymentLedgerService $paymentLedger,
        private readonly PaymentSchedulePaymentReversalService $paymentReversal,
    ) {}

    /**
     * Record a payment for a payment schedule item.
     */
    public function recordPayment(Request $request, PaymentSchedule $paymentSchedule): JsonResponse
    {
        $this->ensureCanRecordPayment($request, $paymentSchedule);

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
            $remainingTotal = PaymentScheduleSettlementStatus::outstandingAmount(
                (float) $paymentSchedule->amount,
                $paidAmount,
                $paymentSchedule->remaining_amount !== null ? (float) $paymentSchedule->remaining_amount : null,
            );
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
                    $this->clearPaymentRunMark($paymentSchedule);

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
                    $this->clearPaymentRunMark($paymentSchedule);

                    // Обновляем статус заказа, если все платежи оплачены
                    $this->updateOrderPaymentStatus($paymentSchedule->order_id);
                }

                $paymentSchedule->save();
            }

            DB::commit();

            $paymentSchedule->refresh();

            $this->paymentLedger->recordFromPaymentSchedule(
                $paymentSchedule,
                $incomingPaid,
                (string) $validated['payment_date'],
                $validated,
                $request->user()?->id,
                isset($partialPayment) ? (int) $partialPayment->id : null,
            );

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
        $this->ensureCanManagePaymentSchedule($request, $paymentSchedule);

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
     * Mark open schedule rows for a concrete payment run date, or clear that mark.
     */
    public function updatePaymentRun(Request $request): JsonResponse
    {
        abort_unless(RoleAccess::canRecordPaymentOnPaymentSchedule($request->user()), 403);

        foreach (['payment_run_date', 'payment_run_by', 'payment_run_note'] as $column) {
            if (! Schema::hasColumn('payment_schedules', $column)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Для планирования оплат выполните миграции графика оплат.',
                ], 422);
            }
        }

        $validated = $request->validate([
            'payment_schedule_ids' => ['required', 'array', 'min:1'],
            'payment_schedule_ids.*' => ['integer'],
            'payment_run_date' => ['nullable', 'date'],
            'payment_run_note' => ['nullable', 'string', 'max:500'],
            'clear' => ['nullable', 'boolean'],
        ]);

        $ids = collect($validated['payment_schedule_ids'])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Выберите строки графика оплат.',
            ], 422);
        }

        $schedules = PaymentSchedule::query()
            ->whereIn('id', $ids->all())
            ->get();

        abort_unless($schedules->count() === $ids->count(), 404);

        $clear = (bool) ($validated['clear'] ?? false);
        $paymentRunDate = $clear
            ? null
            : (string) ($validated['payment_run_date'] ?? now()->toDateString());
        $paymentRunNote = $clear
            ? null
            : $this->nullableTrimmedString($validated['payment_run_note'] ?? null);

        $updated = [];

        DB::transaction(function () use ($request, $schedules, $paymentRunDate, $paymentRunNote, $clear, &$updated): void {
            foreach ($schedules as $schedule) {
                $this->ensureCanRecordPayment($request, $schedule);

                if (! $clear && in_array($schedule->status, ['paid', 'cancelled'], true)) {
                    continue;
                }

                $schedule->forceFill([
                    'payment_run_date' => $paymentRunDate,
                    'payment_run_by' => $paymentRunDate !== null ? $request->user()?->id : null,
                    'payment_run_note' => $paymentRunNote,
                ])->save();

                $updated[] = (int) $schedule->id;
            }
        });

        return response()->json([
            'success' => true,
            'updated_ids' => $updated,
            'payment_run_date' => $paymentRunDate,
        ]);
    }

    /**
     * Get partial payments for a payment schedule item.
     */
    public function getPartialPayments(Request $request, PaymentSchedule $paymentSchedule): JsonResponse
    {
        $this->ensureCanViewPaymentSchedule($request, $paymentSchedule);

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
     * Список активных фактических оплат по строке графика (включая частичные).
     */
    public function paymentEvents(Request $request, PaymentSchedule $paymentSchedule): JsonResponse
    {
        $this->ensureCanViewPaymentSchedule($request, $paymentSchedule);

        if (! $this->paymentLedger->ledgerTableExists()) {
            return response()->json([
                'success' => true,
                'payment_events' => [],
            ]);
        }

        $scheduleIds = [$paymentSchedule->id];

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')
            && Schema::hasColumn('payment_schedules', 'is_partial')) {
            $partialIds = PaymentSchedule::query()
                ->where('parent_payment_id', $paymentSchedule->id)
                ->where('is_partial', true)
                ->pluck('id')
                ->all();
            $scheduleIds = array_merge($scheduleIds, $partialIds);
        }

        $events = PaymentSchedulePaymentEvent::query()
            ->active()
            ->whereIn('payment_schedule_id', $scheduleIds)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (PaymentSchedulePaymentEvent $event): array => [
                'id' => $event->id,
                'amount' => (float) $event->amount,
                'payment_date' => $event->payment_date?->toDateString(),
                'payment_method' => $event->payment_method,
                'transaction_reference' => $event->transaction_reference,
                'notes' => $event->notes,
                'is_management_allocation' => str_starts_with((string) $event->transaction_reference, 'mgmt:'),
            ]);

        return response()->json([
            'success' => true,
            'payment_events' => $events,
            'can_void_payment_events' => RoleAccess::canReversePaymentScheduleEvent($request->user()),
        ]);
    }

    /**
     * Отмена ошибочно зафиксированной оплаты (ручной ввод или разнесение).
     */
    public function voidPaymentEvent(Request $request, PaymentSchedulePaymentEvent $paymentEvent): JsonResponse
    {
        abort_unless(RoleAccess::canReversePaymentScheduleEvent($request->user()), 403);

        $schedule = $paymentEvent->payment_schedule_id !== null
            ? PaymentSchedule::query()->find($paymentEvent->payment_schedule_id)
            : null;

        if ($schedule !== null) {
            $rootSchedule = $schedule;
            if (Schema::hasColumn('payment_schedules', 'is_partial')
                && (bool) $schedule->is_partial
                && $schedule->parent_payment_id) {
                $rootSchedule = PaymentSchedule::query()->find($schedule->parent_payment_id) ?? $schedule;
            }
            $this->ensureCanRecordPayment($request, $rootSchedule);
        }

        if (str_starts_with((string) $paymentEvent->transaction_reference, 'mgmt:')) {
            return response()->json([
                'success' => false,
                'message' => 'Платёж из разнесения выписки отменяйте на экране разнесения.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->paymentReversal->reverseEvent(
                $paymentEvent,
                $request->user(),
                $validated['reason'] ?? 'Отмена ручной фиксации оплаты',
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Оплата отменена.',
        ]);
    }

    /**
     * Cancel a payment schedule item.
     */
    public function cancel(Request $request, PaymentSchedule $paymentSchedule)
    {
        $this->ensureCanCancelPaymentScheduleRow($request, $paymentSchedule);

        $paymentSchedule->status = 'cancelled';
        $this->clearPaymentRunMark($paymentSchedule);
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
        $this->ensureCanCancelPaymentScheduleRow($request, $paymentSchedule);

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

    private function ensureCanViewPaymentSchedule(Request $request, ?PaymentSchedule $paymentSchedule = null): void
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(RoleAccess::canViewPaymentSchedules($user), 403);
        if ($paymentSchedule !== null) {
            $this->ensurePaymentScheduleInUserDataScope($request, $paymentSchedule);
        }
    }

    private function ensureCanManagePaymentSchedule(Request $request, ?PaymentSchedule $paymentSchedule = null): void
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(RoleAccess::canManagePaymentSchedules($user), 403);
        if ($paymentSchedule !== null) {
            $this->ensurePaymentScheduleInUserDataScope($request, $paymentSchedule);
        }
    }

    private function ensureCanRecordPayment(Request $request, ?PaymentSchedule $paymentSchedule = null): void
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(RoleAccess::canRecordPaymentOnPaymentSchedule($user), 403);
        if ($paymentSchedule !== null) {
            $this->ensurePaymentScheduleInUserDataScope($request, $paymentSchedule);
        }
    }

    private function ensureCanCancelPaymentScheduleRow(Request $request, ?PaymentSchedule $paymentSchedule = null): void
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(RoleAccess::canCancelPaymentScheduleRow($user), 403);
        if ($paymentSchedule !== null) {
            $this->ensurePaymentScheduleInUserDataScope($request, $paymentSchedule);
        }
    }

    private function ensurePaymentScheduleInUserDataScope(Request $request, PaymentSchedule $paymentSchedule): void
    {
        $user = $request->user();
        if ($user === null || $user->isAdmin()) {
            return;
        }

        if (RoleAccess::resolvePaymentScheduleDataScopeForUser($user) === 'all') {
            return;
        }

        $order = Order::query()->find((int) $paymentSchedule->order_id);
        abort_if($order === null, 403);
        abort_unless(OrderViewAuthorization::userOwnsOrderRecord($order, (int) $user->id), 403);
    }

    private function clearPaymentRunMark(PaymentSchedule $paymentSchedule): void
    {
        foreach (['payment_run_date', 'payment_run_by', 'payment_run_note'] as $column) {
            if (! Schema::hasColumn('payment_schedules', $column)) {
                return;
            }
        }

        $paymentSchedule->payment_run_date = null;
        $paymentSchedule->payment_run_by = null;
        $paymentSchedule->payment_run_note = null;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
