<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
    private function showcaseUrl(string $path = '/'): string
    {
        $host = config('app.showcase_hosts')[0] ?? 'v5.local';

        return 'http://'.$host.$path;
    }

    public function test_guest_can_open_public_landing_page(): void
    {
        $response = $this->get($this->showcaseUrl('/'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('canLogin', Route::has('login'))
            ->where('canRegister', Route::has('register'))
            ->has('publicSite.texts')
        );
    }

    public function test_guest_can_open_public_secondary_pages(): void
    {
        $this->get($this->showcaseUrl('/about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/About'));

        $this->get($this->showcaseUrl('/services'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Services'));

        $this->get($this->showcaseUrl('/cases'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Cases'));

        $this->get($this->showcaseUrl('/contacts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Contacts'));

        $this->get($this->showcaseUrl('/sla'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Sla')
                ->has('publicSite.traklo_apk_url')
            );
    }

    public function test_public_pages_prefer_public_locale_file_when_available(): void
    {
        $localeDirectory = public_path('locales');
        $localePath = $localeDirectory.'/ru.json';

        File::ensureDirectoryExists($localeDirectory);
        File::put($localePath, json_encode([
            'welcome_title' => 'Тестовый заголовок',
            'footer_name' => 'Тестовый футер',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            $this->get($this->showcaseUrl('/'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('publicSite.texts.welcome_title', 'Тестовый заголовок')
                    ->where('publicSite.texts.footer_name', 'Тестовый футер')
                );
        } finally {
            File::delete($localePath);
        }
    }

    public function test_authenticated_user_is_redirected_from_root_to_dashboard(): void
    {
        $user = new User;
        $user->id = 1;
        $user->name = 'Admin User';
        $user->email = 'admin@example.com';
        $user->exists = true;

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect('/dashboard');
    }
}
