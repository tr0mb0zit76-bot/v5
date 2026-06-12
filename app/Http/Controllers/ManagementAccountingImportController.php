<?php

namespace App\Http\Controllers;

use App\Http\Requests\AllocateManagementStatementLineRequest;
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

        $import = $this->importService->importFromUpload(
            $request->file('statement_file'),
            $bankAccount,
            $request->user(),
        );

        return to_route('finance.management-accounting.imports.show', $import)
            ->with('flash', ['type' => 'success', 'message' => 'Выписка загружена. Разнесите операции.']);
    }

    public function show(Request $request, ManagementStatementImport $import): Response
    {
        abort_unless(RoleAccess::canAccessManagementAccounting($request->user()), 403);
        abort_unless((int) $import->imported_by === (int) $request->user()?->id || $request->user()?->isAdmin(), 403);

        $import->load(['bankAccount', 'importer:id,name']);

        $lines = ManagementStatementLine::query()
            ->where('import_id', $import->id)
            ->with([
                'suggestedOrder:id,order_number',
                'suggestedPaymentSchedule:id,party,amount,planned_date',
                'suggestedCategory:id,name,code',
                'suggestedUser:id,name',
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
                'operational_candidates' => $line->status === 'allocated'
                    ? []
                    : $this->matchingService->operationalCandidatesForLine($line),
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
        abort_unless(RoleAccess::canAccessManagementAccounting($request->user()), 403);

        $this->expenseCategorySyncService->syncAll();

        return back()->with('flash', ['type' => 'success', 'message' => 'Справочник статей обновлён.']);
    }

    public function updateCategory(
        UpdateManagementExpenseCategoryRequest $request,
        ManagementExpenseCategory $category,
    ): RedirectResponse {
        if ($category->is_system) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Системную статью нельзя переименовать.']);
        }

        $this->categoryTreeService->update($category, (string) $request->validated('name'));

        return back()->with('flash', ['type' => 'success', 'message' => 'Статья обновлена.']);
    }

    public function destroyCategory(Request $request, ManagementExpenseCategory $category): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessManagementAccounting($request->user()), 403);

        $this->categoryTreeService->delete($category);

        return back()->with('flash', ['type' => 'success', 'message' => 'Статья удалена.']);
    }
}
