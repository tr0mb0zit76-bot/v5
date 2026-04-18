<?php

namespace Tests\Feature;

use Tests\TestCase;

class DomainRoutingTest extends TestCase
{
    public function test_crm_root_redirects_guest_to_login(): void
    {
        $crmDomain = (string) config('app.crm_domain');

        $this->get("http://{$crmDomain}/")
            ->assertRedirect("http://{$crmDomain}/login");
    }

    public function test_showcase_root_is_accessible_on_showcase_domain(): void
    {
        $showcaseDomain = (string) config('app.showcase_domain');

        $this->get("http://{$showcaseDomain}/")
            ->assertOk();
    }

    public function test_showcase_non_public_path_redirects_to_crm_with_path_and_query(): void
    {
        $crmDomain = (string) config('app.crm_domain');
        $showcaseDomain = (string) config('app.showcase_domain');

        $this->get("http://{$showcaseDomain}/orders?status=active")
            ->assertRedirect("http://{$crmDomain}/orders?status=active");
    }
}
