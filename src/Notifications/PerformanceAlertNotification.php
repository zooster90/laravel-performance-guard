<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PerformanceAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $alertData
     */
    public function __construct(
        private readonly array $alertData,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        $config = config('performance-guard.notifications.channels', []);

        if (! empty($config['email']['enabled'])) {
            $channels[] = 'mail';
        }

        if (! empty($config['slack']['enabled']) && ! empty($config['slack']['webhook_url'])) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage;
        $message->subject('Performance Alert: ' . $this->alertData['type']);
        $message->line('A performance issue has been detected in your application.');
        $message->line('');
        $message->line('**Type:** ' . $this->alertData['type']);
        $message->line('**URI:** ' . ($this->alertData['uri'] ?? 'N/A'));
        $message->line('**Duration:** ' . ($this->alertData['duration_ms'] ?? 'N/A') . 'ms');
        $message->line('**Grade:** ' . ($this->alertData['grade'] ?? 'N/A'));

        if (! empty($this->alertData['details'])) {
            $message->line('**Details:** ' . $this->alertData['details']);
        }

        if (! empty($this->alertData['suggestion'])) {
            $message->line('**Suggestion:** ' . $this->alertData['suggestion']);
        }

        $dashboardUrl = config('app.url', '') . '/' . config('performance-guard.dashboard.path', 'performance-guard');
        $message->action('View Dashboard', $dashboardUrl);

        return $message;
    }

    /**
     * Slack notification via webhook (array format, compatible with all Laravel versions).
     *
     * @return array<string, mixed>
     */
    public function toSlack(object $notifiable): array
    {
        $fields = [
            ['title' => 'Type', 'value' => $this->alertData['type'], 'short' => true],
            ['title' => 'URI', 'value' => $this->alertData['uri'] ?? 'N/A', 'short' => true],
            ['title' => 'Duration', 'value' => ($this->alertData['duration_ms'] ?? 'N/A') . 'ms', 'short' => true],
            ['title' => 'Grade', 'value' => $this->alertData['grade'] ?? 'N/A', 'short' => true],
            ['title' => 'Queries', 'value' => (string) ($this->alertData['query_count'] ?? 'N/A'), 'short' => true],
        ];

        return [
            'text' => 'Performance Alert: ' . $this->alertData['type'],
            'attachments' => [
                [
                    'color' => 'warning',
                    'title' => 'Performance Issue Detected',
                    'fields' => $fields,
                    'footer' => $this->alertData['suggestion'] ?? '',
                ],
            ],
        ];
    }

    /**
     * Telegram notification message (plain text with Markdown).
     */
    public function toTelegram(): string
    {
        $lines = [
            '*Performance Alert: ' . $this->alertData['type'] . '*',
            '',
            'URI: `' . ($this->alertData['uri'] ?? 'N/A') . '`',
            'Duration: ' . ($this->alertData['duration_ms'] ?? 'N/A') . 'ms',
            'Grade: ' . ($this->alertData['grade'] ?? 'N/A'),
            'Queries: ' . ($this->alertData['query_count'] ?? 'N/A'),
        ];

        if (! empty($this->alertData['details'])) {
            $lines[] = 'Details: ' . $this->alertData['details'];
        }

        if (! empty($this->alertData['suggestion'])) {
            $lines[] = '';
            $lines[] = '_Suggestion: ' . $this->alertData['suggestion'] . '_';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->alertData;
    }
}
