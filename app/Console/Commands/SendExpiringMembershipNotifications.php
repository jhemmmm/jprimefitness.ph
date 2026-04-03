<?php

namespace App\Console\Commands;

use App\Services\ExpiringMembershipNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('panel:send-expiring-membership-notifications')]
#[Description('Send in-app notifications for memberships expiring within seven days')]
class SendExpiringMembershipNotifications extends Command
{
    public function __construct(private ExpiringMembershipNotificationService $expiringMembershipNotificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $notifiedSubscriptions = $this->expiringMembershipNotificationService->sendDueNotifications();

        $this->info("Sent {$notifiedSubscriptions} expiring membership notification(s).");

        return self::SUCCESS;
    }
}
