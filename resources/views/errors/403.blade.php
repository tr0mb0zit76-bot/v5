<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Нет доступа — {{ config('app.crm_browser_title', config('app.name')) }}</title>
        <style>
            :root {
                color-scheme: light dark;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #fafafa;
                color: #18181b;
            }

            @media (prefers-color-scheme: dark) {
                body {
                    background: #09090b;
                    color: #fafafa;
                }

                .card {
                    background: #18181b;
                    border-color: #3f3f46;
                }

                .code {
                    color: #a1a1aa;
                    border-color: #52525b;
                }

                .hint {
                    color: #a1a1aa;
                }

                .home {
                    background: #27272a;
                    color: #fafafa;
                    border-color: #52525b;
                }

                .home:hover {
                    background: #3f3f46;
                }
            }

            .card {
                width: 100%;
                max-width: 28rem;
                padding: 2rem 1.75rem;
                border: 1px solid #e4e4e7;
                border-radius: 1rem;
                background: #fff;
                box-shadow: 0 10px 30px rgba(24, 24, 27, 0.06);
                text-align: center;
            }

            .code {
                display: inline-block;
                margin: 0 0 0.75rem;
                padding-right: 0.75rem;
                margin-right: 0.75rem;
                border-right: 1px solid #d4d4d8;
                font-size: 1.125rem;
                font-weight: 600;
                color: #71717a;
                vertical-align: middle;
            }

            .title {
                display: inline;
                margin: 0;
                font-size: 1.125rem;
                font-weight: 600;
                vertical-align: middle;
            }

            .hint {
                margin: 1rem 0 0;
                font-size: 0.875rem;
                line-height: 1.5;
                color: #71717a;
            }

            .actions {
                margin-top: 1.5rem;
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                justify-content: center;
            }

            .home {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.55rem 1rem;
                border-radius: 0.75rem;
                border: 1px solid #e4e4e7;
                background: #f4f4f5;
                color: #18181b;
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
            }

            .home:hover {
                background: #e4e4e7;
            }
        </style>
    </head>
    <body>
        @php
            $raw = trim((string) $exception->getMessage());
            $normalized = mb_strtolower($raw);
            $isGeneric = $raw === ''
                || in_array($normalized, ['forbidden', 'this action is unauthorized.', 'this action is unauthorized'], true);
            $message = $isGeneric ? 'У вас нет прав доступа' : $raw;
        @endphp

        <main class="card" role="main">
            <div>
                <span class="code">403</span>
                <h1 class="title">{{ $message }}</h1>
            </div>
            <p class="hint">
                Этот раздел или действие недоступны для вашей роли.
                Если доступ нужен — обратитесь к администратору.
            </p>
            <div class="actions">
                <a class="home" href="{{ url('/') }}">На главную</a>
                <a class="home" href="javascript:history.back()">Назад</a>
            </div>
        </main>
    </body>
</html>
