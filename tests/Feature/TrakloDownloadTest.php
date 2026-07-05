<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrakloDownloadTest extends TestCase
{
    public function test_traklo_download_page_shows_app_icon_and_link(): void
    {
        $this->get(route('downloads.traklo'))
            ->assertOk()
            ->assertSee('Traklo', false)
            ->assertSee(route('downloads.traklo.file'), false)
            ->assertSee('/downloads/traklo-icon.png', false);
    }

    public function test_traklo_apk_file_returns_not_found_when_missing(): void
    {
        $path = public_path('downloads/traklo.apk');
        $backup = null;

        if (is_file($path)) {
            $backup = $path.'.test-backup';
            rename($path, $backup);
        }

        try {
            $this->get(route('downloads.traklo.file'))->assertNotFound();
        } finally {
            if (is_string($backup) && is_file($backup)) {
                rename($backup, $path);
            }
        }
    }
}
