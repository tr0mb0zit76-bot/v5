<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    /**
     * @return list<string>
     */
    protected function translationPaths(): array
    {
        return [
            public_path('locales/ru.json'),
            public_path('assets/locales/ru.json'),
            public_path('change/locales/ru.json'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedProps(): array
    {
        $translations = [];
        $crmHost = trim((string) config('app.crm_domain'));
        if ($crmHost === '') {
            $crmHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm.log-sol.local';
        }
        $crmScheme = request()->isSecure() ? 'https' : 'http';

        foreach ($this->translationPaths() as $translationsPath) {
            if (! is_file($translationsPath)) {
                continue;
            }

            $decodedTranslations = json_decode((string) file_get_contents($translationsPath), true);

            if (is_array($decodedTranslations)) {
                $translations = $decodedTranslations;
                break;
            }
        }

        return [
            'canLogin' => \Route::has('login'),
            'canRegister' => \Route::has('register'),
            'publicSite' => [
                'texts' => $translations,
                'crm_login_url' => sprintf('%s://%s/login', $crmScheme, $crmHost),
            ],
        ];
    }

    public function home(): Response
    {
        return Inertia::render('Welcome', $this->sharedProps());
    }

    public function about(): Response
    {
        return Inertia::render('Public/About', $this->sharedProps());
    }

    public function services(): Response
    {
        return Inertia::render('Public/Services', $this->sharedProps());
    }

    public function cases(): Response
    {
        return Inertia::render('Public/Cases', $this->sharedProps());
    }

    public function contacts(): Response
    {
        return Inertia::render('Public/Contacts', $this->sharedProps());
    }
}
