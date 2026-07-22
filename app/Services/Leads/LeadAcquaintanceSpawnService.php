<?php

namespace App\Services\Leads;

use App\Models\BusinessProcess;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadBusinessProcessService;
use App\Support\LeadStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LeadAcquaintanceSpawnService
{
    public const SOURCE_PROCESS_SLUG = 'client-acquaintance';

    public const TARGET_PROCESS_SLUG = 'transport-intake';

    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
    ) {}

    public function spawnTransportLead(Lead $parent, User $user): Lead
    {
        $parent->loadMissing(['businessProcess', 'businessProcessStage']);

        if ($parent->businessProcess?->slug !== self::SOURCE_PROCESS_SLUG) {
            throw new InvalidArgumentException('Конверсия в перевозку доступна только для процесса «Знакомство с клиентом».');
        }

        if ($parent->counterparty_id === null) {
            throw new InvalidArgumentException('Сначала выберите контрагента.');
        }

        $metadata = is_array($parent->metadata) ? $parent->metadata : [];
        $existingChildId = $metadata['spawned_transport_lead_id'] ?? null;
        if (is_numeric($existingChildId)) {
            $existing = Lead::query()->find((int) $existingChildId);
            if ($existing instanceof Lead) {
                return $existing;
            }
        }

        $targetProcess = BusinessProcess::query()
            ->where('slug', self::TARGET_PROCESS_SLUG)
            ->where('is_active', true)
            ->first();

        if ($targetProcess === null) {
            throw new InvalidArgumentException('Процесс «Получение деталей по перевозке» не найден.');
        }

        return DB::transaction(function () use ($parent, $user, $metadata, $targetProcess): Lead {
            $qualification = is_array($parent->lead_qualification) ? $parent->lead_qualification : [];
            $capture = is_array($metadata['sales_script_capture'] ?? null) ? $metadata['sales_script_capture'] : [];
            $profile = is_array($metadata['acquaintance_profile'] ?? null) ? $metadata['acquaintance_profile'] : [];

            $child = Lead::query()->create([
                'number' => $this->nextLeadNumber(),
                'status' => LeadStatus::values()[0],
                'source' => 'acquaintance_spawn',
                'counterparty_id' => $parent->counterparty_id,
                'responsible_id' => $parent->responsible_id ?: $user->id,
                'title' => $this->childTitle($parent, $capture, $profile),
                'description' => $this->childDescription($parent, $capture, $profile),
                'loading_location' => $parent->loading_location ?: ($capture['route_from'] ?? null),
                'unloading_location' => $parent->unloading_location ?: ($capture['route_to'] ?? null),
                'planned_shipping_date' => $parent->planned_shipping_date,
                'lead_qualification' => $qualification,
                'metadata' => [
                    'spawned_from_lead_id' => $parent->id,
                    'spawned_from_lead_number' => $parent->number,
                    'acquaintance_profile' => $profile,
                    'sales_script_capture' => $capture,
                ],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->leadBusinessProcessService->startProcess($child, $targetProcess, $user);

            $wonStage = $parent->businessProcess?->stages()
                ->where('is_terminal', true)
                ->where('terminal_outcome', 'won')
                ->orderBy('sequence')
                ->first();

            if ($wonStage !== null && (int) $parent->business_process_stage_id !== (int) $wonStage->id) {
                $this->leadBusinessProcessService->moveLeadToStage($parent, $wonStage, $user);
            } else {
                $parent->forceFill([
                    'status' => 'won',
                    'updated_by' => $user->id,
                ])->save();
            }

            $parentMetadata = is_array($parent->metadata) ? $parent->metadata : [];
            $parent->forceFill([
                'metadata' => array_merge($parentMetadata, [
                    'spawned_transport_lead_id' => $child->id,
                    'spawned_transport_lead_number' => $child->number,
                ]),
                'updated_by' => $user->id,
            ])->save();

            if (Schema::hasTable('lead_activities')) {
                $parent->activities()->create([
                    'type' => 'status_change',
                    'subject' => 'Конверсия в перевозку',
                    'content' => 'Создан лид по перевозке '.$child->number.' из знакомства.',
                    'created_by' => $user->id,
                ]);

                $child->activities()->create([
                    'type' => 'note',
                    'subject' => 'Из знакомства',
                    'content' => 'Лид создан из знакомства '.$parent->number.'.',
                    'created_by' => $user->id,
                ]);
            }

            return $child->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $capture
     * @param  array<string, mixed>  $profile
     */
    private function childTitle(Lead $parent, array $capture, array $profile): string
    {
        $route = trim(implode(' → ', array_filter([
            $parent->loading_location ?: ($capture['route_from'] ?? null),
            $parent->unloading_location ?: ($capture['route_to'] ?? null),
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        if ($route !== '') {
            return Str::limit($route, 255, '');
        }

        $routes = trim((string) ($profile['routes'] ?? $capture['routes'] ?? ''));
        if ($routes !== '') {
            return Str::limit($routes, 255, '');
        }

        return Str::limit('Перевозка: '.$parent->title, 255, '');
    }

    /**
     * @param  array<string, mixed>  $capture
     * @param  array<string, mixed>  $profile
     */
    private function childDescription(Lead $parent, array $capture, array $profile): string
    {
        $lines = [
            'Создано из знакомства '.$parent->number.'.',
        ];

        if (filled($parent->description)) {
            $lines[] = trim((string) $parent->description);
        }

        foreach (['cargo_type' => 'Груз', 'volume_forecast' => 'Объём', 'decision_criteria' => 'Критерии', 'payment_terms' => 'Оплата'] as $code => $label) {
            $value = $capture[$code] ?? $profile[$code] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $lines[] = $label.': '.trim($value);
            }
        }

        return Str::limit(implode("\n", $lines), 5000, '');
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
