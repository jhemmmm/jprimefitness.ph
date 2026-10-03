<?php

namespace Tests\Feature;

use App\Mail\MembershipExpiryMail;
use App\Models\BusinessProfile;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\User;
use App\Services\Sync\SyncRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExpireMembershipsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_memberships_past_their_end_date_expire_and_the_member_is_emailed(): void
    {
        Mail::fake();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);
        $this->travelTo(Carbon::parse('2026-05-01 00:05:00'));

        $ratePlan = RatePlan::create(['name' => 'Monthly', 'duration_days' => 30, 'price' => 1500, 'is_active' => true]);
        $member = User::factory()->create(['name' => 'Member Noel', 'email' => 'noel@example.test', 'status' => User::STATUS_ACTIVE]);
        $member->assignRole('member');

        $create = fn (string $status, ?string $endDate) => $member->memberSubscriptions()->create([
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 1500,
            'start_date' => '2026-04-01',
            'end_date' => $endDate,
            'status' => $status,
        ]);

        $ended = $create(MemberSubscription::STATUS_ACTIVE, '2026-04-30');
        // a member who already renewed gets no "expired" mail
        $renewed = User::factory()->create(['name' => 'Member Rina', 'email' => 'rina@example.test', 'status' => User::STATUS_ACTIVE]);
        $renewed->assignRole('member');
        foreach ([['2026-04-01', '2026-04-30'], ['2026-05-01', '2026-05-30']] as [$start, $end]) {
            $renewed->memberSubscriptions()->create(['rate_plan_id' => $ratePlan->id, 'sold_price' => 1500, 'start_date' => $start, 'end_date' => $end, 'status' => MemberSubscription::STATUS_ACTIVE]);
        }
        $endsToday = $create(MemberSubscription::STATUS_ACTIVE, '2026-05-01');
        $paused = $create(MemberSubscription::STATUS_PAUSED, '2026-04-30');
        $openEnded = $create(MemberSubscription::STATUS_ACTIVE, null);

        $this->artisan('panel:expire-memberships')
            ->expectsOutputToContain('Expired 2 membership(s).')
            ->assertExitCode(0);

        $this->assertSame(MemberSubscription::STATUS_EXPIRED, $ended->fresh()->status);
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $endsToday->fresh()->status);
        $this->assertSame(MemberSubscription::STATUS_PAUSED, $paused->fresh()->status);
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $openEnded->fresh()->status);

        $this->assertSame([MemberSubscription::STATUS_EXPIRED, MemberSubscription::STATUS_ACTIVE], $renewed->memberSubscriptions()->orderBy('start_date')->pluck('status')->all());
        Mail::assertQueued(MembershipExpiryMail::class, fn (MembershipExpiryMail $mail) => $mail->hasTo('noel@example.test') && $mail->subscription->is($ended) && $mail->daysRemaining === null);
        Mail::assertQueuedCount(1);

        // re-running is a no-op: the row is no longer active
        $this->artisan('panel:expire-memberships')->assertExitCode(0);
        Mail::assertQueuedCount(1);

        $this->travelBack();
    }

    public function test_the_local_node_expires_memberships_and_leaves_the_email_to_live(): void
    {
        Mail::fake();
        config(['sync.role' => SyncRole::LOCAL]);
        $this->travelTo(Carbon::parse('2026-05-01 00:05:00'));

        $ratePlan = RatePlan::create(['name' => 'Monthly', 'duration_days' => 30, 'price' => 1500, 'is_active' => true]);
        $member = User::factory()->create(['email' => 'noel@example.test', 'status' => User::STATUS_ACTIVE]);
        $member->assignRole('member');
        $ended = $member->memberSubscriptions()->create(['rate_plan_id' => $ratePlan->id, 'sold_price' => 1500, 'start_date' => '2026-04-01', 'end_date' => '2026-04-30', 'status' => MemberSubscription::STATUS_ACTIVE]);
        $expiring = $member->memberSubscriptions()->create(['rate_plan_id' => $ratePlan->id, 'sold_price' => 1500, 'start_date' => '2026-04-05', 'end_date' => '2026-05-04', 'status' => MemberSubscription::STATUS_ACTIVE]);

        $this->artisan('panel:expire-memberships')->assertExitCode(0);
        $this->artisan('panel:send-expiring-membership-notifications')->assertExitCode(0);

        $this->assertSame(MemberSubscription::STATUS_EXPIRED, $ended->fresh()->status);
        $this->assertNotNull($expiring->fresh()->expiration_notification_sent_for_date);
        Mail::assertNothingQueued();

        $this->travelBack();
    }
}
