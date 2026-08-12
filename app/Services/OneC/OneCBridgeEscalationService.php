<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Задачи эскалации моста 1С с дедупом по meta.
 */
final class OneCBridgeEscalationService
{
    public const META_KIND = 'one_c_bridge_kind';

    public const META_COMPANY = 'one_c_bridge_company';

    public const META_PERIOD = 'one_c_bridge_period';

    /**
     * @param  array{
     *     status: string,
     *     summary_ru: string,
     *     companies: list<array{code: string, label: string, issues: list<string>, pending_count: int, odata_ok: bool}>
     * }  $verdict
     * @return array{created: bool, task: ?Task}
     */
    public function escalateFromVerdict(array $verdict, ?User $initiator = null): array
    {
        $kinds = $this->kindsFromVerdict($verdict);
        if ($kinds === []) {
            return ['created' => false, 'task' => null];
        }

        $assigneeId = config('one_c.bridge.escalation_user_id');
        if ($assigneeId === null || (int) $assigneeId <= 0) {
            throw new InvalidArgumentException(
                'Не задан ONE_C_BRIDGE_ESCALATION_USER_ID — некому назначить задачу эскалации.'
            );
        }

        $assignee = User::query()->find((int) $assigneeId);
        if ($assignee === null) {
            throw new InvalidArgumentException(
                'ONE_C_BRIDGE_ESCALATION_USER_ID указывает на несуществующего пользователя.'
            );
        }

        $period = now()->toDateString();
        $createdTask = null;

        foreach ($kinds as $kind => $payload) {
            if ($this->hasOpenTask($payload['company'], $kind, $period)) {
                continue;
            }

            $createdTask = Task::query()->create([
                'number' => 'T-'.Str::upper(Str::random(8)),
                'title' => $payload['title'],
                'description' => $payload['description'],
                'status' => 'new',
                'priority' => $verdict['status'] === 'error' ? 'high' : 'normal',
                'due_at' => now()->addDay(),
                'responsible_id' => $assignee->id,
                'created_by' => $initiator?->id,
                'meta' => [
                    self::META_KIND => $kind,
                    self::META_COMPANY => $payload['company'],
                    self::META_PERIOD => $period,
                    'one_c_bridge_initiator' => $initiator !== null ? 'user' : 'system',
                ],
            ]);
        }

        return [
            'created' => $createdTask !== null,
            'task' => $createdTask,
        ];
    }

    public function hasOpenTask(string $company, string $kind, string $period): bool
    {
        return Task::query()
            ->where('status', '!=', 'done')
            ->where('meta->'.self::META_COMPANY, $company)
            ->where('meta->'.self::META_KIND, $kind)
            ->where('meta->'.self::META_PERIOD, $period)
            ->exists();
    }

    /**
     * Эскалация только при реальной поломке моста (OData / нет счёта / docs gap), не из‑за pending.
     *
     * @param  array{companies: list<array{code: string, label: string, issues: list<string>, odata_ok: bool, pending_count: int, docs_gap_count?: int, bank_account_id?: ?int, needs_escalation?: bool}>}  $verdict
     * @return array<string, array{company: string, title: string, description: string}>
     */
    private function kindsFromVerdict(array $verdict): array
    {
        $kinds = [];
        foreach ($verdict['companies'] as $company) {
            $needsEscalation = array_key_exists('needs_escalation', $company)
                ? (bool) $company['needs_escalation']
                : (! $company['odata_ok']
                    || ($company['bank_account_id'] ?? null) === null
                    || (int) ($company['docs_gap_count'] ?? 0) > 0);
            if (! $needsEscalation) {
                continue;
            }

            $code = $company['code'];
            $kind = ! $company['odata_ok'] ? 'odata' : 'attention';
            $key = $code.':'.$kind;
            $escalationIssues = array_values(array_filter(
                $company['issues'] ?? [],
                static fn (string $issue): bool => ! str_starts_with($issue, 'Неразнесённых платежей:')
            ));
            $kinds[$key] = [
                'company' => $code,
                'title' => 'Мост 1С: '.$company['label'].($kind === 'odata' ? ' — нет связи' : ' — внимание'),
                'description' => implode("\n", $escalationIssues !== [] ? $escalationIssues : $company['issues']),
            ];
        }

        return $kinds;
    }
}
