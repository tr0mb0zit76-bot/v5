<?php

namespace App\Services\Contractor;

use App\Jobs\EnrichContractorJob;
use App\Models\Contractor;
use App\Models\ContractorEnrichmentRun;
use App\Models\ContractorInsightDraft;
use App\Models\User;
use App\Support\ContractorPortraitDictionary;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContractorEnrichmentService
{
    public function __construct(
        private readonly ContractorCrmFactsCollector $crmFacts,
        private readonly ContractorWebFactsCollector $webFacts,
        private readonly ContractorExternalFactsCollector $externalFacts,
        private readonly ContractorInsightDraftService $insightDrafts,
        private readonly ContractorPortraitService $portraitService,
    ) {}

    /**
     * Soft-fail enqueue после create (UI / MCP / inline).
     */
    public function maybeDispatchAfterCreate(Contractor $contractor, ?User $user): void
    {
        if ($user === null) {
            return;
        }

        if (! (bool) config('contractor_enrichment.enabled', true)) {
            return;
        }

        if (! (bool) config('contractor_enrichment.dispatch_on_create', true)) {
            return;
        }

        if (! Schema::hasTable('contractor_enrichment_runs')) {
            return;
        }

        try {
            $this->dispatch($contractor, $user, ContractorEnrichmentRun::TRIGGER_CREATE, true);
        } catch (Throwable) {
            // create path must not fail
        }
    }

    public function dispatch(Contractor $contractor, User $user, string $trigger = ContractorEnrichmentRun::TRIGGER_MANUAL, bool $force = false): ContractorEnrichmentRun
    {
        $run = $this->createPendingRun($contractor, $user, $trigger, $force);
        EnrichContractorJob::dispatch($run->id);

        return $run;
    }

    /**
     * @return array{run: ContractorEnrichmentRun, dossier: array<string, mixed>, drafts_created: int}
     */
    public function runNow(Contractor $contractor, User $user, string $trigger = ContractorEnrichmentRun::TRIGGER_MANUAL, bool $force = false): array
    {
        $run = $this->createPendingRun($contractor, $user, $trigger, $force);

        return $this->run($run);
    }

    private function createPendingRun(Contractor $contractor, User $user, string $trigger, bool $force): ContractorEnrichmentRun
    {
        $this->assertEnabled();

        if (! Schema::hasTable('contractor_enrichment_runs')) {
            throw ValidationException::withMessages([
                'enrichment' => 'Модуль обогащения недоступен (нет таблицы).',
            ]);
        }

        if (! $force && $this->isThrottled($contractor)) {
            throw ValidationException::withMessages([
                'enrichment' => 'Сводка уже обновлялась недавно. Повторите позже или запросите принудительное обновление.',
            ]);
        }

        return ContractorEnrichmentRun::query()->create([
            'contractor_id' => $contractor->id,
            'status' => ContractorEnrichmentRun::STATUS_PENDING,
            'trigger' => $trigger,
            'created_by' => $user->id,
        ]);
    }

    /**
     * @return array{
     *     run: ContractorEnrichmentRun,
     *     dossier: array<string, mixed>,
     *     drafts_created: int
     * }
     */
    public function run(ContractorEnrichmentRun $run): array
    {
        $this->assertEnabled();

        $contractor = Contractor::query()->findOrFail($run->contractor_id);

        $run->update([
            'status' => ContractorEnrichmentRun::STATUS_RUNNING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $sources = ['crm'];
            $crm = $this->crmFacts->collect($contractor);
            $web = [
                'enabled' => false,
                'query' => null,
                'website' => null,
                'website_excerpt' => null,
                'snippets' => [],
                'error' => null,
            ];

            if ((bool) config('contractor_enrichment.web.enabled', true)) {
                $sources[] = 'web';
                $web = $this->webFacts->collect($contractor);
            }

            $external = ['dadata' => null, 'checko' => null];
            if ((bool) config('contractor_enrichment.include_external', true)) {
                $external = $this->externalFacts->collect($contractor);
                if (($external['dadata'] ?? null) !== null) {
                    $sources[] = 'dadata';
                }
                if (is_array($external['checko'] ?? null) && ($external['checko']['ok'] ?? false)) {
                    $sources[] = 'checko';
                }
            }

            $dossier = [
                'crm' => $crm,
                'web' => $web,
                'external' => $external,
                'built_at' => now()->toIso8601String(),
            ];

            $proposals = $this->buildProposals($contractor, $crm, $web, $external);
            $created = $this->insightDrafts->createPendingProposals(
                $contractor,
                $proposals,
                $run->id,
            );

            $run->update([
                'status' => ContractorEnrichmentRun::STATUS_SUCCEEDED,
                'sources_json' => $sources,
                'dossier_json' => $dossier,
                'proposed_drafts_json' => $proposals,
                'finished_at' => now(),
            ]);

            return [
                'run' => $run->fresh(),
                'dossier' => $dossier,
                'drafts_created' => count($created),
            ];
        } catch (Throwable $e) {
            $run->update([
                'status' => ContractorEnrichmentRun::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestSummary(Contractor $contractor): ?array
    {
        if (! Schema::hasTable('contractor_enrichment_runs')) {
            return null;
        }

        $run = ContractorEnrichmentRun::query()
            ->where('contractor_id', $contractor->id)
            ->orderByDesc('id')
            ->first();

        if ($run === null) {
            return null;
        }

        $dossier = is_array($run->dossier_json) ? $run->dossier_json : [];
        $crm = is_array($dossier['crm'] ?? null) ? $dossier['crm'] : [];
        $relationships = is_array($crm['relationships'] ?? null) ? $crm['relationships'] : [];
        $funnel = is_array($crm['funnel'] ?? null) ? $crm['funnel'] : [];
        $web = is_array($dossier['web'] ?? null) ? $dossier['web'] : [];
        $external = is_array($dossier['external'] ?? null) ? $dossier['external'] : [];
        $checko = is_array($external['checko'] ?? null) ? $external['checko'] : [];

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'trigger' => $run->trigger,
            'updated_at' => optional($run->finished_at ?? $run->updated_at)?->toIso8601String(),
            'error_message' => $run->error_message,
            'customer_orders_count' => (int) ($relationships['customer_orders_count'] ?? 0),
            'carrier_orders_count' => (int) ($relationships['carrier_orders_count'] ?? 0),
            'leads_open' => (int) ($funnel['leads_open'] ?? 0),
            'web_snippets_count' => count($web['snippets'] ?? []),
            'checko_grade' => $checko['grade'] ?? null,
            'checko_tier_label' => $checko['tier_label'] ?? null,
            'pending_drafts_count' => Schema::hasTable('contractor_insight_drafts')
                ? ContractorInsightDraft::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('status', ContractorInsightDraft::STATUS_PENDING)
                    ->count()
                : 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestDossier(Contractor $contractor): ?array
    {
        if (! Schema::hasTable('contractor_enrichment_runs')) {
            return null;
        }

        $run = ContractorEnrichmentRun::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ContractorEnrichmentRun::STATUS_SUCCEEDED)
            ->orderByDesc('id')
            ->first();

        if ($run === null) {
            return null;
        }

        return [
            'run_id' => $run->id,
            'finished_at' => optional($run->finished_at)?->toIso8601String(),
            'dossier' => $run->dossier_json,
        ];
    }

    public function isThrottled(Contractor $contractor): bool
    {
        $hours = max(1, (int) config('contractor_enrichment.throttle_hours', 12));

        return ContractorEnrichmentRun::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ContractorEnrichmentRun::STATUS_SUCCEEDED)
            ->where('finished_at', '>=', now()->subHours($hours))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $crm
     * @param  array<string, mixed>  $web
     * @param  array{dadata: ?array<string, mixed>, checko: ?array<string, mixed>}  $external
     * @return list<array{field_key: string, proposed_value: mixed, confidence: float|null, source_type: string, source_url: string|null}>
     */
    private function buildProposals(Contractor $contractor, array $crm, array $web, array $external): array
    {
        $portrait = $this->portraitService->getOrCreate($contractor);
        $proposals = [];

        $objections = $crm['communications']['top_objection_tags'] ?? [];
        if (is_array($objections) && $objections !== []) {
            $existing = is_array($portrait->typical_objections) ? $portrait->typical_objections : [];
            $new = array_values(array_diff($objections, $existing));
            if ($new !== []) {
                $proposals[] = [
                    'field_key' => 'typical_objections',
                    'proposed_value' => $new,
                    'confidence' => 0.65,
                    'source_type' => ContractorInsightDraft::SOURCE_CRM_ENRICHMENT,
                    'source_url' => null,
                ];
            }
        }

        $lostPriceHints = (int) ($crm['funnel']['lost_price_hints'] ?? 0);
        if (
            $lostPriceHints > 0
            && ($portrait->price_sensitivity ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN
        ) {
            $proposals[] = [
                'field_key' => 'price_sensitivity',
                'proposed_value' => 'high',
                'confidence' => 0.6,
                'source_type' => ContractorInsightDraft::SOURCE_CRM_ENRICHMENT,
                'source_url' => null,
            ];
        }

        $channel = $crm['communications']['last_interaction']['channel'] ?? null;
        if (
            is_string($channel)
            && $channel !== ''
            && ($portrait->preferred_channel ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN
            && in_array($channel, ContractorPortraitDictionary::preferredChannels(), true)
            && $channel !== ContractorPortraitDictionary::UNKNOWN
        ) {
            $proposals[] = [
                'field_key' => 'preferred_channel',
                'proposed_value' => $channel,
                'confidence' => 0.55,
                'source_type' => ContractorInsightDraft::SOURCE_CRM_ENRICHMENT,
                'source_url' => null,
            ];
        }

        $identityNote = $this->identityMemo($crm['identity'] ?? [], $external['dadata'] ?? null, $external['checko'] ?? null);
        $webNote = $this->webMemo($web);
        $memoParts = array_values(array_filter([
            $identityNote,
            $webNote['text'] ?? null,
        ]));
        if ($memoParts !== [] && blank($portrait->internal_notes)) {
            $proposals[] = [
                'field_key' => 'internal_notes',
                'proposed_value' => implode("\n\n", $memoParts),
                'confidence' => $webNote !== null ? 0.55 : 0.7,
                'source_type' => $webNote !== null
                    ? ContractorInsightDraft::SOURCE_WEB_PUBLIC
                    : ContractorInsightDraft::SOURCE_CRM_ENRICHMENT,
                'source_url' => $webNote['url'] ?? null,
            ];
        }

        return $proposals;
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>|null  $dadata
     * @param  array<string, mixed>|null  $checko
     */
    private function identityMemo(array $identity, ?array $dadata, ?array $checko): ?string
    {
        $parts = [];
        $name = trim((string) ($dadata['value'] ?? $identity['name'] ?? ''));
        $inn = trim((string) ($dadata['inn'] ?? $identity['inn'] ?? ''));
        $form = trim((string) ($identity['legal_form'] ?? ''));
        $desc = trim((string) ($identity['short_description'] ?? ''));
        $site = trim((string) ($identity['website'] ?? ''));
        $okved = trim((string) ($dadata['okved_name'] ?? $dadata['okved'] ?? ''));
        $address = trim((string) ($dadata['address'] ?? ''));

        if ($name !== '') {
            $parts[] = $name;
        }
        if ($inn !== '') {
            $parts[] = 'ИНН '.$inn;
        }
        if ($form !== '') {
            $parts[] = 'форма '.$form;
        }
        if ($okved !== '') {
            $parts[] = 'ОКВЭД '.$okved;
        }
        if ($address !== '') {
            $parts[] = $address;
        }
        if ($desc !== '') {
            $parts[] = $desc;
        }
        if ($site !== '') {
            $parts[] = 'сайт '.$site;
        }
        if (is_array($checko) && ($checko['ok'] ?? false) && ! empty($checko['grade'])) {
            $tier = trim((string) ($checko['tier_label'] ?? ''));
            $parts[] = 'Checko '.$checko['grade'].($tier !== '' ? " ({$tier})" : '');
        }

        if ($parts === []) {
            return null;
        }

        return 'Карточка: '.implode('; ', $parts).'.';
    }

    /**
     * @param  array<string, mixed>  $web
     * @return array{text: string, url: string|null}|null
     */
    private function webMemo(array $web): ?array
    {
        $lines = [];
        $url = null;

        $excerpt = trim((string) ($web['website_excerpt'] ?? ''));
        $website = trim((string) ($web['website'] ?? ''));
        if ($excerpt !== '') {
            $lines[] = $excerpt;
            $url = $website !== '' ? $website : null;
        }

        $snippets = is_array($web['snippets'] ?? null) ? $web['snippets'] : [];
        foreach ($snippets as $snippet) {
            if (! is_array($snippet)) {
                continue;
            }
            $title = trim((string) ($snippet['title'] ?? ''));
            $text = trim((string) ($snippet['snippet'] ?? ''));
            $snippetUrl = trim((string) ($snippet['url'] ?? ''));
            $chunk = trim($title.($text !== '' ? ': '.$text : ''));
            if ($chunk === '') {
                continue;
            }
            $lines[] = $chunk;
            if ($url === null && $snippetUrl !== '') {
                $url = $snippetUrl;
            }
            if (count($lines) >= 3) {
                break;
            }
        }

        if ($lines === []) {
            return null;
        }

        return [
            'text' => implode("\n", array_map(
                static fn (string $line): string => Str::limit($line, 280),
                $lines,
            )),
            'url' => $url,
        ];
    }

    private function assertEnabled(): void
    {
        if (! (bool) config('contractor_enrichment.enabled', true)) {
            throw ValidationException::withMessages([
                'enrichment' => 'Обогащение портрета отключено.',
            ]);
        }
    }
}
