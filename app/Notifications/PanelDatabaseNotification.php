<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

abstract class PanelDatabaseNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    final public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    final public function toDatabase(object $notifiable): array
    {
        return $this->data();
    }

    /**
     * @return array<string, mixed>
     */
    final public function toArray(object $notifiable): array
    {
        return $this->data();
    }

    final public function databaseType(object $notifiable): string
    {
        return $this->typeSlug();
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function data(): array;

    abstract protected function typeSlug(): string;
}
