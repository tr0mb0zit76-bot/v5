<?php

$showcaseHosts = array_values(array_unique(array_filter(array_map(
    static fn (string $host): string => strtolower(trim($host)),
    explode(',', (string) env('SHOWCASE_DOMAIN', 'v5.local'))
))));

if ($showcaseHosts === []) {
    $showcaseHosts = ['v5.local'];
}

$crmDomainFromEnv = env('CRM_DOMAIN');
$crmDomain = ($crmDomainFromEnv !== null && $crmDomainFromEnv !== '')
    ? strtolower(trim((string) $crmDomainFromEnv))
    : strtolower(trim((string) (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')));

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    | Домены витрины и кабинета (разные хосты в проде: avtoaliyans.ru vs crm.avtoaliyans.ru).
    | Локально по умолчанию: витрина v5.local, кабинет из CRM_DOMAIN (например crm.avtoaliyans.local).
    */
    'crm_domain' => $crmDomain,

    /*
    | Несколько хостов витрины через запятую в SHOWCASE_DOMAIN (например domen.ru,www.domen.ru).
    | showcase_domain — первый хост (обратная совместимость).
    */
    'showcase_hosts' => $showcaseHosts,
    'showcase_domain' => $showcaseHosts[0],

    /*
    | Витрина и CRM на одном хосте (локальная отладка без split-domain).
    */
    'same_showcase_and_crm_host' => $crmDomain !== ''
        && count($showcaseHosts) === 1
        && strcasecmp($crmDomain, $showcaseHosts[0] ?? '') === 0,

    /*
    | Суффикс заголовка вкладки (после названия страницы через « - ») для Inertia.
    */
    'showcase_browser_title' => env('SHOWCASE_BROWSER_TITLE', 'Автоальянс Смоленск'),
    'crm_browser_title' => env('CRM_BROWSER_TITLE', 'CRM компании Автоальянс Смоленск'),

    /*
    | Ссылка на карточку компании на checko.ru для виджета «Проверенная компания» на витрине.
    | По умолчанию — карточка с ИНН 6732110940 (ОГРН в URL задаёт сам Чекко).
    */
    'showcase_checko_company_url' => env(
        'SHOWCASE_CHECKO_COMPANY_URL',
        'https://checko.ru/company/avtoalyans-smolensk-1156733014899',
    ),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Europe/Samara'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
