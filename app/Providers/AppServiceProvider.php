<?php

namespace App\Providers;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Models\Task;
use App\Policies\SalesScriptNodePolicy;
use App\Policies\SalesScriptPlaySessionPolicy;
use App\Policies\SalesScriptPolicy;
use App\Policies\SalesScriptTransitionPolicy;
use App\Policies\SalesScriptVersionPolicy;
use App\Policies\TaskPolicy;
use App\Services\Inference\DeepSeekChatCompletionClient;
use App\Services\NextcloudWebDavStorage;
use App\Services\SalesScripts\TrainerAssistantAutoReactionService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
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

        $this->app->singleton(TrainerAssistantAutoReactionService::class, function (Application $app): TrainerAssistantAutoReactionService {
            return new TrainerAssistantAutoReactionService($app->make(ChatCompletionClient::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(SalesScript::class, SalesScriptPolicy::class);
        Gate::policy(SalesScriptVersion::class, SalesScriptVersionPolicy::class);
        Gate::policy(SalesScriptNode::class, SalesScriptNodePolicy::class);
        Gate::policy(SalesScriptTransition::class, SalesScriptTransitionPolicy::class);
        Gate::policy(SalesScriptPlaySession::class, SalesScriptPlaySessionPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
    }
}
