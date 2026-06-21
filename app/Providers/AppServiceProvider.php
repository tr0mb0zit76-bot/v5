<?php

namespace App\Providers;

use App\Contracts\Inference\ChatCompletionClient;
use App\Contracts\Inference\ToolAwareChatCompletionClient;
use App\Models\ContractorInsightDraft;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Models\Task;
use App\Policies\ContractorInsightDraftPolicy;
use App\Policies\SalesScriptNodePolicy;
use App\Policies\SalesScriptPlaySessionPolicy;
use App\Policies\SalesScriptPolicy;
use App\Policies\SalesScriptTransitionPolicy;
use App\Policies\SalesScriptVersionPolicy;
use App\Policies\TaskPolicy;
use App\Services\Inference\DeepSeekChatCompletionClient;
use App\Services\NextcloudWebDavStorage;
use App\Services\SalesScripts\TrainerAssistantAutoReactionService;
use App\Support\InertiaAppSurface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NextcloudWebDavStorage::class, function () {
            return new NextcloudWebDavStorage(
                baseUrl: config('document_storage.nextcloud.base_url'),
                username: config('document_storage.nextcloud.webdav_user'),
                password: config('document_storage.nextcloud.webdav_password'),
                webdavRoot: (string) config('document_storage.nextcloud.webdav_root', '/remote.php/dav/files'),
                timeoutSeconds: (int) config('document_storage.nextcloud.timeout', 30),
                verifySsl: (bool) config('document_storage.nextcloud.verify_ssl', true),
            );
        });

        $this->app->singleton(DeepSeekChatCompletionClient::class, function (): DeepSeekChatCompletionClient {
            $cfg = config('ai.inference.deepseek', []);

            return new DeepSeekChatCompletionClient(
                apiKey: (string) config('ai.providers.deepseek.key', ''),
                completionsUrl: (string) ($cfg['completions_url'] ?? 'https://api.deepseek.com/chat/completions'),
                defaultModel: (string) ($cfg['default_model'] ?? 'deepseek-chat'),
                timeoutSeconds: max(1, (int) ($cfg['timeout_seconds'] ?? 45)),
            );
        });

        $this->app->singleton(ChatCompletionClient::class, fn (Application $app): ChatCompletionClient => $app->make(DeepSeekChatCompletionClient::class));

        $this->app->singleton(ToolAwareChatCompletionClient::class, fn (Application $app): ToolAwareChatCompletionClient => $app->make(DeepSeekChatCompletionClient::class));

        $this->app->singleton(TrainerAssistantAutoReactionService::class, function (Application $app): TrainerAssistantAutoReactionService {
            return new TrainerAssistantAutoReactionService($app->make(ChatCompletionClient::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->rebinding('request', function ($app, Request $request): void {
            $this->configureGeneratedUrls($request);
        });

        if ($this->app->runningInConsole()) {
            $this->configureGeneratedUrls(null);
        } elseif ($this->app->bound('request')) {
            $this->configureGeneratedUrls($this->app->make(Request::class));
        }

        RateLimiter::for('mcp', function (Request $request) {
            $user = $request->user();

            return $user
                ? Limit::perMinute(120)->by('mcp-user-'.$user->id)
                : Limit::perMinute(30)->by('mcp-ip-'.($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('agent-command-bar', function (Request $request) {
            $user = $request->user();

            return $user
                ? Limit::perMinute(20)->by('agent-cmd-'.$user->id)
                : Limit::perMinute(5)->by('agent-cmd-ip-'.($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('order-intake', function (Request $request) {
            $user = $request->user();

            return $user
                ? Limit::perMinute(10)->by('order-intake-'.$user->id)
                : Limit::perMinute(2)->by('order-intake-ip-'.($request->ip() ?? 'unknown'));
        });

        Vite::prefetch(concurrency: 3);

        View::composer('app', function ($view): void {
            $view->with(
                'documentTitleDefault',
                InertiaAppSurface::fromRequest(request())->documentTitleSuffix(),
            );
        });

        Gate::policy(ContractorInsightDraft::class, ContractorInsightDraftPolicy::class);
        Gate::policy(SalesScript::class, SalesScriptPolicy::class);
        Gate::policy(SalesScriptVersion::class, SalesScriptVersionPolicy::class);
        Gate::policy(SalesScriptNode::class, SalesScriptNodePolicy::class);
        Gate::policy(SalesScriptTransition::class, SalesScriptTransitionPolicy::class);
        Gate::policy(SalesScriptPlaySession::class, SalesScriptPlaySessionPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
    }

    private function configureGeneratedUrls(?Request $request): void
    {
        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            URL::forceScheme('https');

            return;
        }

        $appUrl = strtolower((string) config('app.url', ''));

        if ($request !== null && ! $request->isSecure()) {
            $forwarded = $request->header('X-Forwarded-Proto');
            $hasHttpsForwarded = is_string($forwarded) && strtolower($forwarded) === 'https';
            $appHost = parse_url($appUrl, PHP_URL_HOST);

            if (! $hasHttpsForwarded
                && is_string($appHost)
                && strcasecmp($request->getHost(), $appHost) === 0) {
                URL::forceScheme('http');
                URL::forceRootUrl($request->getSchemeAndHttpHost());

                return;
            }
        }

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        } elseif ($request !== null) {
            $forwarded = $request->header('X-Forwarded-Proto');

            if (is_string($forwarded) && strtolower($forwarded) === 'https') {
                URL::forceScheme('https');
            }
        }

        if ($request === null) {
            if ($appUrl !== '') {
                URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
            }

            return;
        }

        if (InertiaAppSurface::fromRequest($request) === InertiaAppSurface::Showcase) {
            URL::forceRootUrl($request->getSchemeAndHttpHost());

            return;
        }

        if ($appUrl !== '') {
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
        }
    }
}
