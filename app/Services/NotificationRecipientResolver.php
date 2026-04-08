<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(?int $branchId = null): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super admin', 'admin', 'manager']))
            ->get();
    }

    public function send(Notification $notification, ?int $branchId = null): void
    {
        $recipients = $this->resolve($branchId);

        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients, $notification);
    }
}
