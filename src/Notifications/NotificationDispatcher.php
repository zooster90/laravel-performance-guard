<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    /**
     * @param  array<string, mixed>  $recordData
     * @param  array{hasNPlusOne: bool, duplicates: array, suggestions: array}  $analysis
     * @param  array<int, array>  $slowQueries
     */
    public function dispatch(array $recordData, array $analysis, array $slowQueries, float $memoryMb = 0): void
    {
        if (! config('performance-guard.notifications.enabled', false)) {
            return;
        }

        $notifyOn = config('performance-guard.notifications.notify_on', []);

        if (! empty($notifyOn['n_plus_one']) && $analysis['hasNPlusOne']) {
            $this->sendAlert([
                'type' => 'N+1 Query Detected',
                'uri' => $recordData['uri'] ?? '',
                'duration_ms' => $recordData['duration_ms'] ?? 0,
                'grade' => $recordData['grade'] ?? '',
                'query_count' => $recordData['query_count'] ?? 0,
                'details' => count($analysis['duplicates']) . ' duplicate query pattern(s) found',
                'suggestion' => $analysis['suggestions'][0] ?? '',
            ]);
        }

        if (! empty($notifyOn['slow_query']) && count($slowQueries) > 0) {
            $this->sendAlert([
                'type' => 'Slow Query Detected',
                'uri' => $recordData['uri'] ?? '',
                'duration_ms' => $recordData['duration_ms'] ?? 0,
                'grade' => $recordData['grade'] ?? '',
                'query_count' => $recordData['query_count'] ?? 0,
                'details' => count($slowQueries) . ' slow query(ies) detected',
                'suggestion' => 'Review query execution plans and consider adding indexes.',
            ]);
        }

        $slowRequestThreshold = config('performance-guard.thresholds.slow_request_ms', 1000);

        if (! empty($notifyOn['slow_request']) && ($recordData['duration_ms'] ?? 0) > $slowRequestThreshold) {
            $this->sendAlert([
                'type' => 'Slow Request',
                'uri' => $recordData['uri'] ?? '',
                'duration_ms' => $recordData['duration_ms'] ?? 0,
                'grade' => $recordData['grade'] ?? '',
                'query_count' => $recordData['query_count'] ?? 0,
                'details' => 'Request exceeded ' . $slowRequestThreshold . 'ms threshold',
                'suggestion' => 'Review middleware stack, database queries, and external API calls.',
            ]);
        }

        $memoryThreshold = (float) config('performance-guard.thresholds.memory_mb', 128);

        if (! empty($notifyOn['high_memory']) && $memoryMb > $memoryThreshold) {
            $this->sendAlert([
                'type' => 'High Memory Usage',
                'uri' => $recordData['uri'] ?? '',
                'duration_ms' => $recordData['duration_ms'] ?? 0,
                'grade' => $recordData['grade'] ?? '',
                'query_count' => $recordData['query_count'] ?? 0,
                'details' => 'Memory usage: ' . round($memoryMb, 2) . 'MB (threshold: ' . $memoryThreshold . 'MB)',
                'suggestion' => 'Review data loading patterns and consider chunking or streaming large datasets.',
            ]);
        }

        if (! empty($notifyOn['grade_f']) && ($recordData['grade'] ?? '') === 'F') {
            $this->sendAlert([
                'type' => 'Grade F Request',
                'uri' => $recordData['uri'] ?? '',
                'duration_ms' => $recordData['duration_ms'] ?? 0,
                'grade' => 'F',
                'query_count' => $recordData['query_count'] ?? 0,
                'details' => 'Request received the lowest performance grade',
                'suggestion' => 'Immediate optimization recommended.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $alertData
     */
    private function sendAlert(array $alertData): void
    {
        $cooldownMinutes = (int) config('performance-guard.notifications.cooldown_minutes', 15);
        $cacheKey = 'performance-guard:alert:' . md5($alertData['type'] . ($alertData['uri'] ?? ''));

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));

        $notification = new PerformanceAlertNotification($alertData);

        $emailRecipients = config('performance-guard.notifications.channels.email.recipients', []);

        if (! empty($emailRecipients)) {
            Notification::route('mail', $emailRecipients)->notify($notification);
        }

        $slackWebhook = config('performance-guard.notifications.channels.slack.webhook_url');

        if (! empty($slackWebhook)) {
            $payload = $notification->toSlack(new \stdClass);
            Http::post($slackWebhook, $payload);
        }
    }
}
