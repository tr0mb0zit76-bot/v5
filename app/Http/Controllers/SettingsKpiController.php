<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalaryCoefficientRequest;
use App\Http\Requests\UpdateKpiSettingsRequest;
use App\Http\Requests\UpdateSalaryCoefficientRequest;
use App\Models\SalaryCoefficient;
use App\Models\User;
use App\Services\KpiConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsKpiController extends Controller
{
    public function __construct(private readonly KpiConfigurationService $kpiConfigurationService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Settings/Kpi', [
            'bonusMultiplier' => $this->kpiConfigurationService->getBonusMultiplier(),
            'thresholds' => $this->kpiConfigurationService->groupedThresholds(),
        ]);
    }

    public function salaryIndex(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Settings/MotivationSalary', [
            'employees' => $this->employeesPayload(),
            'salaryCoefficients' => $this->salaryCoefficientsPayload(),
        ]);
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

        return to_route('settings.motivation.salary');
    }

    public function updateSalaryCoefficient(
        UpdateSalaryCoefficientRequest $request,
        SalaryCoefficient $salaryCoefficient
    ): RedirectResponse {
        $salaryCoefficient->update($request->validated());

        return to_route('settings.motivation.salary');
    }

    public function destroySalaryCoefficient(Request $request, SalaryCoefficient $salaryCoefficient): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $salaryCoefficient->delete();

        return to_route('settings.motivation.salary');
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
