<?php

declare(strict_types=1);

namespace App\Services\LoadingPlanner;

use App\Models\Cargo;
use App\Models\Lead;
use App\Models\LeadCargoItem;
use App\Models\LoadingPlannerProject;
use App\Models\Order;
use Illuminate\Support\Str;

final class LoadingPlannerCargoSeedService
{
    public function seedFromLead(LoadingPlannerProject $project, Lead $lead): void
    {
        $lead->loadMissing(['cargoItems', 'counterparty']);

        if ($lead->cargoItems->isEmpty()) {
            $this->createDefaultGroup($project);

            return;
        }

        $group = $project->cargoGroups()->create([
            'name' => 'Грузовая группа #1',
            'recipient_name' => $lead->counterparty?->name ?? 'Получатель без названия',
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);

        foreach ($lead->cargoItems->values() as $index => $cargoItem) {
            $group->items()->create($this->mapLeadCargoItem($cargoItem, $index + 1));
        }
    }

    public function seedFromOrder(LoadingPlannerProject $project, Order $order): void
    {
        $order->loadMissing(['cargoItems', 'customer']);

        if ($order->cargoItems->isEmpty()) {
            $this->createDefaultGroup($project);

            return;
        }

        $group = $project->cargoGroups()->create([
            'name' => 'Грузовая группа #1',
            'recipient_name' => $order->customer?->name ?? 'Получатель без названия',
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);

        foreach ($order->cargoItems->values() as $index => $cargoItem) {
            $group->items()->create($this->mapOrderCargoItem($cargoItem, $index + 1));
        }
    }

    private function createDefaultGroup(LoadingPlannerProject $project): void
    {
        $group = $project->cargoGroups()->create([
            'name' => 'Грузовая группа #1',
            'recipient_name' => 'Получатель без названия',
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);

        $group->items()->create([
            'client_key' => (string) Str::uuid(),
            'name' => 'Новый груз',
            'package_type' => 'box',
            'quantity' => 1,
            'length_mm' => 1200,
            'width_mm' => 800,
            'height_mm' => 1000,
            'weight_kg' => 100,
            'can_rotate' => true,
            'stackable' => false,
            'max_stack' => 5,
            'can_tilt' => false,
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLeadCargoItem(LeadCargoItem $cargoItem, int $sortOrder): array
    {
        $metadata = is_array($cargoItem->metadata) ? $cargoItem->metadata : [];

        return [
            'client_key' => (string) Str::uuid(),
            'name' => trim((string) ($cargoItem->name ?: 'Груз')) ?: 'Груз',
            'package_type' => $this->mapPackageType($cargoItem->package_type),
            'quantity' => max(1, (int) ($cargoItem->package_count ?? 1)),
            'length_mm' => $this->metersToMm($metadata['length_m'] ?? null, 1200),
            'width_mm' => $this->metersToMm($metadata['width_m'] ?? null, 800),
            'height_mm' => $this->metersToMm($metadata['height_m'] ?? null, 1000),
            'weight_kg' => max(0, (float) ($cargoItem->weight_kg ?? 0)),
            'can_rotate' => ! (bool) ($metadata['is_oversized'] ?? $cargoItem->cargo_type === 'oversized'),
            'stackable' => false,
            'max_stack' => 5,
            'can_tilt' => false,
            'color' => '#8b5cf6',
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOrderCargoItem(Cargo $cargoItem, int $sortOrder): array
    {
        return [
            'client_key' => (string) Str::uuid(),
            'name' => trim((string) ($cargoItem->title ?: $cargoItem->ati_cargo_name ?: 'Груз')) ?: 'Груз',
            'package_type' => $this->mapPackageType($cargoItem->packing_type),
            'quantity' => max(1, (int) ($cargoItem->package_count ?? $cargoItem->pallet_count ?? 1)),
            'length_mm' => $this->metersToMm($cargoItem->length, 1200),
            'width_mm' => $this->metersToMm($cargoItem->width, 800),
            'height_mm' => $this->metersToMm($cargoItem->height, 1000),
            'weight_kg' => max(0, (float) ($cargoItem->weight_value ?? $cargoItem->weight ?? 0)),
            'can_rotate' => ! (bool) $cargoItem->is_oversized,
            'stackable' => false,
            'max_stack' => 5,
            'can_tilt' => false,
            'color' => '#8b5cf6',
            'sort_order' => $sortOrder,
        ];
    }

    private function mapPackageType(?string $packageType): string
    {
        $normalized = strtolower(trim((string) $packageType));

        return match ($normalized) {
            'pallet', 'pallet_eur', 'eur_pallet' => 'pallet',
            'crate', 'box', 'bag', 'roll', 'barrel' => $normalized,
            default => 'box',
        };
    }

    private function metersToMm(mixed $value, int $fallback): int
    {
        $meters = is_numeric($value) ? (float) $value : 0.0;

        if ($meters <= 0) {
            return $fallback;
        }

        return max(1, (int) round($meters * 1000));
    }
}
