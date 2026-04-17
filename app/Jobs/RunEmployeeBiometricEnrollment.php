<?php

namespace App\Jobs;

use App\Models\EmployeeBiometricSession;
use App\Services\Hikvision\HikvisionBiometricService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunEmployeeBiometricEnrollment implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $sessionId,
    ) {}

    public function handle(HikvisionBiometricService $hikvisionBiometricService): void
    {
        $session = EmployeeBiometricSession::query()->find($this->sessionId);

        if (! $session) {
            return;
        }

        $hikvisionBiometricService->processEnrollment($session);
    }
}
