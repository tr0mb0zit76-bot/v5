<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\LogsMcpToolCalls;
use App\Models\ImprovementExperiment;
use App\Models\ImprovementHypothesis;
use App\Models\ImprovementSignal;
use App\Models\User;
use App\Support\CrmFeatureCatalog;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_improvement_loop_insights')]
#[Description('Контур улучшений: открытые сигналы (продажи/документы/флот/УУ), черновики гипотез, активные эксперименты и статистика A/B. Read-only.')]
class GetImprovementLoopInsightsTool extends Tool
{
    use LogsMcpToolCalls;

    public function handle(Request $request): Response
    {
        return $this->withMcpAccess($request, function (User $user) use ($request): Response {
            if (! CrmFeatureCatalog::isEnabled('improvement_loop', $user) || ! RoleAccess::canAccessImprovementLoop($user)) {
                throw new AuthenticationException('Нет доступа к контуру улучшений.');
            }

            if (! Schema::hasTable('improvement_signals')) {
                return Response::json([
                    'available' => false,
                    'message' => 'Таблицы контура не созданы. Выполните php artisan migrate.',
                ]);
            }

            $validated = $request->validate([
                'domain' => ['nullable', 'string', 'in:sales,documents,fleet,finance'],
                'limit' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $limit = (int) ($validated['limit'] ?? 20);
            $domain = $validated['domain'] ?? null;

            $signalsQuery = ImprovementSignal::query()
                ->whereIn('status', [ImprovementSignal::STATUS_OPEN, ImprovementSignal::STATUS_LINKED])
                ->orderByDesc('created_at')
                ->limit($limit);

            if (is_string($domain) && $domain !== '') {
                $signalsQuery->where('domain', $domain);
            }

            $signals = $signalsQuery->get(['id', 'domain', 'kind', 'severity', 'title', 'status', 'payload', 'created_at'])
                ->map(fn (ImprovementSignal $s): array => [
                    'id' => $s->id,
                    'domain' => $s->domain,
                    'kind' => $s->kind,
                    'severity' => $s->severity,
                    'title' => $s->title,
                    'status' => $s->status,
                    'payload' => $s->payload,
                    'created_at' => optional($s->created_at)?->toIso8601String(),
                ])
                ->all();

            $hypotheses = Schema::hasTable('improvement_hypotheses')
                ? ImprovementHypothesis::query()
                    ->where('status', ImprovementHypothesis::STATUS_DRAFT)
                    ->orderByDesc('score')
                    ->limit($limit)
                    ->get(['id', 'category', 'text', 'score', 'status'])
                    ->map(fn (ImprovementHypothesis $h): array => [
                        'id' => $h->id,
                        'category' => $h->category,
                        'text' => $h->text,
                        'score' => $h->score,
                        'status' => $h->status,
                    ])
                    ->all()
                : [];

            $experiments = Schema::hasTable('improvement_experiments')
                ? ImprovementExperiment::query()
                    ->whereIn('status', [
                        ImprovementExperiment::STATUS_PLANNED,
                        ImprovementExperiment::STATUS_RUNNING,
                    ])
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get(['id', 'name', 'status', 'assignment_mode', 'result_snapshot', 'starts_on', 'ends_on'])
                    ->map(fn (ImprovementExperiment $e): array => [
                        'id' => $e->id,
                        'name' => $e->name,
                        'status' => $e->status,
                        'assignment_mode' => $e->assignment_mode,
                        'starts_on' => optional($e->starts_on)?->toDateString(),
                        'ends_on' => optional($e->ends_on)?->toDateString(),
                        'stats' => $e->result_snapshot['stats'] ?? null,
                    ])
                    ->all()
                : [];

            return Response::json([
                'available' => true,
                'ui_path' => '/improvement',
                'counts' => [
                    'open_signals' => count($signals),
                    'draft_hypotheses' => count($hypotheses),
                    'active_experiments' => count($experiments),
                ],
                'signals' => $signals,
                'draft_hypotheses' => $hypotheses,
                'active_experiments' => $experiments,
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()
                ->description('Фильтр сигналов: sales|documents|fleet|finance')
                ->enum(['sales', 'documents', 'fleet', 'finance']),
            'limit' => $schema->integer()
                ->description('Лимит строк в каждом блоке (5–50).')
                ->min(5)
                ->max(50),
        ];
    }
}
