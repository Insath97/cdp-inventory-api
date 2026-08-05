<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        if (!empty($this->payload['is_reminder'])) {
            return ['database'];
        }
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $title = $this->payload['title'] ?? 'Inventory Alert';
        $message = $this->payload['message'] ?? 'An inventory event has occurred.';
        $url = $this->payload['url'] ?? '/dashboard';
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('CDP Inventory: ' . $title)
            ->greeting('Hello ' . ($notifiable->name ?? 'User') . ',')
            ->line($message)
            ->action('View Details', $frontendUrl . '/' . ltrim($url, '/'))
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}