<?php

namespace Tests\Unit\Support;

use App\Models\Role;
use App\Models\User;
use App\Support\CrmFeatureCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrmFeatureCatalogTest extends TestCase
{
    #[Test]
    public function commercial_mail_ai_requires_mail_visibility_area(): void
    {
        $role = Role::query()->create([
            'name' => 'orders_only',
            'visibility_areas' => ['orders'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertFalse(CrmFeatureCatalog::isEnabled('commercial_mail_ai', $user));
    }

    #[Test]
    public function commercial_mail_ai_is_enabled_for_mail_users(): void
    {
        $role = Role::query()->create([
            'name' => 'mail_user',
            'visibility_areas' => ['mail'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue(CrmFeatureCatalog::isEnabled('commercial_mail_ai', $user));
    }
}
