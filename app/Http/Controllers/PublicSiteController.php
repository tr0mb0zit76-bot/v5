<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    private const COOKIE_NAME = 'public_site_locale';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['ru', 'en', 'cn'];

    /**
     * Каноническая русская локаль: resources/locales/public/ru.json (не затирается типичным rsync/clean public/).
     * Дубли: public/locales/ru.json (если нужен прямой доступ по URL), publicSiteRuFallbacks.json во фронте.
     *
     * @return list<string>
     */
    protected function translationPathsForLocale(string $locale): array
    {
        return match ($locale) {
            'ru' => [
                public_path('locales/ru.json'),
                public_path('assets/locales/ru.json'),
                public_path('change/locales/ru.json'),
                resource_path('locales/public/ru.json'),
                public_path('locales/en.json'),
            ],
            'cn' => [
                public_path('locales/cn.json'),
                public_path('locales/en.json'),
            ],
            default => [
                public_path('locales/en.json'),
            ],
        };
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        $normalized = strtolower($locale);
        if (! in_array($normalized, self::SUPPORTED_LOCALES, true)) {
            abort(404);
        }

        $fallback = \Route::has('public.home') ? route('public.home') : '/';

        return redirect()->back(fallback: $fallback)->withCookie(cookie(
            self::COOKIE_NAME,
            $normalized,
            self::COOKIE_MINUTES,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedProps(): array
    {
        $locale = $this->resolvePublicLocale();
        $translations = [];
        $crmHost = trim((string) config('app.crm_domain'));
        if ($crmHost === '') {
            $crmHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm.avtoaliyans.ru';
        }
        $crmScheme = request()->isSecure() ? 'https' : 'http';

        foreach ($this->translationPathsForLocale($locale) as $translationsPath) {
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
                'active_locale' => $locale,
                'available_locales' => [
                    ['code' => 'ru', 'label' => 'RU'],
                    ['code' => 'en', 'label' => 'EN'],
                    ['code' => 'cn', 'label' => '中文'],
                ],
                'checko_company_url' => (string) config('app.showcase_checko_company_url', ''),
                'sla_documents' => $this->slaDocumentsForFrontend(),
                'traklo_apk_url' => $this->resolveTrakloApkUrl(),
            ],
        ];
    }

    protected function resolveTrakloApkUrl(): string
    {
        $configured = trim((string) config('external_users.apk_url', '/downloads/traklo.apk'));

        if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
            return $configured;
        }

        $path = str_starts_with($configured, '/') ? $configured : '/'.$configured;

        if (in_array($path, ['/downloads/traklo', '/downloads/traklo/'], true)) {
            $path = '/downloads/traklo.apk';
        }

        $crmHost = trim((string) config('app.crm_domain'));
        if ($crmHost === '') {
            $crmHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        }

        $scheme = request()->isSecure() ? 'https' : 'http';

        return sprintf('%s://%s%s', $scheme, $crmHost, $path);
    }

    /**
     * @return list<array{id: string, panel: string, label: string, preview_url: string|null}>
     */
    protected function slaDocumentsForFrontend(): array
    {
        /** @var array<string, array{panel?: string, label?: string}> $catalog */
        $catalog = config('showcase.sla_documents', []);
        $documents = [];

        foreach ($catalog as $id => $entry) {
            $panel = (string) ($entry['panel'] ?? '');
            if ($panel === '') {
                continue;
            }

            $documents[] = [
                'id' => (string) $id,
                'panel' => $panel,
                'label' => (string) ($entry['label'] ?? $id),
                'preview_url' => \Route::has('public.sla.document')
                    ? route('public.sla.document', ['document' => $id])
                    : null,
            ];
        }

        return $documents;
    }

    protected function resolvePublicLocale(): string
    {
        $fromCookie = request()->cookie(self::COOKIE_NAME);
        if (is_string($fromCookie)) {
            $candidate = strtolower(trim($fromCookie));
            if (in_array($candidate, self::SUPPORTED_LOCALES, true)) {
                return $candidate;
            }
        }

        return 'ru';
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

    public function sla(): Response
    {
        return Inertia::render('Public/Sla', $this->sharedProps());
    }
}
