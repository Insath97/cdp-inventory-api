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

    /**
     * In-app only. This notification used to also go out over the 'mail'
     * channel, which meant every $user->notify() in the app silently emailed
     * the recipient -- and for the events that already send an explicit
     * Mailable (stock transfer, stock take, check in/out, damage, branch
     * request) that was a second, duplicate mail for the same event.
     * Anything that genuinely needs an email sends one via Mail::to() at the
     * call site instead.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}