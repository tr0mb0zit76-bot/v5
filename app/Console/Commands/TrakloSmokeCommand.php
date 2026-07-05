<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\ExternalUsers\CounterpartyConversationService;
use App\Services\ExternalUsers\ExternalUserProvisionService;
use App\Services\OrderPortalInviteService;
use App\Support\ExternalParty;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class TrakloSmokeCommand extends Command
{
    protected $signature = 'traklo:smoke {--url= : Base URL for HTTP checks (default APP_URL)}';

    protected $description = 'Smoke-проверка Traklo / external users (prod-safe: транзакционные сценарии откатываются)';

    /** @var list<array{check: string, status: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $baseUrl = rtrim((string) ($this->option('url') ?: config('app.url')), '/');

        $this->info('Traklo smoke — '.$baseUrl);
        $this->newLine();

        $this->checkInfrastructure();
        $this->checkPublicHttp($baseUrl);
        $this->checkTransactionalFlows();

        return $this->renderReport();
    }

    private function checkInfrastructure(): void
    {
        $this->section('Infrastructure');

        $this->record('migration users.is_external', Schema::hasColumn('users', 'is_external'));
        $this->record('migration contractor_contacts.is_traklo_primary', Schema::hasColumn('contractor_contacts', 'is_traklo_primary'));
        $this->record('migration external_user_invites table', Schema::hasTable('external_user_invites'));
        $this->record('migration conversations.channel', Schema::hasColumn('conversations', 'channel'));
        $this->record('migration chat_messages.order_id', Schema::hasColumn('chat_messages', 'order_id'));
        $this->record('role counterparty_carrier', Role::query()->where('name', 'counterparty_carrier')->exists());
        $this->record('role counterparty_customer', Role::query()->where('name', 'counterparty_customer')->exists());

        foreach ([
            'mobile.login' => 'GET /mobile/login',
            'external.invite.show' => 'GET /external/invite/{token}',
            'portal.customer.show' => 'GET /portal/customer/{token}',
            'portal.carrier.show' => 'GET /portal/carrier/{token}',
            'public.transport-request.create' => 'GET /transport-request',
            'mobile.shell.counterparty.orders' => 'counterparty orders API',
            'messenger.conversations.open-counterparty' => 'open counterparty thread',
            'orders.portal-invites.customer.store' => 'customer portal invite API',
        ] as $name => $label) {
            $this->record('route '.$label, Route::has($name));
        }
    }

    private function checkPublicHttp(string $baseUrl): void
    {
        $this->section('Public HTTP');

        foreach (['/mobile/login' => 200, '/transport-request' => 200] as $path => $expected) {
            try {
                $response = Http::timeout(20)->get($baseUrl.$path);
                $code = $response->status();
                $ok = $code === $expected;
                $detail = 'HTTP '.$code;
                if ($path === '/transport-request' && $ok) {
                    $body = $response->body();
                    $ok = str_contains($body, 'Public/TransportRequest')
                        || str_contains($body, 'traklo_apk_url')
                        || str_contains($body, 'Traklo');
                    $detail = $ok ? 'HTTP 200 + Inertia TransportRequest' : 'HTTP 200, страница Traklo не распознана';
                }
                $this->record('HTTP '.$path, $ok, $detail);
            } catch (Throwable $exception) {
                $this->record('HTTP '.$path, false, $exception->getMessage());
            }
        }

        try {
            $apk = Http::timeout(20)->head($baseUrl.'/downloads/traklo.apk');
            $this->record(
                'HTTP /downloads/traklo.apk',
                in_array($apk->status(), [200, 302], true),
                'HTTP '.$apk->status(),
            );
        } catch (Throwable $exception) {
            $this->record('HTTP /downloads/traklo.apk', false, $exception->getMessage());
        }

        try {
            $invalidInvite = Http::timeout(20)->get($baseUrl.'/external/invite/'.Str::random(40));
            $this->record(
                'HTTP invalid external invite',
                in_array($invalidInvite->status(), [200, 404, 410], true),
                'HTTP '.$invalidInvite->status().' (not 500)',
            );
        } catch (Throwable $exception) {
            $this->record('HTTP invalid external invite', false, $exception->getMessage());
        }
    }

    private function checkTransactionalFlows(): void
    {
        $this->section('Transactional flows (rollback)');

        $staff = $this->resolveStaffUser();
        if ($staff === null) {
            $this->record('staff user for flows', false, 'Не найден активный staff/admin');

            return;
        }

        DB::beginTransaction();

        try {
            $external = null;
            try {
                $external = $this->runInviteAndExternalAccessFlow($staff);
            } catch (Throwable $exception) {
                $this->record('invite + external access flow', false, $this->formatException($exception));
            }

            try {
                $this->runPortalFlows($staff);
            } catch (Throwable $exception) {
                $this->record('portal flows', false, $this->formatException($exception));
            }

            try {
                $this->runMessengerFlows($staff);
            } catch (Throwable $exception) {
                $this->record('messenger flows', false, $this->formatException($exception));
            }

            if ($external !== null) {
                try {
                    $this->runDeactivationFlow($external);
                } catch (Throwable $exception) {
                    $this->record('deactivation flow', false, $this->formatException($exception));
                }
            }

            $this->record('transaction rollback', true, 'Временные данные не сохранены');
        } finally {
            DB::rollBack();
            Auth::logout();
        }
    }

    private function formatException(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message !== ''
            ? $message
            : class_basename($exception).' @ '.$exception->getFile().':'.$exception->getLine();
    }

    private function runInviteAndExternalAccessFlow(User $staff): ?User
    {
        $suffix = now()->format('His');
        $email = "traklo-smoke-{$suffix}@smoke.avtoaliyans.test";
        $password = 'SmokePass-'.Str::random(10).'9!';

        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => '[SMOKE] Traklo '.$suffix,
        ]);
        $contact = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Smoke Carrier',
            'email' => $email,
            'phone' => '+7900000'.random_int(1000, 9999),
            'is_primary' => true,
            'is_traklo_primary' => true,
        ]);

        $this->record('is_traklo_primary on contact', (bool) $contact->is_traklo_primary);

        $payload = app(ExternalUserProvisionService::class)->provisionInvite($contractor, $contact, $staff);
        $this->record(
            'provision invite-link',
            str_contains($payload['url'], '/external/invite/'),
            $payload['url'],
        );

        $inviteShow = app()->handle(Request::create(
            route('external.invite.show', ['token' => $payload['token']]),
            'GET',
        ));
        $this->record(
            'invite page renders',
            $inviteShow->getStatusCode() === HttpResponse::HTTP_OK,
            'HTTP '.$inviteShow->getStatusCode(),
        );

        $external = $payload['user']->fresh();
        $external?->forceFill([
            'password' => Hash::make($password),
            'is_active' => true,
        ])->save();
        $payload['invite']->forceFill(['consumed_at' => now()])->save();

        $this->record(
            'invite activate + password',
            filled($external?->getRawOriginal('password')),
        );

        if ($external === null) {
            return null;
        }

        $ordersRedirect = $this->dispatchAuthenticatedGet('/orders', $external, [
            'Accept' => 'text/html',
        ]);
        $location = (string) $ordersRedirect->headers->get('Location');
        $this->record(
            'external /orders blocked',
            in_array($ordersRedirect->getStatusCode(), [HttpResponse::HTTP_FOUND, HttpResponse::HTTP_FORBIDDEN], true)
                || str_contains($location, 'mobile/messenger'),
            'HTTP '.$ordersRedirect->getStatusCode().($location !== '' ? ' → '.$location : ''),
        );

        $counterpartyOrders = $this->dispatchAuthenticatedGet(route('mobile.shell.counterparty.orders'), $external, [
            'Accept' => 'application/json',
        ]);
        $this->record(
            'external counterparty orders API',
            $counterpartyOrders->getStatusCode() === HttpResponse::HTTP_OK,
            'HTTP '.$counterpartyOrders->getStatusCode(),
        );

        $this->smokePassword = $password;

        return $external;
    }

    private ?string $smokePassword = null;

    private function runPortalFlows(User $staff): void
    {
        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => '[SMOKE] Customer portal',
        ]);
        $order = Order::query()->create([
            'order_number' => 'SMK-C-'.Str::upper(Str::random(4)),
            'company_code' => 'SMK',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
            'customer_id' => $customer->id,
            'manager_id' => $staff->id,
        ]);

        $customerInvite = app(OrderPortalInviteService::class)->createCustomerDocumentsInvite($order, $staff);
        $customerPage = app()->handle(Request::create(
            route('portal.customer.show', ['token' => $customerInvite['token']]),
            'GET',
        ));
        $this->record(
            'customer portal page',
            $customerPage->getStatusCode() === HttpResponse::HTTP_OK,
            'HTTP '.$customerPage->getStatusCode(),
        );

        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => '[SMOKE] Carrier portal',
        ]);
        if (Schema::hasColumn('orders', 'performers')) {
            $order->forceFill([
                'performers' => [
                    ['contractor_id' => $carrier->id, 'stage' => 'leg_1'],
                ],
            ])->save();
        }

        $carrierInvite = app(OrderPortalInviteService::class)->createCarrierFleetInvite(
            $order,
            $carrier->id,
            'leg_1',
            1,
            $staff,
        );
        $carrierPage = app()->handle(Request::create(
            route('portal.carrier.show', ['token' => $carrierInvite['token']]),
            'GET',
        ));
        $this->record(
            'carrier portal page',
            $carrierPage->getStatusCode() === HttpResponse::HTTP_OK,
            'HTTP '.$carrierPage->getStatusCode(),
        );
    }

    private function runMessengerFlows(User $staff): void
    {
        [$carrierContractor, $carrierExternal] = $this->createExternalFixture('carrier', 'carrier-'.Str::random(8).'@smoke.test');
        [$customerContractor, $customerExternal] = $this->createExternalFixture('customer', 'customer-'.Str::random(8).'@smoke.test');

        $conversationService = app(CounterpartyConversationService::class);
        $carrierConversation = $conversationService->findOrCreateThread(
            $staff,
            $carrierContractor,
            ExternalParty::Carrier,
        );
        $customerConversation = $conversationService->findOrCreateThread(
            $staff,
            $customerContractor,
            ExternalParty::Customer,
        );

        $this->record(
            'staff open carrier counterparty thread',
            $carrierConversation->channel === 'counterparty',
            'conversation #'.$carrierConversation->id,
        );
        $this->record(
            'staff open customer counterparty thread',
            $customerConversation->channel === 'counterparty',
            'conversation #'.$customerConversation->id,
        );
        $this->record(
            'two separate counterparty threads',
            $carrierConversation->id !== $customerConversation->id,
            'carrier='.$carrierConversation->id.', customer='.$customerConversation->id,
        );

        $mixedRejected = false;
        try {
            $conversationService->assertGroupParticipantsAllowed([$carrierExternal->id, $customerExternal->id]);
        } catch (ValidationException) {
            $mixedRejected = true;
        }
        $this->record('mixed external group rejected', $mixedRejected);

        $response = $this->dispatchAuthenticatedGet(route('messenger.conversations.index'), $carrierExternal, [
            'Accept' => 'application/json',
        ]);
        $payload = json_decode((string) $response->getContent(), true);
        $externalConversations = is_array($payload['conversations'] ?? null) ? $payload['conversations'] : [];
        $counterpartyOnly = collect($externalConversations)
            ->every(fn (array $row): bool => ($row['channel'] ?? null) === 'counterparty');

        $this->record(
            'external sees counterparty conversations only',
            $response->getStatusCode() === HttpResponse::HTTP_OK && count($externalConversations) >= 1 && $counterpartyOnly,
            count($externalConversations).' conversation(s)',
        );
    }

    private function runDeactivationFlow(User $external): void
    {
        if ($this->smokePassword === null) {
            $this->record('deactivated user login blocked', false, 'Skipped: no password from invite flow');

            return;
        }

        $external->forceFill(['is_active' => false])->save();

        Auth::attempt([
            'email' => $external->email,
            'password' => $this->smokePassword,
        ]);
        $user = Auth::user();
        $blocked = ! ($user instanceof User && $user->is_active);
        Auth::logout();

        $this->record('deactivated user login blocked', $blocked);
    }

    /**
     * @return array{0: Contractor, 1: User}
     */
    private function createExternalFixture(string $party, string $email): array
    {
        $roleName = $party === 'carrier' ? 'counterparty_carrier' : 'counterparty_customer';
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        $contractor = Contractor::query()->create([
            'type' => $party === 'carrier' ? 'carrier' : 'customer',
            'name' => '[SMOKE] '.$party,
        ]);
        $contact = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Smoke '.$party,
            'email' => $email,
            'is_traklo_primary' => true,
        ]);
        $external = User::factory()->create([
            'email' => $email,
            'is_external' => true,
            'is_active' => true,
            'contractor_id' => $contractor->id,
            'contractor_contact_id' => $contact->id,
            'external_party' => $party,
            'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);

        return [$contractor, $external];
    }

    private function resolveStaffUser(): ?User
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereHas('role', fn ($role) => $role->where('name', 'admin'))
                    ->orWhereHas('role', fn ($role) => $role->where('name', 'manager'));
            })
            ->orderBy('id')
            ->first()
            ?? User::query()->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function dispatchAuthenticatedGet(string $uri, User $user, array $headers = []): Response
    {
        Auth::login($user);

        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.str_replace('-', '_', strtoupper($key))] = $value;
        }

        $response = app()->handle(Request::create($uri, 'GET', server: $server));
        Auth::logout();

        return $response;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>'.$title.'</>');
    }

    private function record(string $check, bool $ok, string $detail = ''): void
    {
        $this->results[] = [
            'check' => $check,
            'status' => $ok ? 'PASS' : 'FAIL',
            'detail' => $detail,
        ];

        $line = sprintf(' [%s] %s', $ok ? 'PASS' : 'FAIL', $check);
        if ($detail !== '') {
            $line .= ' — '.$detail;
        }
        $this->line($line);
    }

    private function renderReport(): int
    {
        $failed = collect($this->results)->where('status', 'FAIL')->count();
        $passed = collect($this->results)->where('status', 'PASS')->count();

        $this->newLine();
        $this->info("Summary: {$passed} passed, {$failed} failed, ".count($this->results).' total');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
