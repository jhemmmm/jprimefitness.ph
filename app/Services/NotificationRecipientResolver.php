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
        $administrators = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super admin', 'admin']))
            ->get();

        if ($branchId === null) {
            return $administrators->unique('id')->values();
        }

        $managers = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'manager'))
            ->whereHas('branches', fn ($query) => $query->whereKey($branchId))
            ->get();

        return $administrators->concat($managers)->unique('id')->values();
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
