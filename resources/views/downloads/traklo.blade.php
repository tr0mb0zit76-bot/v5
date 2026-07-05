<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Скачать {{ $appName }}</title>
    <meta name="description" content="Мобильное приложение {{ $appName }} для клиентов и перевозчиков Автоальянс.">
    <link rel="icon" href="{{ $iconUrl }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ $iconUrl }}">
    <style>
        :root {
            color-scheme: dark;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top, #1f2937 0%, #0b0f17 55%, #05070b 100%);
            color: #f8fafc;
        }

        .card {
            width: min(420px, 100%);
            padding: 32px 28px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(15, 23, 42, 0.82);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            text-align: center;
        }

        .icon {
            width: 96px;
            height: 96px;
            margin: 0 auto 20px;
            border-radius: 22px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.75rem;
            font-weight: 700;
        }

        .version {
            margin: 0 0 16px;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .hint {
            margin: 0 0 24px;
            color: #cbd5e1;
            line-height: 1.55;
            font-size: 0.95rem;
        }

        .download {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 220px;
            padding: 14px 22px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }

        .download:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.45);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <main class="card">
        <img class="icon" src="{{ $iconUrl }}" width="96" height="96" alt="{{ $appName }}">
        <h1>{{ $appName }}</h1>
        @if ($versionName !== '')
            <p class="version">Версия {{ $versionName }}</p>
        @endif
        <p class="hint">Скачайте приложение для Android: чаты с менеджером, заявки и документы.</p>
        <a class="download" href="{{ $apkFileUrl }}">Скачать APK</a>
    </main>
</body>
</html>
