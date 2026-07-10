<?php

namespace App\Services\SalesScripts;

use App\Models\Lead;
use App\Models\SalesScriptPlaySession;
use App\Models\User;
use App\Support\LeadStatus;
use App\Support\LeadViewAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SalesScriptLeadLinkService
{
    public function __construct(
        private readonly SalesScriptCrmActionService $crmActionService,
    ) {}

    /**
     * @return list<array{id: int, number: string, title: string, responsible_name: string|null}>
     */
    public function search(User $user, string $query): array
    {
        $normalized = trim($query);
        if (mb_strlen($normalized) < 2) {
            return [];
        }

        return Lead::query()
            ->with('responsible:id,name')
            ->tap(fn (Builder $builder): Builder => LeadViewAuthorization::applyLeadsVisibilityScope($builder, $user))
            ->where(function (Builder $builder) use ($normalized): void {
                $builder
                    ->where('number', 'like', '%'.$normalized.'%')
                    ->orWhere('title', 'like', '%'.$normalized.'%');
            })
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get(['id', 'number', 'title', 'responsible_id'])
            ->map(fn (Lead $lead): array => [
                'id' => (int) $lead->id,
                'number' => (string) $lead->number,
                'title' => (string) $lead->title,
                'responsible_name' => $lead->responsible?->name,
            ])
            ->all();
    }

    public function link(SalesScriptPlaySession $session, Lead $lead, User $user): SalesScriptPlaySession
    {
        $this->assertCompleted($session);
        $this->assertCanUseLead($lead, $user);

        $session->forceFill([
            'lead_id' => $lead->id,
            'contractor_id' => $session->contractor_id ?? $lead->counterparty_id,
            'crm_synced_at' => null,
        ])->save();

        $this->crmActionService->syncAfterCompletion($session->fresh());

        return $session->fresh(['lead']);
    }

    public function create(SalesScriptPlaySession $session, User $user, ?string $title = null): Lead
    {
        $this->assertCompleted($session);

        if ($session->lead_id !== null) {
            throw new InvalidArgumentException('Сессия уже связана с лидом.');
        }

        $session->loadMissing(['fieldValues.captureField', 'version.script']);
        $fields = $this->fieldValuesByCode($session);

        $lead = DB::transaction(function () use ($session, $user, $title, $fields): Lead {
            $lead = Lead::query()->create([
                'number' => $this->nextLeadNumber(),
                'status' => LeadStatus::values()[0],
                'source' => 'sales_script_play',
                'counterparty_id' => $session->contractor_id,
                'responsible_id' => $user->id,
                'title' => $this->resolveTitle($session, $fields, $title),
                'description' => $this->description($session, $fields),
                'loading_location' => $fields['route_from'] ?? null,
                'unloading_location' => $fields['route_to'] ?? null,
                'planned_shipping_date' => $this->dateValue($fields['loading_date'] ?? null),
                'lead_qualification' => [
                    'need' => $fields['cargo_type'] ?? null,
                    'timeline' => $fields['decision_deadline'] ?? null,
                ],
                'metadata' => [
                    'sales_script_play' => [
                        'session_id' => $session->id,
                        'script_title' => $session->version?->script?->title,
                        'captured_fields' => $fields,
                    ],
                ],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $session->forceFill([
                'lead_id' => $lead->id,
                'crm_synced_at' => null,
            ])->save();

            return $lead;
        });

        $this->crmActionService->syncAfterCompletion($session->fresh());

        return $lead;
    }

    private function assertCanUseLead(Lead $lead, User $user): void
    {
        if (! LeadViewAuthorization::userCanViewLead($user, $lead)) {
            throw new InvalidArgumentException('Нет доступа к выбранному лиду.');
        }
    }

    private function assertCompleted(SalesScriptPlaySession $session): void
    {
        if (! $session->isComplete()) {
            throw new InvalidArgumentException('Сначала завершите разговор и зафиксируйте исход.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function fieldValuesByCode(SalesScriptPlaySession $session): array
    {
        $values = [];

        foreach ($session->fieldValues as $fieldValue) {
            $code = $fieldValue->captureField?->code;
            $value = trim((string) $fieldValue->value);

            if (is_string($code) && $code !== '' && $value !== '') {
                $values[$code] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function resolveTitle(
        SalesScriptPlaySession $session,
        array $fields,
        ?string $requestedTitle,
    ): string {
        if (filled($requestedTitle)) {
            return Str::limit(trim((string) $requestedTitle), 255, '');
        }

        $route = trim(implode(' → ', array_filter([
            $fields['route_from'] ?? null,
            $fields['route_to'] ?? null,
        ])));

        return Str::limit(
            $route !== ''
                ? $route
                : ($fields['client_name'] ?? 'Разговор: '.($session->version?->script?->title ?? 'скрипт')),
            255,
            '',
        );
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function description(SalesScriptPlaySession $session, array $fields): string
    {
        $lines = [
            'Лид создан после прохождения скрипта «'.($session->version?->script?->title ?? '—').'».',
        ];

        foreach ($fields as $code => $value) {
            $lines[] = '- '.str_replace('_', ' ', $code).': '.$value;
        }

        if (filled($session->notes)) {
            $lines[] = 'Итог: '.trim((string) $session->notes);
        }

        return Str::limit(implode("\n", $lines), 5000, '');
    }

    private function dateValue(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nextLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd');
        $sequence = DB::table('leads')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
