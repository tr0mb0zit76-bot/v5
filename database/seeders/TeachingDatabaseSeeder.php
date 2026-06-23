<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Опциональные фикстуры для **тестовой** БД из `.env.testing` (например u_tromb_test), а не для вашей рабочей `.env`.
 * Нужны, чтобы после `migrate` в тестовой схеме было что открыть вручную или не падали интеграционные проверки.
 *
 *   php artisan migrate --env=testing
 *   php artisan db:seed --class=TeachingDatabaseSeeder --env=testing
 *
 * Вход: admin@u-tromb.local / password  |  manager@u-tromb.local / password
 */
class TeachingDatabaseSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@u-tromb.local';

    private const MANAGER_EMAIL = 'manager@u-tromb.local';

    private const PASSWORD = 'password';

    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->command?->error('Нет таблиц users/roles. Сначала выполните: php artisan migrate');

            return;
        }

        if (User::query()->where('email', self::ADMIN_EMAIL)->exists()) {
            $this->command?->warn('Учебные данные уже есть (найден '.self::ADMIN_EMAIL.'). Повторный запуск пропущен.');

            return;
        }

        DB::transaction(function (): void {
            $roles = $this->seedRoles();
            [$admin, $manager] = $this->seedUsers($roles);
            [$customerA, $customerB, $carrierA, $carrierB] = $this->seedContractors($admin, $manager);
            $this->seedOrders($admin, $manager, $customerA, $customerB, $carrierA, $carrierB);
            $this->seedLeads($manager);
            $this->seedPaymentSchedules();
        });

        $this->command?->info('Готово. Вход: '.self::ADMIN_EMAIL.' или '.self::MANAGER_EMAIL.' / пароль: '.self::PASSWORD);
    }

    /**
     * @return array{admin: Role, manager: Role}
     */
    private function seedRoles(): array
    {
        $make = function (string $name, string $displayName): Role {
            $areas = RoleAccess::defaultVisibilityAreas($name);
            $scopes = RoleAccess::defaultVisibilityScopes($name);

            return Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'description' => 'Учебная роль (сидер TeachingDatabaseSeeder)',
                    'permissions' => RoleAccess::permissionKeys(),
                    'visibility_areas' => $areas,
                    'visibility_scopes' => $scopes,
                ],
            );
        };

        return [
            'admin' => $make('admin', 'Администратор'),
            'manager' => $make('manager', 'Менеджер'),
        ];
    }

    /**
     * @param  array{admin: Role, manager: Role}  $roles
     * @return array{0: User, 1: User}
     */
    private function seedUsers(array $roles): array
    {
        $hash = Hash::make(self::PASSWORD);

        $admin = User::query()->create([
            'role_id' => $roles['admin']->id,
            'name' => 'Учебный админ',
            'email' => self::ADMIN_EMAIL,
            'password' => $hash,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $manager = User::query()->create([
            'role_id' => $roles['manager']->id,
            'name' => 'Учебный менеджер',
            'email' => self::MANAGER_EMAIL,
            'password' => $hash,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        return [$admin, $manager];
    }

    /**
     * @return array{0: Contractor, 1: Contractor, 2: Contractor, 3: Contractor}
     */
    private function seedContractors(User $admin, User $manager): array
    {
        $customerPayload = [
            'type' => 'customer',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ];
        if (Schema::hasColumn('contractors', 'owner_id')) {
            $customerPayload['owner_id'] = $manager->id;
        }

        $customerA = Contractor::query()->create(array_merge($customerPayload, [
            'name' => 'ООО «СеверТранс»',
            'inn' => '7701234567',
            'phone' => '+7 (495) 100-00-01',
            'email' => 'logist@severtrans.demo',
            'is_verified' => true,
        ]));

        $customerB = Contractor::query()->create(array_merge($customerPayload, [
            'name' => 'ИП Кузнецов А.В.',
            'inn' => '770123456789',
            'phone' => '+7 (903) 555-12-34',
        ]));

        $carrierA = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО «ЮгКарго»',
            'inn' => '7801987654',
            'phone' => '+7 (812) 200-00-02',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $carrierB = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ТК «ВостокЛайн»',
            'inn' => '5408123456',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        return [$customerA, $customerB, $carrierA, $carrierB];
    }

    private function seedOrders(
        User $admin,
        User $manager,
        Contractor $customerA,
        Contractor $customerB,
        Contractor $carrierA,
        Contractor $carrierB,
    ): void {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $year = (int) now()->format('Y');
        $base = Carbon::create($year, 1, 15)->startOfDay();

        $rows = [
            [
                'order_number' => 'TRN-'.$year.'-0001',
                'company_code' => 'ORD',
                'manager_id' => $manager->id,
                'order_date' => $base->copy()->subMonths(2)->toDateString(),
                'status' => 'closed',
                'status_updated_at' => $base->copy()->subMonths(2)->addDays(5),
                'status_updated_by' => $manager->id,
                'customer_id' => $customerA->id,
                'carrier_id' => $carrierA->id,
                'customer_rate' => 450_000,
                'carrier_rate' => 380_000,
                'additional_expenses' => 15_000,
                'delta' => 55_000,
            ],
            [
                'order_number' => 'TRN-'.$year.'-0002',
                'company_code' => 'ORD',
                'manager_id' => $manager->id,
                'order_date' => $base->copy()->subMonth()->toDateString(),
                'status' => 'closed',
                'status_updated_at' => $base->copy()->subMonth()->addDays(3),
                'status_updated_by' => $manager->id,
                'customer_id' => $customerB->id,
                'carrier_id' => $carrierB->id,
                'customer_rate' => 280_000,
                'carrier_rate' => 235_000,
                'additional_expenses' => 8000,
                'delta' => 37_000,
            ],
            [
                'order_number' => 'TRN-'.$year.'-0003',
                'company_code' => 'ORD',
                'manager_id' => $manager->id,
                'order_date' => $base->copy()->toDateString(),
                'status' => 'in_progress',
                'customer_id' => $customerA->id,
                'carrier_id' => $carrierB->id,
                'customer_rate' => 520_000,
                'carrier_rate' => 440_000,
                'additional_expenses' => 10_000,
                'delta' => 70_000,
            ],
            [
                'order_number' => 'TRN-'.$year.'-0004',
                'company_code' => 'ORD',
                'manager_id' => $manager->id,
                'order_date' => now()->toDateString(),
                'status' => 'new',
                'customer_id' => $customerB->id,
                'carrier_id' => $carrierA->id,
                'customer_rate' => 195_000,
                'carrier_rate' => 165_000,
                'additional_expenses' => 5000,
                'delta' => 25_000,
            ],
        ];

        foreach ($rows as $data) {
            $payload = array_merge([
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $manager->id,
            ], $data);

            if (Schema::hasColumn('orders', 'payment_status')) {
                $payload['payment_status'] = 'none';
            }

            Order::query()->create($payload);
        }
    }

    private function seedLeads(User $manager): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Lead::factory()
            ->count(5)
            ->create([
                'responsible_id' => $manager->id,
            ]);
    }

    private function seedPaymentSchedules(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        $order = Order::query()->where('status', 'in_progress')->orderBy('id')->first();
        if ($order === null) {
            return;
        }

        $now = now();

        $rows = [
            [
                'order_id' => $order->id,
                'party' => 'customer',
                'type' => 'prepayment',
                'amount' => 150_000,
                'planned_date' => now()->addDays(3)->toDateString(),
                'actual_date' => null,
                'status' => 'pending',
                'notes' => 'Учебная строка графика',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_id' => $order->id,
                'party' => 'carrier',
                'type' => 'prepayment',
                'amount' => 120_000,
                'planned_date' => now()->addDays(5)->toDateString(),
                'actual_date' => null,
                'status' => 'pending',
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('payment_schedules')->insert($row);
        }
    }
}
