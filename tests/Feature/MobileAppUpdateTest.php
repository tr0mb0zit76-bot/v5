<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileAppUpdateTest extends TestCase
{
    private ?string $manifestPath = null;

    protected function tearDown(): void
    {
        if ($this->manifestPath !== null && is_file($this->manifestPath)) {
            @unlink($this->manifestPath);
        }

        parent::tearDown();
    }

    public function test_mobile_app_update_endpoint_reports_available_update(): void
    {
        config([
            'mobile_app.manifest_path' => sys_get_temp_dir().'/missing-traklo-update.json',
            'mobile_app.latest_version_code' => 3,
            'mobile_app.latest_version_name' => '1.2',
            'mobile_app.min_supported_version_code' => 2,
            'mobile_app.apk_url' => 'https://example.test/downloads/traklo.apk',
            'mobile_app.changelog' => 'Новая версия.',
        ]);

        $this->getJson(route('mobile.app-update', ['version_code' => 1]))
            ->assertOk()
            ->assertJsonPath('app_name', 'Traklo')
            ->assertJsonPath('latest_version_code', 3)
            ->assertJsonPath('latest_version_name', '1.2')
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('required', true)
            ->assertJsonPath('apk_url', 'https://example.test/downloads/traklo.apk');
    }

    public function test_mobile_app_update_endpoint_returns_no_update_for_latest_version(): void
    {
        config([
            'mobile_app.manifest_path' => sys_get_temp_dir().'/missing-traklo-update.json',
            'mobile_app.latest_version_code' => 3,
            'mobile_app.min_supported_version_code' => 2,
        ]);

        $this->getJson(route('mobile.app-update', ['version_code' => 3]))
            ->assertOk()
            ->assertJsonPath('update_available', false)
            ->assertJsonPath('required', false);
    }

    public function test_mobile_app_update_endpoint_prefers_manifest_file(): void
    {
        $this->manifestPath = tempnam(sys_get_temp_dir(), 'traklo-update-');
        $this->assertIsString($this->manifestPath);

        file_put_contents($this->manifestPath, json_encode([
            'latest_version_code' => 7,
            'latest_version_name' => '2.0',
            'min_supported_version_code' => 5,
            'apk_url' => '/downloads/traklo.apk',
            'changelog' => 'Manifest version.',
        ]));

        config([
            'mobile_app.manifest_path' => $this->manifestPath,
            'mobile_app.latest_version_code' => 3,
            'mobile_app.latest_version_name' => '1.2',
        ]);

        $this->getJson(route('mobile.app-update', ['version_code' => 6]))
            ->assertOk()
            ->assertJsonPath('latest_version_code', 7)
            ->assertJsonPath('latest_version_name', '2.0')
            ->assertJsonPath('required', false)
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('changelog', 'Manifest version.');
    }

    public function test_mobile_app_update_endpoint_can_force_config_over_manifest(): void
    {
        $this->manifestPath = tempnam(sys_get_temp_dir(), 'traklo-update-');
        $this->assertIsString($this->manifestPath);

        file_put_contents($this->manifestPath, json_encode([
            'latest_version_code' => 7,
            'latest_version_name' => '2.0',
            'min_supported_version_code' => 5,
        ]));

        config([
            'mobile_app.force_config' => true,
            'mobile_app.manifest_path' => $this->manifestPath,
            'mobile_app.latest_version_code' => 3,
            'mobile_app.latest_version_name' => '1.2',
            'mobile_app.min_supported_version_code' => 2,
        ]);

        $this->getJson(route('mobile.app-update', ['version_code' => 3]))
            ->assertOk()
            ->assertJsonPath('latest_version_code', 3)
            ->assertJsonPath('latest_version_name', '1.2')
            ->assertJsonPath('update_available', false)
            ->assertJsonPath('required', false);
    }
}
