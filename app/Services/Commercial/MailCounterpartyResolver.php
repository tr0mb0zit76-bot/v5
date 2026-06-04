<?php

namespace App\Services\Commercial;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Lead;
use Illuminate\Support\Facades\Schema;

final class MailCounterpartyResolver
{
    public function resolveContractorIdByEmail(string $email): ?int
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (Schema::hasTable('contractors')) {
            $contractorId = Contractor::query()
                ->where(function ($query) use ($normalized): void {
                    $query->whereRaw('LOWER(email) = ?', [$normalized]);

                    if (Schema::hasColumn('contractors', 'contact_person_email')) {
                        $query->orWhereRaw('LOWER(contact_person_email) = ?', [$normalized]);
                    }
                })
                ->value('id');

            if ($contractorId !== null) {
                return (int) $contractorId;
            }
        }

        if (Schema::hasTable('contractor_contacts') && Schema::hasColumn('contractor_contacts', 'email')) {
            $contactContractorId = ContractorContact::query()
                ->whereRaw('LOWER(email) = ?', [$normalized])
                ->value('contractor_id');

            if ($contactContractorId !== null) {
                return (int) $contactContractorId;
            }
        }

        return null;
    }

    public function resolveOpenLeadId(?int $contractorId): ?int
    {
        if ($contractorId === null || $contractorId <= 0 || ! Schema::hasTable('leads')) {
            return null;
        }

        $leadId = Lead::query()
            ->where('counterparty_id', $contractorId)
            ->whereNotIn('status', ['won', 'lost'])
            ->orderByDesc('updated_at')
            ->value('id');

        return $leadId !== null ? (int) $leadId : null;
    }

    /**
     * @param  list<string>  $emails
     */
    public function resolveContractorIdFromParticipants(array $emails, string $mailboxEmail): ?int
    {
        $mailbox = strtolower(trim($mailboxEmail));

        foreach ($emails as $email) {
            $normalized = strtolower(trim($email));

            if ($normalized === '' || $normalized === $mailbox) {
                continue;
            }

            $contractorId = $this->resolveContractorIdByEmail($normalized);

            if ($contractorId !== null) {
                return $contractorId;
            }
        }

        return null;
    }
}
