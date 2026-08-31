<?php

namespace Tests\Unit;

use App\Support\CrmAppearance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CrmAppearanceTest extends TestCase
{
    #[Test]
    public function it_resolves_defaults_for_empty_preferences(): void
    {
        $resolved = CrmAppearance::resolve(null);

        $this->assertSame('rounded', $resolved['button_radius']);
        $this->assertSame('sky', $resolved['primary_accent']);
        $this->assertSame('filled', $resolved['tab_style']);
        $this->assertSame('sky', $resolved['workspace_skin']);
        $this->assertSame('normal', $resolved['ag_grid_density']);
        $this->assertSame('monochrome', $resolved['nav_icon_style']);
    }

    #[Test]
    public function it_merges_partial_validated_preferences(): void
    {
        $merged = CrmAppearance::mergeValidated(
            ['primary_accent' => 'sky'],
            ['button_radius' => 'rounded', 'tab_style' => 'underline'],
        );

        $this->assertSame('rounded', $merged['button_radius']);
        $this->assertSame('sky', $merged['primary_accent']);
        $this->assertSame('underline', $merged['tab_style']);
        $this->assertSame('sky', $merged['workspace_skin']);
    }

    #[Test]
    public function it_preserves_non_appearance_keys_when_merging(): void
    {
        $merged = CrmAppearance::mergeValidated(
            ['ag_grid_density' => 'compact'],
            [
                'button_radius' => 'sharp',
                'sidebar_favorite_keys' => ['orders', 'leads'],
            ],
        );

        $this->assertSame('compact', $merged['ag_grid_density']);
        $this->assertSame('sharp', $merged['button_radius']);
        $this->assertSame(['orders', 'leads'], $merged['sidebar_favorite_keys']);
    }

    #[Test]
    public function it_accepts_sky_workspace_skin(): void
    {
        $resolved = CrmAppearance::resolve(['workspace_skin' => 'sky']);

        $this->assertSame('sky', $resolved['workspace_skin']);
    }

    #[Test]
    public function it_accepts_semantic_nav_icon_style(): void
    {
        $resolved = CrmAppearance::resolve(['nav_icon_style' => 'semantic']);

        $this->assertSame('semantic', $resolved['nav_icon_style']);
    }

    #[Test]
    public function it_rejects_unknown_nav_icon_style(): void
    {
        $resolved = CrmAppearance::resolve(['nav_icon_style' => 'rainbow']);

        $this->assertSame('monochrome', $resolved['nav_icon_style']);
    }
}
