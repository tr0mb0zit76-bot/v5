<?php

namespace App\Services\ExternalUsers;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\ExternalUserInvite;
use App\Models\Role;
use App\Models\User;
use App\Support\CounterpartyPartyResolver;
use App\Support\ExternalParty;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ExternalUserProvisionService
{
    public function __construct(
        private readonly ExternalUserInviteService $inviteService,
    ) {}

    /**
     * @return array{
     *     user: User,
     *     invite: ExternalUserInvite,
     *     token: string,
     *     url: string,
     *     created: bool
     * }
     */
    public function provisionInvite(
        Contractor $contractor,
        ContractorContact $contact,
        User $staff,
        ?string $requestedParty = null,
    ): array {
        $this->assertContactBelongsToContractor($contractor, $contact);
        $this->assertContactHasEmail($contact);

        $party = CounterpartyPartyResolver::resolveForContractor($contractor, $requestedParty);

        $existingStaff = User::query()
            ->where('email', $contact->email)
            ->where(function ($query): void {
                if (Schema::hasColumn('users', 'is_external')) {
                    $query->where('is_external', false)->orWhereNull('is_external');
                }
            })
            ->first();

        if ($existingStaff !== null) {
            throw ValidationException::withMessages([
                'email' => 'Этот email уже используется сотрудником CRM.',
            ]);
        }

        $role = $this->resolveRoleForParty($party);

        $created = false;

        $user = User::query()
            ->when(
                Schema::hasColumn('users', 'contractor_contact_id'),
                fn ($query) => $query->where('contractor_contact_id', $contact->id),
            )
            ->first();

        if ($user === null) {
            $user = User::query()->where('email', $contact->email)->first();
        }

        if ($user === null) {
            $created = true;
            $user = User::query()->create([
                'name' => trim((string) $contact->full_name) !== '' ? $contact->full_name : $contact->email,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'password' => null,
                'role_id' => $role->id,
                'email_verified_at' => now(),
                'is_active' => true,
                'is_external' => true,
                'contractor_id' => $contractor->id,
                'contractor_contact_id' => $contact->id,
                'external_party' => $party->value,
                'mail_sync_enabled' => false,
                'can_management_accounting' => false,
                'belongs_to_management' => false,
                'sees_company_dashboard' => false,
                'has_signing_authority' => false,
            ]);

            RoleAccess::syncUserRoles($user, [$role->id]);
        } else {
            $this->assertExistingExternalUserMatches($user, $contractor, $contact, $party);
            $user->forceFill([
                'is_active' => true,
                'is_external' => true,
                'contractor_id' => $contractor->id,
                'contractor_contact_id' => $contact->id,
                'external_party' => $party->value,
                'role_id' => $role->id,
                'mail_sync_enabled' => false,
            ])->save();

            RoleAccess::syncUserRoles($user, [$role->id]);
        }

        $invitePayload = $this->inviteService->createInvite(
            $contact,
            $party->value,
            $staff,
            $user,
        );

        return [
            'user' => $user->fresh(['role']),
            'invite' => $invitePayload['invite'],
            'token' => $invitePayload['token'],
            'url' => $invitePayload['url'],
            'created' => $created,
        ];
    }

    public function setTrakloPrimary(Contractor $contractor, ContractorContact $contact): void
    {
        $this->assertContactBelongsToContractor($contractor, $contact);

        DB::transaction(function () use ($contractor, $contact): void {
            $contractor->contacts()
                ->whereKeyNot($contact->id)
                ->update(['is_traklo_primary' => false]);

            $contact->forceFill(['is_traklo_primary' => true])->save();
        });
    }

    private function assertContactBelongsToContractor(Contractor $contractor, ContractorContact $contact): void
    {
        if ((int) $contact->contractor_id !== (int) $contractor->id) {
            throw ValidationException::withMessages([
                'contractor_contact_id' => 'Контакт не принадлежит этому контрагенту.',
            ]);
        }
    }

    private function assertContactHasEmail(ContractorContact $contact): void
    {
        if (! filled($contact->email)) {
            throw ValidationException::withMessages([
                'email' => 'У контакта должен быть указан email для доступа в Traklo.',
            ]);
        }
    }

    private function assertExistingExternalUserMatches(
        User $user,
        Contractor $contractor,
        ContractorContact $contact,
        ExternalParty $party,
    ): void {
        if (! $user->isExternal()) {
            throw ValidationException::withMessages([
                'email' => 'Этот email уже используется внутренним пользователем CRM.',
            ]);
        }

        if (
            $user->contractor_contact_id !== null
            && (int) $user->contractor_contact_id !== (int) $contact->id
        ) {
            throw ValidationException::withMessages([
                'email' => 'Email уже привязан к другому контакту Traklo.',
            ]);
        }

        if ($user->external_party !== null && $user->external_party !== $party->value) {
            throw ValidationException::withMessages([
                'external_party' => 'Пользователь уже создан как '.$user->external_party.'.',
            ]);
        }
    }

    private function resolveRoleForParty(ExternalParty $party): Role
    {
        $roleName = match ($party) {
            ExternalParty::Carrier => 'counterparty_carrier',
            ExternalParty::Customer => 'counterparty_customer',
        };

        $role = Role::query()->where('name', $roleName)->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => 'Роль '.$roleName.' не найдена. Выполните миграции.',
            ]);
        }

        return $role;
    }
}
