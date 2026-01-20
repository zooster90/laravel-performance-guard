<?php

use Illuminate\Support\Facades\Route;
use Zufarmarwah\PerformanceGuard\Http\Controllers\DashboardController;
use Zufarmarwah\PerformanceGuard\Http\Middleware\AuthorizeDashboard;

$path = config('performance-guard.dashboard.path', 'performance-guard');
$middleware = array_merge(
    config('performance-guard.dashboard.middleware', ['web']),
    [AuthorizeDashboard::class]
);

Route::prefix($path)
    ->middleware($middleware)
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('performance-guard.dashboard');
        Route::get('/api', [DashboardController::class, 'api'])->name('performance-guard.api');
        Route::get('/api/{uuid}', [DashboardController::class, 'show'])->name('performance-guard.show');
        Route::get('/n-plus-one', [DashboardController::class, 'nPlusOne'])->name('performance-guard.n-plus-one');
        Route::get('/slow-queries', [DashboardController::class, 'slowQueries'])->name('performance-guard.slow-queries');
    });
