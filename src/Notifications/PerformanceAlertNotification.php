<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
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

        if (! empty($config['slack']['enabled'])) {
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

    public function toSlack(object $notifiable): SlackMessage
    {
        $message = new SlackMessage;
        $message->warning();
        $message->content('Performance Alert: ' . $this->alertData['type']);
        $message->attachment(function ($attachment) {
            $attachment->title('Performance Issue Detected')
                ->fields([
                    'Type' => $this->alertData['type'],
                    'URI' => $this->alertData['uri'] ?? 'N/A',
                    'Duration' => ($this->alertData['duration_ms'] ?? 'N/A') . 'ms',
                    'Grade' => $this->alertData['grade'] ?? 'N/A',
                    'Query Count' => (string) ($this->alertData['query_count'] ?? 'N/A'),
                ]);

            if (! empty($this->alertData['suggestion'])) {
                $attachment->footer($this->alertData['suggestion']);
            }
        });

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->alertData;
    }
}
