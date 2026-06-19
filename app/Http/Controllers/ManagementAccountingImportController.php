<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateStatementImportException;
use App\Http\Requests\AllocateManagementStatementLineRequest;
use App\Http\Requests\DeallocateManagementStatementLineRequest;
use App\Http\Requests\StoreManagementAccountingImportRequest;
use App\Http\Requests\StoreManagementExpenseCategoryRequest;
use App\Http\Requests\StoreManagementManualEntryRequest;
use App\Http\Requests\UpdateManagementExpenseCategoryRequest;
use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Services\ManagementAccounting\ManagementAccountingAllocationService;
use App\Services\ManagementAccounting\ManagementAccountingImportService;
use App\Services\ManagementAccounting\ManagementAccountingMatchingService;
use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use App\Services\ManagementAccounting\ManagementExpenseCategoryTreeService;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagementAccountingImportController extends Controller
{
    public function __construct(
        private readonly ManagementAccountingImportService $importService,
        private readonly ManagementAccountingAllocationService $allocationService,
        private readonly ManagementAccountingMatchingService $matchingService,
        private readonly ManagementExpenseCategorySyncService $expenseCategorySyncService,
        private readonly ManagementExpenseCategoryTreeService $categoryTreeService,
    ) {}

    public function store(StoreManagementAccountingImportRequest $request): RedirectResponse
    {
        $bankAccountId = $request->validated('bank_account_id');
        $bankAccount = $bankAccountId !== null
            ? ManagementBankAccount::query()->findOrFail((int) $bankAccountId)
            : null;

        try {
            $import = $this->importService->importFromUpload(
                $request->file('statement_file'),
                $bankAccount,
                $request->user(),
            );
        } catch (DuplicateStatementImportException $exception) {
            return to_route('finance.management-accounting.imports.show', [
                'import' => $exception->existingImport,
                'filter' => 'allocated',
            ])->with('flash', [
                'type' => 'warning',
                'message' => 'Эта выписка уже загружена — открыта существующая для правки.',
            ]);
        }

        return to_route('finance.management-accounting.imports.show', $import)
            ->with('flash', ['type' => 'success', 'message' => 'Выписка загружена. Разнесите операции.']);
    }

    public function show(Request $request, ManagementStatementImport $import): Response
    {
        abort_unless(RoleAccess::canManageStatementImport($request->user()), 403);

        $import->load(['bankAccount', 'importer:id,name']);

        $lines = ManagementStatementLine::query()
            ->where('import_id', $import->id)
            ->with([
                'suggestedOrder:id,order_number',
                'suggestedPaymentSchedule:id,party,amount,planned_date',
                'suggestedCategory:id,name,code',
                'suggestedUser:id,name',
                'allocationOrder:id,order_number',
                'allocationCategory:id,name,code',
                'allocationPaymentSchedule:id,party,amount,planned_date',
                'allocationUser:id,name',
                'allocator:id,name',
                'splits.paymentSchedule:id,party,amount,planned_date',
                'splits.order:id,order_number',
            ])
            ->orderBy('operation_date')
            ->orderBy('row_number')
            ->get()
            ->map(fn (ManagementStatementLine $line): array => [
                'id' => $line->id,
                'operation_date' => $line->operation_date?->toDateString(),
                'direction' => $line->direction,
                'amount' => (float) $line->amount,
                'currency' => $line->currency,
                'description' => $line->description,
                'status' => $line->status,
                'match_type' => $line->match_type,
                'match_confidence' => $line->match_confidence,
                'match_notes' => $line->match_notes,
                'suggested_order' => $line->suggestedOrder === null ? null : [
                    'id' => $line->suggestedOrder->id,
                    'order_number' => $line->suggestedOrder->order_number,
                ],
                'suggested_payment_schedule' => $line->suggestedPaymentSchedule === null ? null : [
                    'id' => $line->suggestedPaymentSchedule->id,
                    'party' => $line->suggestedPaymentSchedule->party,
                    'amount' => (float) $line->suggestedPaymentSchedule->amount,
                    'planned_date' => $line->suggestedPaymentSchedule->planned_date,
                ],
                'suggested_category' => $line->suggestedCategory === null ? null : [
                    'id' => $line->suggestedCategory->id,
                    'name' => $line->suggestedCategory->name,
                    'code' => $line->suggestedCategory->code,
                ],
                'suggested_user' => $line->suggestedUser === null ? null : [
                    'id' => $line->suggestedUser->id,
                    'name' => $line->suggestedUser->name,
                ],
                'operational_candidates' => $line->status !== 'allocated'
                    ? $this->matchingService->operationalCandidatesForLine($line)
                    : [],
                'contractor_search_hint' => $this->matchingService->extractSearchHintFromDescription((string) $line->description),
                'needs_manual_selection' => $line->status !== 'allocated'
                    && $line->match_confidence < 70
                    && $line->suggested_payment_schedule_id === null,
                'allocation_summary' => $line->status === 'allocated' ? [
                    'match_type' => $line->match_type,
                    'amount' => (float) ($line->allocation_amount ?? $line->amount),
                    'allocated_at' => $line->allocated_at?->toIso8601String(),
                    'allocated_by_name' => $line->allocator?->name,
                    'order' => $line->allocationOrder === null ? null : [
                        'id' => $line->allocationOrder->id,
                        'order_number' => $line->allocationOrder->order_number,
                    ],
                    'payment_schedule' => $line->allocationPaymentSchedule === null ? null : [
                        'id' => $line->allocationPaymentSchedule->id,
                        'party' => $line->allocationPaymentSchedule->party,
                        'amount' => (float) $line->allocationPaymentSchedule->amount,
                        'planned_date' => $line->allocationPaymentSchedule->planned_date,
                    ],
                    'category' => $line->allocationCategory === null ? null : [
                        'id' => $line->allocationCategory->id,
                        'name' => $line->allocationCategory->name,
                        'code' => $line->allocationCategory->code,
                    ],
                    'user' => $line->allocationUser === null ? null : [
                        'id' => $line->allocationUser->id,
                        'name' => $line->allocationUser->name,
                    ],
                    'splits' => $line->match_type === 'operational_split'
                        ? $line->splits->map(fn ($split): array => [
                            'id' => $split->id,
                            'amount' => (float) $split->amount,
                            'order_number' => $split->order?->order_number,
                            'payment_schedule_id' => $split->payment_schedule_id,
                            'payment_schedule_amount' => $split->paymentSchedule !== null
                                ? (float) $split->paymentSchedule->amount
                                : null,
                        ])->values()->all()
                        : [],
                ] : null,
            ]);

        return Inertia::render('Finance/ManagementAccounting/Reconcile', [
            'import' => [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'status' => $import->status,
                'period_from' => $import->period_from?->toDateString(),
                'period_to' => $import->period_to?->toDateString(),
                'lines_count' => $import->lines_count,
                'lines_allocated' => $import->lines_allocated,
                'total_in' => (float) $import->total_in,
                'total_out' => (float) $import->total_out,
                'bank_account' => $import->bankAccount === null ? null : [
                    'id' => $import->bankAccount->id,
                    'bank_name' => $import->bankAccount->bank_name,
                    'account_mask' => $import->bankAccount->account_mask,
                    'currency' => $import->bankAccount->currency,
                ],
                'importer_name' => $import->importer?->name,
            ],
            'lines' => $lines,
            'categories' => ManagementExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'kind']),
            'filters' => [
                'line_filter' => in_array($request->query('filter'), ['pending', 'allocated', 'all'], true)
                    ? $request->string('filter')->toString()
                    : null,
            ],
        ]);
    }

    public function operationalCandidates(Request $request, ManagementStatementLine $line): JsonResponse
    {
        abort_unless(RoleAccess::canManageStatementImport($request->user()), 403);

        return response()->json([
            'candidates' => $this->matchingService->searchOperationalCandidates(
                $line,
                $request->string('search')->toString() ?: null,
            ),
        ]);
    }

    public function allocate(
        AllocateManagementStatementLineRequest $request,
        ManagementStatementLine $line,
    ): RedirectResponse {
        $this->allocationService->allocateLine($line, $request->validated(), $request->user());

        if ($line->import_id !== null) {
            return back()->with('flash', ['type' => 'success', 'message' => 'Операция разнесена.']);
        }

        return to_route('finance.management-accounting.index')
            ->with('flash', ['type' => 'success', 'message' => 'Ручная операция сохранена.']);
    }

    public function deallocate(
        DeallocateManagementStatementLineRequest $request,
        ManagementStatementLine $line,
    ): RedirectResponse {
        abort_unless(RoleAccess::canManageStatementImport($request->user()), 403);

        $this->allocationService->deallocateLine(
            $line,
            $request->user(),
            $request->validated('reason'),
        );

        if ($line->import_id !== null) {
            return back()->with('flash', ['type' => 'success', 'message' => 'Разнесение отменено.']);
        }

        return to_route('finance.management-accounting.index')
            ->with('flash', ['type' => 'success', 'message' => 'Ручная операция отменена.']);
    }

    public function destroy(Request $request, ManagementStatementImport $import): RedirectResponse
    {
        abort_unless(RoleAccess::canManageStatementImport($request->user()), 403);

        $this->importService->destroyImport($import, $request->user());

        return to_route('finance.index', [
            'section' => 'cashflow',
            'cashflow_tab' => 'reconcile',
        ])->with('flash', [
            'type' => 'success',
            'message' => 'Выписка и все её операции удалены.',
        ]);
    }

    public function storeManual(StoreManagementManualEntryRequest $request): RedirectResponse
    {
        $line = $this->allocationService->createManualLine($request->validated(), $request->user());
        $this->allocationService->allocateLine(
            $line,
            [
                'allocation_type' => $request->validated('allocation_type'),
                'category_id' => $request->validated('category_id'),
                'payment_schedule_id' => $request->validated('payment_schedule_id'),
                'user_id' => $request->validated('user_id'),
            ],
            $request->user(),
        );

        return to_route('finance.management-accounting.index')
            ->with('flash', ['type' => 'success', 'message' => 'Ручная операция добавлена.']);
    }

    public function storeCategory(StoreManagementExpenseCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        $this->categoryTreeService->create(
            (string) $validated['name'],
            $parentId,
            (string) ($validated['flow'] ?? 'out'),
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Статья добавлена.']);
    }

    public function syncCategories(Request $request): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessPaymentReconcile($request->user()), 403);

        $this->expenseCategorySyncService->syncAll();

        return back()->with('flash', ['type' => 'success', 'message' => 'Справочник статей обновлён.']);
    }

    public function updateCategory(
        UpdateManagementExpenseCategoryRequest $request,
        ManagementExpenseCategory $category,
    ): RedirectResponse {
        $validated = $request->validated();

        if ($category->is_system && array_key_exists('name', $validated)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Системную статью нельзя переименовать.']);
        }

        if ($validated === []) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Нет данных для обновления.']);
        }

        $this->categoryTreeService->update($category, $validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Статья обновлена.']);
    }

    public function destroyCategory(Request $request, ManagementExpenseCategory $category): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessPaymentReconcile($request->user()), 403);

        $this->categoryTreeService->delete($category);

        return back()->with('flash', ['type' => 'success', 'message' => 'Статья удалена.']);
    }
}
