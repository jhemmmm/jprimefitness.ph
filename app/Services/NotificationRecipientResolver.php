<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationRecipientResolver
{
    /**
     * @var Collection<int, User>|null
     */
    private ?Collection $recipients = null;

    /**
     * @return Collection<int, User>
     */
    public function resolve(): Collection
    {
        // ponytail: memoized for the request; several alerts in one action reuse the same recipient set
        return $this->recipients ??= User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super admin', 'admin', 'manager']))
            ->get();
    }

    public function send(Notification $notification): void
    {
        $recipients = $this->resolve();

        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients, $notification);
    }
}
