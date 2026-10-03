<?php

namespace App\Console\Commands;

use App\Models\KioskPayment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('kiosk:simulate-paid {reference? : Kiosk payment reference (default: latest pending)} {--force : Allow running outside APP_ENV=local}')]
#[Description('Dev helper: flip a pending kiosk payment to paid, the same way the PayMongo webhook would.')]
class SimulateKioskPaymentPaid extends Command
{
    public function handle(): int
    {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->error('Refusing to run outside APP_ENV=local. Pass --force to override.');

            return self::FAILURE;
        }

        $reference = $this->argument('reference');
        $payment = KioskPayment::findForCli($reference);

        if (! $payment) {
            $this->error($reference ? "No kiosk payment with reference {$reference}." : 'No pending kiosk payments to settle.');

            return self::FAILURE;
        }

        if ($payment->status === KioskPayment::STATUS_PAID) {
            $this->info("Already paid: {$payment->reference}");

            return self::SUCCESS;
        }

        $payment->update([
            'status' => KioskPayment::STATUS_PAID,
            'paid_at' => Carbon::now(),
        ]);

        $this->info("Marked paid: {$payment->reference} (pi={$payment->paymongo_payment_intent_id})");

        return self::SUCCESS;
    }
}
