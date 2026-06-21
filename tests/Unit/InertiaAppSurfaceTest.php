<?php

namespace Tests\Unit;

use App\Support\InertiaAppSurface;
use Illuminate\Http\Request;
use Tests\TestCase;

class InertiaAppSurfaceTest extends TestCase
{
    public function test_sla_path_is_treated_as_showcase_when_hosts_are_shared(): void
    {
        config(['app.same_showcase_and_crm_host' => true]);

        $request = Request::create('/sla', 'GET');

        $this->assertSame(InertiaAppSurface::Showcase, InertiaAppSurface::fromRequest($request));
    }
}
