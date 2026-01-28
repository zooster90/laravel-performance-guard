<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Zufarmarwah\PerformanceGuard\Analyzers\NPlusOneAnalyzer;
use Zufarmarwah\PerformanceGuard\Analyzers\PerformanceScorer;
use Zufarmarwah\PerformanceGuard\Analyzers\SlowQueryAnalyzer;
use Zufarmarwah\PerformanceGuard\Commands\CleanupCommand;
use Zufarmarwah\PerformanceGuard\Commands\StatusCommand;
use Zufarmarwah\PerformanceGuard\Listeners\QueryListener;
use Zufarmarwah\PerformanceGuard\Middleware\PerformanceMonitoringMiddleware;
use Zufarmarwah\PerformanceGuard\Notifications\NotificationDispatcher;

class PerformanceGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/performance-guard.php', 'performance-guard');

        $this->app->singleton(QueryListener::class);
        $this->app->singleton(NPlusOneAnalyzer::class);
        $this->app->singleton(SlowQueryAnalyzer::class);
        $this->app->singleton(PerformanceScorer::class);
        $this->app->singleton(PerformanceGuardManager::class);
        $this->app->singleton(NotificationDispatcher::class);
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->registerMiddlewareAlias();
        $this->registerQueryListener();
        $this->registerCommands();
        $this->loadRoutes();
        $this->loadViews();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/performance-guard.php' => config_path('performance-guard.php'),
        ], 'performance-guard-config');
    }

    private function publishMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    private function registerMiddlewareAlias(): void
    {
        $router = $this->app->make('router');
        $router->aliasMiddleware('performance-guard', PerformanceMonitoringMiddleware::class);
    }

    private function registerQueryListener(): void
    {
        if (! config('performance-guard.enabled', true)) {
            return;
        }

        $listener = $this->app->make(QueryListener::class);

        DB::listen(function (QueryExecuted $event) use ($listener) {
            $listener->recordQuery($event);
        });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupCommand::class,
                StatusCommand::class,
            ]);
        }
    }

    private function loadRoutes(): void
    {
        if (! config('performance-guard.dashboard.enabled', true)) {
            return;
        }

        $routeFile = __DIR__ . '/../routes/web.php';

        if (file_exists($routeFile)) {
            $this->loadRoutesFrom($routeFile);
        }
    }

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'performance-guard');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/performance-guard'),
        ], 'performance-guard-views');
    }
}
