<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class RoleAccessMergeTest extends TestCase
{
    public function test_merged_visibility_scopes_prefers_all_over_own(): void
    {
        $manager = new Role([
            'name' => 'manager',
            'visibility_scopes' => ['orders' => 'own', 'contractors' => 'own'],
        ]);

        $dispatcher = new Role([
            'name' => 'dispatcher',
            'visibility_scopes' => ['orders' => 'all'],
        ]);

        $user = new User;
        $user->setRelation('roles', new EloquentCollection([$manager, $dispatcher]));

        $scopes = RoleAccess::mergedVisibilityScopesForUser($user);

        $this->assertSame('all', $scopes['orders']);
        $this->assertSame('own', $scopes['contractors']);
    }

    public function test_user_visibility_areas_union_from_multiple_roles(): void
    {
        $manager = new Role([
            'name' => 'manager',
            'visibility_areas' => ['orders', 'leads'],
        ]);

        $dispatcher = new Role([
            'name' => 'dispatcher',
            'visibility_areas' => ['drivers', 'orders'],
        ]);

        $user = new User;
        $user->setRelation('roles', new EloquentCollection([$manager, $dispatcher]));

        $areas = RoleAccess::userVisibilityAreas($user);

        $this->assertContains('orders', $areas);
        $this->assertContains('leads', $areas);
        $this->assertContains('drivers', $areas);
    }
}
