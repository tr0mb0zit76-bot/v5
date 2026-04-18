<?php

namespace Tests\Unit;

use App\Support\DocumentPreview;
use Tests\TestCase;

class DocumentPreviewTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        $this->envBackup = [];
        parent::tearDown();
    }

    private function overrideEnv(string $key, ?string $value): void
    {
        if (! array_key_exists($key, $this->envBackup)) {
            $previous = getenv($key);
            $this->envBackup[$key] = $previous === false ? false : $previous;
        }

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    public function test_resolves_explicit_driver_case_insensitive(): void
    {
        $this->overrideEnv('DOC_PREVIEW_DRIVER', 'GOTENBERG');
        $this->overrideEnv('GOTENBERG_URL', null);
        $this->assertSame('gotenberg', DocumentPreview::resolvedDriverFromEnv());
    }

    public function test_fallback_gotenberg_when_driver_unset_and_url_present(): void
    {
        $this->overrideEnv('DOC_PREVIEW_DRIVER', null);
        $this->overrideEnv('GOTENBERG_URL', 'http://127.0.0.1:3000');
        $this->assertSame('gotenberg', DocumentPreview::resolvedDriverFromEnv());
    }

    public function test_fallback_gotenberg_when_driver_empty_and_url_present(): void
    {
        $this->overrideEnv('DOC_PREVIEW_DRIVER', '');
        $this->overrideEnv('GOTENBERG_URL', 'http://127.0.0.1:3000');
        $this->assertSame('gotenberg', DocumentPreview::resolvedDriverFromEnv());
    }

    public function test_fallback_html_when_driver_unset_and_url_missing(): void
    {
        $this->overrideEnv('DOC_PREVIEW_DRIVER', null);
        $this->overrideEnv('GOTENBERG_URL', null);
        $this->assertSame('html', DocumentPreview::resolvedDriverFromEnv());
    }

    public function test_explicit_html_wins_over_gotenberg_url(): void
    {
        $this->overrideEnv('DOC_PREVIEW_DRIVER', 'html');
        $this->overrideEnv('GOTENBERG_URL', 'http://127.0.0.1:3000');
        $this->assertSame('html', DocumentPreview::resolvedDriverFromEnv());
    }
}
