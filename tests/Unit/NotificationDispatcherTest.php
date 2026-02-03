<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Zufarmarwah\PerformanceGuard\Notifications\NotificationDispatcher;
use Zufarmarwah\PerformanceGuard\Notifications\PerformanceAlertNotification;

beforeEach(function () {
    $this->dispatcher = new NotificationDispatcher;

    config([
        'performance-guard.notifications.enabled' => true,
        'performance-guard.notifications.cooldown_minutes' => 15,
        'performance-guard.notifications.notify_on' => [
            'n_plus_one' => true,
            'slow_query' => true,
            'slow_request' => true,
            'high_memory' => true,
            'grade_f' => true,
        ],
        'performance-guard.notifications.channels.email.enabled' => false,
        'performance-guard.notifications.channels.email.recipients' => [],
        'performance-guard.notifications.channels.slack.enabled' => false,
        'performance-guard.notifications.channels.slack.webhook_url' => null,
        'performance-guard.notifications.channels.telegram.enabled' => false,
        'performance-guard.notifications.channels.telegram.bot_token' => null,
        'performance-guard.notifications.channels.telegram.chat_id' => null,
        'performance-guard.thresholds.slow_request_ms' => 1000,
        'performance-guard.thresholds.memory_mb' => 128,
    ]);

    Cache::flush();
});

it('does nothing when notifications are disabled', function () {
    config(['performance-guard.notifications.enabled' => false]);

    Http::fake();
    Notification::fake();

    $this->dispatcher->dispatch(
        ['uri' => '/test', 'duration_ms' => 5000, 'grade' => 'F', 'query_count' => 100],
        ['hasNPlusOne' => true, 'duplicates' => [['count' => 10]], 'suggestions' => ['Eager load']],
        [['sql' => 'select 1', 'duration' => 500]],
        200.0
    );

    Http::assertNothingSent();
    Notification::assertNothingSent();
});

it('sends slack notification for n_plus_one', function () {
    Http::fake();

    config([
        'performance-guard.notifications.channels.slack.enabled' => true,
        'performance-guard.notifications.channels.slack.webhook_url' => 'https://hooks.slack.com/test',
    ]);

    $this->dispatcher->dispatch(
        ['uri' => '/api/users', 'duration_ms' => 200, 'grade' => 'A', 'query_count' => 30],
        ['hasNPlusOne' => true, 'duplicates' => [['count' => 15]], 'suggestions' => ['Use eager loading']],
        [],
    );

    Http::assertSentCount(1);
});

it('sends telegram notification for slow request', function () {
    Http::fake();

    config([
        'performance-guard.notifications.channels.telegram.enabled' => true,
        'performance-guard.notifications.channels.telegram.bot_token' => 'test-bot-token',
        'performance-guard.notifications.channels.telegram.chat_id' => '-100123',
    ]);

    $this->dispatcher->dispatch(
        ['uri' => '/slow', 'duration_ms' => 5000, 'grade' => 'F', 'query_count' => 50],
        ['hasNPlusOne' => false, 'duplicates' => [], 'suggestions' => []],
        [],
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org/bottest-bot-token/sendMessage');
    });
});

it('respects cooldown period', function () {
    Http::fake();

    config([
        'performance-guard.notifications.channels.slack.enabled' => true,
        'performance-guard.notifications.channels.slack.webhook_url' => 'https://hooks.slack.com/test',
    ]);

    $recordData = ['uri' => '/api/users', 'duration_ms' => 200, 'grade' => 'A', 'query_count' => 30];
    $analysis = ['hasNPlusOne' => true, 'duplicates' => [['count' => 15]], 'suggestions' => ['Eager load']];

    $this->dispatcher->dispatch($recordData, $analysis, []);

    Http::assertSentCount(1);

    Http::fake();

    $this->dispatcher->dispatch($recordData, $analysis, []);

    Http::assertNothingSent();
});

it('sends alert for high memory usage', function () {
    Http::fake();

    config([
        'performance-guard.notifications.channels.slack.enabled' => true,
        'performance-guard.notifications.channels.slack.webhook_url' => 'https://hooks.slack.com/test',
    ]);

    $this->dispatcher->dispatch(
        ['uri' => '/heavy', 'duration_ms' => 200, 'grade' => 'A', 'query_count' => 5],
        ['hasNPlusOne' => false, 'duplicates' => [], 'suggestions' => []],
        [],
        200.0,
    );

    Http::assertSentCount(1);
});

it('sends alert for grade F', function () {
    Http::fake();

    config([
        'performance-guard.notifications.channels.slack.enabled' => true,
        'performance-guard.notifications.channels.slack.webhook_url' => 'https://hooks.slack.com/test',
    ]);

    $this->dispatcher->dispatch(
        ['uri' => '/bad', 'duration_ms' => 100, 'grade' => 'F', 'query_count' => 5],
        ['hasNPlusOne' => false, 'duplicates' => [], 'suggestions' => []],
        [],
    );

    Http::assertSentCount(1);
});
