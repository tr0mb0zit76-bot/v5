<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Витрина (публичный сайт) и CRM могут жить на разных хостах или на одном — см. routes/web.php.
 * Используется для суффикса заголовка вкладки и начального title в app.blade.php.
 */
enum InertiaAppSurface: string
{
    case Showcase = 'showcase';
    case Crm = 'crm';

    public static function fromRequest(Request $request): self
    {
        $host = strtolower($request->getHost());
        $crm = strtolower((string) config('app.crm_domain', ''));
        /** @var list<string> $showcaseHosts */
        $showcaseHosts = config('app.showcase_hosts', []);
        $hostsNormalized = array_values(array_filter(array_map(
            static fn (string $h): string => strtolower(trim($h)),
            $showcaseHosts,
        )));

        if (config('app.same_showcase_and_crm_host', false)) {
            return self::isShowcaseDocumentPath($request) ? self::Showcase : self::Crm;
        }

        if ($crm !== '' && strcasecmp($host, $crm) === 0) {
            return self::Crm;
        }

        if ($hostsNormalized !== [] && in_array($host, $hostsNormalized, true)) {
            return self::Showcase;
        }

        return self::Crm;
    }

    public function documentTitleSuffix(): string
    {
        return match ($this) {
            self::Showcase => (string) config('app.showcase_browser_title', 'Автоальянс Смоленск'),
            self::Crm => (string) config('app.crm_browser_title', 'CRM компании Автоальянс Смоленск'),
        };
    }

    private static function isShowcaseDocumentPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === '' || $path === 'about' || $path === 'services' || $path === 'cases' || $path === 'sla' || $path === 'contacts') {
            return true;
        }

        if (str_starts_with($path, 'sla/documents/')) {
            return true;
        }

        if (str_starts_with($path, 'locale/')) {
            return true;
        }

        return $path === '_boost/browser-logs';
    }
}
