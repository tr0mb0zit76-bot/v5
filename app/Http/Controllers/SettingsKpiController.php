<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalaryCoefficientRequest;
use App\Http\Requests\StoreSalaryPayoutRequest;
use App\Http\Requests\StoreSalaryPeriodRequest;
use App\Http\Requests\UpdateKpiSettingsRequest;
use App\Http\Requests\UpdateSalaryCoefficientRequest;
use App\Models\SalaryCoefficient;
use App\Models\SalaryPeriod;
use App\Models\User;
use App\Services\KpiConfigurationService;
use App\Services\SalaryPayrollService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsKpiController extends Controller
{
    public function __construct(
        private readonly KpiConfigurationService $kpiConfigurationService,
        private readonly SalaryPayrollService $salaryPayrollService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsMotivation($request->user()), 403);

        return Inertia::render('Settings/Kpi', [
            'bonusMultiplier' => $this->kpiConfigurationService->getBonusMultiplier(),
            'thresholds' => $this->kpiConfigurationService->groupedThresholds(),
        ]);
    }

    public function salaryIndex(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsMotivation($request->user()), 403);

        return Inertia::render('Settings/MotivationSalary', [
            ...$this->salaryPagePayload($request),
            'salary_module' => 'settings',
        ]);
    }

    public function financeSalaryIndex(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessFinanceSalary($request->user()), 403);

        return Inertia::render('Settings/MotivationSalary', [
            ...$this->salaryPagePayload($request),
            'salary_module' => 'finance',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function salaryPagePayload(Request $request): array
    {
        $periods = $this->salaryPayrollService->periods();
        $activePeriod = $periods->firstWhere('id', (int) $request->integer('salary_period_id')) ?? $periods->first();
        $selectedSalaryUserId = $request->filled('salary_user_id')
            ? (int) $request->integer('salary_user_id')
            : null;

        return [
            'employees' => $this->employeesPayload(),
            'salaryCoefficients' => $this->salaryCoefficientsPayload(),
            'salaryPeriods' => $periods->map(fn (SalaryPeriod $period): array => [
                'id' => $period->id,
                'period_start' => optional($period->period_start)?->toDateString(),
                'period_end' => optional($period->period_end)?->toDateString(),
                'period_type' => $period->period_type,
                'status' => $period->status,
                'notes' => $period->notes,
            ])->values(),
            'activeSalaryPeriodId' => $activePeriod?->id,
            'activeSalaryUserId' => $selectedSalaryUserId,
            'salaryPeriodUsers' => $this->salaryPayrollService->userSummariesForPeriod($activePeriod, $selectedSalaryUserId),
            'salaryPeriodOrderRows' => $this->salaryPayrollService->orderRowsForPeriod($activePeriod, $selectedSalaryUserId),
        ];
    }

    public function update(UpdateKpiSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->kpiConfigurationService->saveBonusMultiplier((float) $validated['bonus_multiplier']);
        $this->kpiConfigurationService->replaceThresholds($validated['thresholds']);

        return to_route('settings.motivation.kpi');
    }

    public function storeSalaryCoefficient(StoreSalaryCoefficientRequest $request): RedirectResponse
    {
        SalaryCoefficient::query()->create($request->validated());

        return $this->salaryRedirect($request);
    }

    public function updateSalaryCoefficient(
        UpdateSalaryCoefficientRequest $request,
        SalaryCoefficient $salaryCoefficient
    ): RedirectResponse {
        $salaryCoefficient->update($request->validated());

        return $this->salaryRedirect($request);
    }

    public function destroySalaryCoefficient(Request $request, SalaryCoefficient $salaryCoefficient): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);

        $salaryCoefficient->delete();

        return $this->salaryRedirect($request);
    }

    public function storeSalaryPeriod(StoreSalaryPeriodRequest $request): RedirectResponse
    {
        $period = $this->salaryPayrollService->createPeriod($request->validated(), $request->user()?->id);
        $this->salaryPayrollService->recalculatePeriod($period);

        return $this->salaryRedirect($request, ['salary_period_id' => $period->id]);
    }

    public function recalculateSalaryPeriod(Request $request, SalaryPeriod $salaryPeriod): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);
        $this->salaryPayrollService->recalculatePeriod($salaryPeriod);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    public function approveSalaryPeriod(Request $request, SalaryPeriod $salaryPeriod): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);
        $this->salaryPayrollService->approvePeriod($salaryPeriod, $request->user()?->id);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    public function closeSalaryPeriod(Request $request, SalaryPeriod $salaryPeriod): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);
        $this->salaryPayrollService->closePeriod($salaryPeriod, $request->user()?->id);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    public function storeSalaryPayout(
        StoreSalaryPayoutRequest $request,
        SalaryPeriod $salaryPeriod
    ): RedirectResponse {
        $this->salaryPayrollService->createPayout($salaryPeriod, $request->validated(), $request->user()?->id);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    private function assertSalaryModuleAccess(Request $request): void
    {
        if ($request->routeIs('finance.salary.*')) {
            abort_unless(RoleAccess::canAccessFinanceSalary($request->user()), 403);

            return;
        }

        abort_unless(RoleAccess::canAccessSettingsMotivation($request->user()), 403);
    }

    /**
     * @param  array<string, scalar|null>  $parameters
     */
    private function salaryRedirect(Request $request, array $parameters = []): RedirectResponse
    {
        $routeName = $request->routeIs('finance.salary.*')
            ? 'finance.salary.index'
            : 'settings.motivation.salary';

        $salaryUserId = $request->filled('salary_user_id')
            ? (int) $request->integer('salary_user_id')
            : null;

        if ($salaryUserId !== null && ! array_key_exists('salary_user_id', $parameters)) {
            $parameters['salary_user_id'] = $salaryUserId;
        }

        return to_route($routeName, $parameters);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeesPayload(): array
    {
        return User::query()
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'roles.name as role_name',
            ])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_name' => $user->role_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function salaryCoefficientsPayload(): array
    {
        return SalaryCoefficient::query()
            ->with('manager:id,name,email')
            ->orderByDesc('effective_from')
            ->orderBy('manager_id')
            ->get()
            ->map(fn (SalaryCoefficient $coefficient): array => [
                'id' => $coefficient->id,
                'manager_id' => $coefficient->manager_id,
                'manager_name' => $coefficient->manager?->name,
                'base_salary' => $coefficient->base_salary,
                'bonus_percent' => $coefficient->bonus_percent,
                'effective_from' => optional($coefficient->effective_from)?->toDateString(),
                'effective_to' => optional($coefficient->effective_to)?->toDateString(),
                'is_active' => $coefficient->is_active,
            ])
            ->values()
            ->all();
    }
}
