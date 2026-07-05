<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderPortalInviteService
{
    /**
     * @return array{invite: OrderPortalInvite, token: string, url: string}
     */
    public function createCarrierFleetInvite(
        Order $order,
        int $contractorId,
        string $stage,
        int $carrierSlot,
        User $user,
    ): array {
        $normalizedStage = $this->normalizeStageIdentifier($stage);
        $carrierSlot = max(1, min(4, $carrierSlot));

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = now()->addYears(5);

        $invite = DB::transaction(function () use ($order, $contractorId, $normalizedStage, $carrierSlot, $user, $tokenHash, $expiresAt): OrderPortalInvite {
            OrderPortalInvite::query()
                ->where('order_id', $order->id)
                ->where('contractor_id', $contractorId)
                ->where('stage', $normalizedStage)
                ->where('carrier_slot', $carrierSlot)
                ->where('purpose', OrderPortalInvite::PURPOSE_CARRIER_FLEET)
                ->whereNull('revoked_at')
                ->whereNull('used_at')
                ->update(['revoked_at' => now()]);

            return OrderPortalInvite::query()->create([
                'order_id' => $order->id,
                'contractor_id' => $contractorId,
                'stage' => $normalizedStage,
                'carrier_slot' => $carrierSlot,
                'purpose' => OrderPortalInvite::PURPOSE_CARRIER_FLEET,
                'token_hash' => $tokenHash,
                'created_by' => $user->id,
                'expires_at' => $expiresAt,
            ]);
        });

        return [
            'invite' => $invite,
            'token' => $token,
            'url' => route('portal.carrier.show', ['token' => $token]),
        ];
    }

    /**
     * @return array{invite: OrderPortalInvite, token: string, url: string}
     */
    public function createCustomerDocumentsInvite(Order $order, User $user): array
    {
        $contractorId = (int) $order->customer_id;
        if ($contractorId <= 0) {
            throw new \InvalidArgumentException('У заказа не указан заказчик.');
        }

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);

        $invite = DB::transaction(function () use ($order, $contractorId, $user, $tokenHash): OrderPortalInvite {
            OrderPortalInvite::query()
                ->where('order_id', $order->id)
                ->where('contractor_id', $contractorId)
                ->where('stage', 'customer')
                ->where('purpose', OrderPortalInvite::PURPOSE_CUSTOMER_DOCUMENTS)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return OrderPortalInvite::query()->create([
                'order_id' => $order->id,
                'contractor_id' => $contractorId,
                'stage' => 'customer',
                'carrier_slot' => 1,
                'purpose' => OrderPortalInvite::PURPOSE_CUSTOMER_DOCUMENTS,
                'token_hash' => $tokenHash,
                'created_by' => $user->id,
                'expires_at' => now()->addYears(5),
            ]);
        });

        return [
            'invite' => $invite,
            'token' => $token,
            'url' => route('portal.customer.show', ['token' => $token]),
        ];
    }

    public function resolveByToken(string $token): ?OrderPortalInvite
    {
        return $this->resolveByTokenForPurpose($token, OrderPortalInvite::PURPOSE_CARRIER_FLEET);
    }

    public function resolveCustomerByToken(string $token): ?OrderPortalInvite
    {
        return $this->resolveByTokenForPurpose($token, OrderPortalInvite::PURPOSE_CUSTOMER_DOCUMENTS);
    }

    private function resolveByTokenForPurpose(string $token, string $purpose): ?OrderPortalInvite
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return OrderPortalInvite::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('purpose', $purpose)
            ->first();
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateToken(): string
    {
        return Str::lower(Str::random(48));
    }

    public function normalizeStageIdentifier(string $stage): string
    {
        $stage = trim($stage);
        if ($stage === '') {
            return 'leg_1';
        }

        if (preg_match('/^leg_(\d+)$/i', $stage, $matches) === 1) {
            return 'leg_'.(int) $matches[1];
        }

        if (preg_match('/^\d+$/', $stage) === 1) {
            return 'leg_'.$stage;
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $stage, $matches) === 1) {
            return 'leg_'.(int) $matches[1];
        }

        if (preg_match('/^плечо\s*(\d+)$/ui', $stage, $matches) === 1) {
            return 'leg_'.(int) $matches[1];
        }

        return $stage;
    }

    public function isContractorAssignedOnOrder(Order $order, int $contractorId, string $stage, int $carrierSlot): bool
    {
        $stage = $this->normalizeStageIdentifier($stage);
        $carrierSlot = max(1, min(4, $carrierSlot));

        $rows = $this->expandPerformerContractorRows(is_array($order->performers) ? $order->performers : []);
        $wizardPerformers = $this->wizardStatePerformers($order);

        if ($wizardPerformers !== []) {
            $rows = array_merge($rows, $this->expandPerformerContractorRows($wizardPerformers));
        }

        if ($rows === [] && Schema::hasTable('order_legs')) {
            $order->loadMissing(['legs.contractorAssignments']);

            foreach ($order->legs as $leg) {
                $legStage = $this->normalizeStageIdentifier((string) $leg->description);

                foreach ($leg->contractorAssignments as $assignment) {
                    $assignmentContractorId = (int) $assignment->contractor_id;
                    if ($assignmentContractorId <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'stage' => $legStage,
                        'carrier_slot' => max(1, (int) ($assignment->carrier_slot ?? 1)),
                        'contractor_id' => $assignmentContractorId,
                    ];
                }
            }
        }

        if ($rows === [] && Schema::hasTable('financial_terms')) {
            $order->loadMissing('financialTerms');
            $financialTerm = $order->financialTerms->first();
            $costs = is_array($financialTerm?->contractors_costs) ? $financialTerm->contractors_costs : [];

            foreach ($costs as $cost) {
                if (! is_array($cost)) {
                    continue;
                }

                $costContractorId = (int) ($cost['contractor_id'] ?? 0);
                if ($costContractorId <= 0) {
                    continue;
                }

                $rows[] = [
                    'stage' => $this->normalizeStageIdentifier((string) ($cost['stage'] ?? 'leg_1')),
                    'carrier_slot' => max(1, (int) ($cost['carrier_slot'] ?? 1)),
                    'contractor_id' => $costContractorId,
                ];
            }
        }

        foreach ($rows as $row) {
            if ($row['stage'] !== $stage || $row['carrier_slot'] !== $carrierSlot) {
                continue;
            }

            if ($row['contractor_id'] === $contractorId) {
                return true;
            }
        }

        return (int) $order->carrier_id === $contractorId && $stage === 'leg_1' && $carrierSlot === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function wizardStatePerformers(Order $order): array
    {
        $state = $order->wizard_state;

        if (! is_array($state)) {
            $raw = $order->getAttributes()['wizard_state'] ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $state = is_array($decoded) ? $decoded : null;
            } elseif (is_array($raw)) {
                $state = $raw;
            }
        }

        if (! is_array($state)) {
            return [];
        }

        $performers = $state['performers'] ?? [];

        return is_array($performers) ? $performers : [];
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array{stage: string, carrier_slot: int, contractor_id: int}>
     */
    public function expandPerformerContractorRows(array $performers): array
    {
        $rows = [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $stage = $this->normalizeStageIdentifier((string) ($performer['stage'] ?? ''));

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $contractorId = (int) ($slot['contractor_id'] ?? 0);
                    if ($contractorId <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'stage' => $stage,
                        'carrier_slot' => max(1, (int) ($slot['slot'] ?? 1)),
                        'contractor_id' => $contractorId,
                    ];
                }

                continue;
            }

            $contractorId = (int) ($performer['contractor_id'] ?? 0);
            if ($contractorId <= 0) {
                continue;
            }

            $rows[] = [
                'stage' => $stage,
                'carrier_slot' => 1,
                'contractor_id' => $contractorId,
            ];
        }

        return $rows;
    }
}
