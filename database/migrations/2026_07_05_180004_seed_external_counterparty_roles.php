<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<array{name: string, display_name: string, external_party: string}>
     */
    private array $roles = [
        [
            'name' => 'counterparty_carrier',
            'display_name' => 'Контакт перевозчика (Traklo)',
            'external_party' => 'carrier',
        ],
        [
            'name' => 'counterparty_customer',
            'display_name' => 'Контакт заказчика (Traklo)',
            'external_party' => 'customer',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $counterpartyAreas = [
            'counterparty_orders',
            'counterparty_documents',
            'counterparty_portal',
        ];

        foreach ($this->roles as $roleDefinition) {
            Role::query()->updateOrCreate(
                ['name' => $roleDefinition['name']],
                [
                    'display_name' => $roleDefinition['display_name'],
                    'description' => 'Внешний пользователь Traklo',
                    'permissions' => [],
                    'visibility_areas' => $counterpartyAreas,
                    'visibility_scopes' => [],
                    'columns_config' => [],
                    'has_signing_authority' => false,
                    'default_mobile_nav_keys' => $roleDefinition['external_party'] === 'carrier'
                        ? ['counterparty_orders', 'counterparty_documents', 'chats', 'counterparty_portal']
                        : ['counterparty_orders', 'counterparty_documents', 'chats'],
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        Role::query()->whereIn('name', array_column($this->roles, 'name'))->delete();
    }
};
