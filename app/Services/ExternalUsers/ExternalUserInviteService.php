<?php

namespace App\Services\ExternalUsers;

use App\Models\ContractorContact;
use App\Models\ExternalUserInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExternalUserInviteService
{
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateToken(): string
    {
        return Str::lower(Str::random(48));
    }

    /**
     * @return array{invite: ExternalUserInvite, token: string, url: string}
     */
    public function createInvite(
        ContractorContact $contact,
        string $externalParty,
        User $createdBy,
        ?User $linkedUser = null,
    ): array {
        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = now()->addDays(max(1, (int) config('external_users.invite_ttl_days', 14)));

        $invite = DB::transaction(function () use ($contact, $externalParty, $createdBy, $linkedUser, $tokenHash, $expiresAt): ExternalUserInvite {
            ExternalUserInvite::query()
                ->where('contractor_contact_id', $contact->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return ExternalUserInvite::query()->create([
                'contractor_contact_id' => $contact->id,
                'contractor_id' => $contact->contractor_id,
                'external_party' => $externalParty,
                'token_hash' => $tokenHash,
                'created_by' => $createdBy->id,
                'user_id' => $linkedUser?->id,
                'expires_at' => $expiresAt,
            ]);
        });

        return [
            'invite' => $invite,
            'token' => $token,
            'url' => route('external.invite.show', ['token' => $token]),
        ];
    }

    public function resolveByToken(string $token): ?ExternalUserInvite
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return ExternalUserInvite::query()
            ->with(['contact', 'contractor', 'user'])
            ->where('token_hash', $this->hashToken($token))
            ->first();
    }
}
