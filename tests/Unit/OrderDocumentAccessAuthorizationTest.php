<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderDocumentAccessAuthorization;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderDocumentAccessAuthorizationTest extends TestCase
{
    public function test_clerk_with_documents_all_scope_may_view_and_manage_foreign_order_documents(): void
    {
        $clerk = $this->makeUser('clerk', ['documents' => 'all', 'orders' => 'all']);
        $order = new Order(['id' => 10, 'manager_id' => 99]);

        $this->assertTrue(OrderDocumentAccessAuthorization::userMayManageDocuments($clerk, $order));
        $this->assertTrue(OrderDocumentAccessAuthorization::userMayViewDocuments($clerk, $order));
    }

    public function test_manager_may_manage_only_own_order_documents(): void
    {
        $manager = $this->makeUser('manager', ['documents' => 'own', 'orders' => 'own'], userId: 42);
        $ownOrder = new Order(['id' => 1, 'manager_id' => 42]);
        $foreignOrder = new Order(['id' => 2, 'manager_id' => 999]);

        $this->assertTrue(OrderDocumentAccessAuthorization::userMayManageDocuments($manager, $ownOrder));
        $this->assertTrue(OrderDocumentAccessAuthorization::userMayViewDocuments($manager, $ownOrder));

        $this->assertFalse(OrderDocumentAccessAuthorization::userMayManageDocuments($manager, $foreignOrder));
        $this->assertFalse(OrderDocumentAccessAuthorization::userMayViewDocuments($manager, $foreignOrder));
    }

    public function test_order_owner_may_manage_documents_when_manager_differs(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $owner = $this->makeUser('manager', ['documents' => 'own', 'orders' => 'own'], userId: 55);
        $order = new Order([
            'id' => 3,
            'manager_id' => 999,
            'order_owner_id' => 55,
        ]);

        $this->assertTrue(OrderDocumentAccessAuthorization::userMayManageDocuments($owner, $order));
        $this->assertTrue(OrderDocumentAccessAuthorization::userMayViewDocuments($owner, $order));
    }

    public function test_accountant_with_documents_all_scope_may_manage_documents(): void
    {
        $accountant = $this->makeUser('accountant', ['documents' => 'all', 'orders' => 'all']);
        $order = new Order(['id' => 5, 'manager_id' => 1]);

        $this->assertTrue(OrderDocumentAccessAuthorization::userMayManageDocuments($accountant, $order));
        $this->assertTrue(OrderDocumentAccessAuthorization::userMayViewDocuments($accountant, $order));
    }

    /**
     * @param  array<string, string>  $visibilityScopes
     */
    private function makeUser(string $roleName, array $visibilityScopes, int $userId = 42): User
    {
        $role = new Role([
            'id' => 1,
            'name' => $roleName,
            'display_name' => ucfirst($roleName),
            'visibility_scopes' => $visibilityScopes,
        ]);
        $role->exists = true;

        $user = new User([
            'role_id' => $role->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $user->id = $userId;
        $user->exists = true;
        $user->setRelation('role', $role);
        $user->setRelation('roles', new EloquentCollection([$role]));

        return $user;
    }
}
