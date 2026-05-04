<?php

namespace Tests\Feature;

use App\Mail\MembershipQrCodeMail;
use App\Models\BusinessProfile;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\MembershipQrService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MembershipQrCodeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');
        Role::findOrCreate('coach');

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
        ]);
    }

    public function test_pos_membership_sale_generates_qr_and_emails_member(): void
    {
        Mail::fake();

        $staff = $this->createUserWithRole('staff', 'Staff Ben');
        $member = $this->createUserWithRole('member', 'Member Mia');
        $member->update(['email' => 'mia@example.com']);
        $ratePlan = $this->createRatePlan('Monthly', 30, ['price' => 1499]);

        $response = $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'member_id' => $member->id,
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-05-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 1500,
                'sold_at' => '2026-05-03 09:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('type', SaleTransaction::TYPE_MEMBERSHIP);

        $subscription = MemberSubscription::where('user_id', $member->id)->firstOrFail();

        $this->assertNotEmpty($subscription->qr_payload);
        $this->assertNotNull($subscription->qr_generated_at);
        $this->assertNotNull($subscription->qr_emailed_at);
        $this->assertStringStartsWith(MembershipQrService::PAYLOAD_PREFIX, $subscription->qr_payload);
        $this->assertNotEmpty($response->json('membership_qr_url'));

        Mail::assertQueued(MembershipQrCodeMail::class, function (MembershipQrCodeMail $mail) use ($member) {
            return (int) $mail->subscription->user_id === (int) $member->id;
        });
    }

    public function test_membership_sale_without_member_email_generates_qr_without_email(): void
    {
        Mail::fake();

        $staff = $this->createUserWithRole('staff', 'Staff Ben');
        $member = $this->createUserWithRole('member', 'Member No Email');
        $member->update(['email' => null]);
        $ratePlan = $this->createRatePlan('Monthly', 30, ['price' => 1499]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'member_id' => $member->id,
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-05-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 1500,
                'sold_at' => '2026-05-03 09:00:00',
            ])
            ->assertCreated();

        $subscription = MemberSubscription::where('user_id', $member->id)->firstOrFail();

        $this->assertNotEmpty($subscription->qr_payload);
        $this->assertNotNull($subscription->qr_generated_at);
        $this->assertNull($subscription->qr_emailed_at);

        Mail::assertNothingSent();
    }

    public function test_member_creation_generates_qr_and_sends_email(): void
    {
        Mail::fake();

        $staff = $this->createUserWithRole('manager', 'Manager Ana');
        $ratePlan = $this->createRatePlan('Monthly', 30, ['price' => 1499]);

        $response = $this->actingAs($staff)
            ->postJson('/panel/members', [
                'name' => 'Member Lina',
                'email' => 'lina@example.com',
                'phone' => '09171234567',
                'password' => 'password123',
                'status' => User::STATUS_ACTIVE,
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-05-01',
            ])
            ->assertCreated()
            ->assertJsonPath('member_subscriptions.0.rate_plan_id', $ratePlan->id);

        $subscription = MemberSubscription::findOrFail($response->json('member_subscriptions.0.id'));

        $this->assertNotEmpty($subscription->qr_payload);
        $this->assertNotEmpty($response->json('member_subscriptions.0.qr_url'));

        Mail::assertQueued(MembershipQrCodeMail::class, 1);
    }

    public function test_member_detail_membership_assignment_generates_qr_and_sends_email(): void
    {
        Mail::fake();

        $staff = $this->createUserWithRole('manager', 'Manager Ana');
        $member = $this->createUserWithRole('member', 'Member Rene');
        $member->update(['email' => 'rene@example.com']);
        $ratePlan = $this->createRatePlan('Quarterly', 90, ['price' => 3999]);

        $response = $this->actingAs($staff)
            ->putJson("/panel/members/{$member->id}/membership", [
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-05-01',
            ])
            ->assertOk()
            ->assertJsonPath('member_subscriptions.0.rate_plan_id', $ratePlan->id);

        $subscription = MemberSubscription::findOrFail($response->json('member_subscriptions.0.id'));

        $this->assertNotEmpty($subscription->qr_payload);
        $this->assertNotEmpty($response->json('member_subscriptions.0.qr_url'));

        Mail::assertQueued(MembershipQrCodeMail::class, 1);
    }

    public function test_panel_qr_endpoints_return_svg_data_uri(): void
    {
        Mail::fake();

        $staff = $this->createUserWithRole('staff', 'Staff Ben');
        $member = $this->createUserWithRole('member', 'Member Mia');
        $ratePlan = $this->createRatePlan('Monthly', 30, ['price' => 1499]);

        $transactionId = $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'member_id' => $member->id,
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-05-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 1500,
                'sold_at' => '2026-05-03 09:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $transaction = SaleTransaction::findOrFail($transactionId);
        $subscription = MemberSubscription::findOrFail(data_get($transaction->details, 'subscription_id'));

        $memberQr = $this->actingAs($staff)
            ->getJson(route('panel.members.memberships.qr', [$member, $subscription]))
            ->assertOk()
            ->assertJsonPath('membership_id', $subscription->id)
            ->json();

        $saleQr = $this->actingAs($staff)
            ->getJson(route('panel.sales.membership-qr', $transaction))
            ->assertOk()
            ->assertJsonPath('membership_id', $subscription->id)
            ->json();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $memberQr['qr_data_uri']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $saleQr['qr_data_uri']);
    }

    public function test_membership_qr_actions_are_rendered_in_sales_and_member_components(): void
    {
        $salesComponent = file_get_contents(resource_path('js/components/panel/SalesPage.vue'));
        $membershipComponent = file_get_contents(resource_path('js/components/panel/vendor/MemberMembershipPage.vue'));

        $this->assertStringContainsString('transaction.membership_qr_url', $salesComponent);
        $this->assertStringContainsString('membership.qr_url', $membershipComponent);
        $this->assertStringContainsString('openMembershipQr', $salesComponent);
        $this->assertStringContainsString('openMembershipQr', $membershipComponent);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRatePlan(string $name, int $durationDays, array $attributes = []): RatePlan
    {
        return RatePlan::create(array_merge([
            'name' => $name,
            'duration_days' => $durationDays,
            'description' => $name.' membership',
            'is_active' => true,
        ], $attributes));
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
