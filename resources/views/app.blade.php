@php
    $crmHtmlAppearance = \App\Support\CrmAppearance::defaults();
    if (auth()->check()) {
        $crmHtmlAppearance = \App\Support\CrmAppearance::resolve(
            is_array(auth()->user()->ui_preferences) ? auth()->user()->ui_preferences : null,
        );
    }
    $crmThemeColor = ($crmHtmlAppearance['workspace_skin'] ?? 'classic') === 'sky' ? '#0284c7' : '#18181b';
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-crm-radius="{{ $crmHtmlAppearance['button_radius'] }}"
    data-crm-accent="{{ $crmHtmlAppearance['primary_accent'] }}"
    data-crm-tab-style="{{ $crmHtmlAppearance['tab_style'] }}"
    data-crm-workspace-skin="{{ $crmHtmlAppearance['workspace_skin'] }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-base-url" content="{{ rtrim(url('/'), '/') }}">
        <meta name="theme-color" content="{{ $crmThemeColor }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $documentTitleDefault ?? config('app.crm_browser_title') }}">

        <title inertia>{{ $documentTitleDefault ?? config('app.crm_browser_title') }}</title>
        <link rel="icon" type="image/png" href="/assets/favicon/favicon-96x96.png" sizes="96x96">
        <link rel="icon" type="image/svg+xml" href="/assets/favicon/favicon.svg">
        <link rel="shortcut icon" href="/assets/favicon/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">

        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        @inertia
    </body>
</html>
