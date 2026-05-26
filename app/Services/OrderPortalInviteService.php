<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $expiresAt = now()->addDays(max(1, (int) config('portal.carrier_invite_ttl_days', 14)));

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

    public function resolveByToken(string $token): ?OrderPortalInvite
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return OrderPortalInvite::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('purpose', OrderPortalInvite::PURPOSE_CARRIER_FLEET)
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

        if (preg_match('/^leg_\d+$/', $stage) === 1) {
            return $stage;
        }

        if (preg_match('/^\d+$/', $stage) === 1) {
            return 'leg_'.$stage;
        }

        return $stage;
    }
}
