<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    protected function sharedProps(): array
    {
        $translationsPath = public_path('change/locales/ru.json');
        $translations = [];

        if (is_file($translationsPath)) {
            $decodedTranslations = json_decode((string) file_get_contents($translationsPath), true);
            $translations = is_array($decodedTranslations) ? $decodedTranslations : [];
        }

        return [
            'canLogin' => \Route::has('login'),
            'canRegister' => \Route::has('register'),
            'publicSite' => [
                'texts' => $translations,
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
