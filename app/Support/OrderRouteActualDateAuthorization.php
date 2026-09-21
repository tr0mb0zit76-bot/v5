<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Фактическая выгрузка: первую дату может поставить любой с правом правки заказа;
 * изменить или снять уже проставленную — только admin / supervisor.
 */
final class OrderRouteActualDateAuthorization
{
    public static function userMayChangeExistingUnloadingActual(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isAdmin() || $user->isSupervisor();
    }

    public static function assertCanChangeUnloadingActual(
        ?User $user,
        mixed $existingDate,
        mixed $incomingDate,
    ): void {
        $existing = PerformerRouteActualDates::normalizeDate($existingDate);
        $incoming = PerformerRouteActualDates::normalizeDate($incomingDate);

        if ($existing === null || $existing === $incoming) {
            return;
        }

        if (self::userMayChangeExistingUnloadingActual($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'unloading_actual' => 'Фактическую дату выгрузки после проставления может изменить только руководитель или администратор.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function assertWizardPayloadMayChangeUnloadingActuals(
        ?User $user,
        Order $order,
        array $validated,
    ): void {
        if (self::userMayChangeExistingUnloadingActual($user)) {
            return;
        }

        $existingByStage = self::existingUnloadingActualByStage($order);
        $incomingPerformers = is_array($validated['performers'] ?? null) ? $validated['performers'] : [];

        foreach ($incomingPerformers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $stage = PerformerRouteActualDates::normalizeStageKey((string) ($performer['stage'] ?? 'leg_1'));
            $existing = $existingByStage[$stage] ?? null;

            if (PerformerRouteActualDates::isSplitPerformer($performer)) {
                $incomingUnloadings = [];

                foreach ($performer['split_carriers'] ?? [] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $slotDate = PerformerRouteActualDates::normalizeDate($slot['unloading_actual'] ?? null);
                    if ($slotDate !== null) {
                        $incomingUnloadings[] = $slotDate;
                    }
                }

                $incoming = $incomingUnloadings === [] ? null : max($incomingUnloadings);
                self::assertCanChangeUnloadingActual($user, $existing, $incoming);

                continue;
            }

            self::assertCanChangeUnloadingActual(
                $user,
                $existing,
                $performer['unloading_actual'] ?? null,
            );
        }
    }

    /**
     * @return array<string, string|null>
     */
    public static function existingUnloadingActualByStage(Order $order): array
    {
        $order->loadMissing([
            'legs' => fn ($query) => $query->orderBy('sequence'),
            'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
        ]);

        $byStage = [];

        foreach ($order->legs as $leg) {
            $stage = PerformerRouteActualDates::normalizeStageKey((string) ($leg->description ?? 'leg_1'));
            $unloadingPoint = $leg->routePoints
                ->filter(fn ($point): bool => $point->type === 'unloading')
                ->sortBy('sequence')
                ->last();

            $byStage[$stage] = optional($unloadingPoint?->actual_date)?->toDateString();
        }

        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $stage = PerformerRouteActualDates::normalizeStageKey((string) ($performer['stage'] ?? 'leg_1'));

            if (($byStage[$stage] ?? null) !== null) {
                continue;
            }

            foreach (PerformerRouteActualDates::executorDateRows($performer) as $row) {
                if ($row['unloading'] !== null) {
                    $byStage[$stage] = $row['unloading'];
                    break;
                }
            }
        }

        return $byStage;
    }
}
