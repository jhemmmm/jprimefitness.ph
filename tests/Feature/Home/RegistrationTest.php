<?php

namespace Tests\Feature\Home;

use App\Mail\MemberActivatedMail;
use App\Mail\MemberRegistrationReceivedMail;
use App\Mail\MembershipRenewLinkMail;
use App\Models\BusinessProfile;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\SystemActivity;
use App\Models\User;
use App\Notifications\MemberActivatedNotification;
use App\Notifications\MemberRegistrationReceivedNotification;
use App\Services\MemberActivationService;
use App\Services\MembershipQrService;
use App\Services\PaymongoPaymentService;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private RatePlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('member');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('super admin');

        BusinessProfile::factory()->create();

        $this->plan = RatePlan::create([
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 1500,
            'is_active' => true,
        ]);

        // Default: bypass reCAPTCHA in tests.
        $this->app->bind(RecaptchaService::class, function () {
            $mock = Mockery::mock(RecaptchaService::class);
            $mock->shouldReceive('verify')->andReturn(true);

            return $mock;
        });

        Mail::fake();
        Notification::fake();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'date_of_birth' => '1995-04-12',
            'gender' => 'male',
            'emergency_contact_name' => 'Maria Cruz',
            'emergency_contact_phone' => '09181234567',
            'rate_plan_id' => $this->plan->id,
            'preferred_start_date' => now()->addDay()->toDateString(),
            'notes' => 'New member',
            'payment_method' => 'on_site',
            'terms_accepted' => true,
            'recaptcha_token' => 'fake-token',
        ], $overrides);
    }

    public function test_on_site_registration_creates_pending_member_paused_subscription_and_audit_records(): void
    {
        $response = $this->postJson('/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('ok', true);

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame(User::STATUS_INACTIVE, $user->status);
        $this->assertTrue($user->hasRole('member'));
        $this->assertNotNull($user->profile);
        $this->assertSame('Maria Cruz', $user->profile->emergency_contact_name);

        $subscription = $user->memberSubscriptions()->firstOrFail();
        $this->assertSame(MemberSubscription::STATUS_PAUSED, $subscription->status);
        $this->assertSame((int) $this->plan->id, (int) $subscription->rate_plan_id);
        $this->assertNull($subscription->qr_emailed_at);

        $this->assertSame(1, SystemActivity::where('subject_type', SystemActivity::SUBJECT_MEMBER)->where('subject_id', $user->id)->count());
        $this->assertSame(1, SystemActivity::where('subject_type', SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION)->where('subject_id', $subscription->id)->count());

        Mail::assertQueued(MemberRegistrationReceivedMail::class, fn ($m) => $m->hasTo('juan@example.com'));
    }

    public function test_online_registration_returns_checkout_url_from_paymongo(): void
    {
        $this->app->bind(PaymongoPaymentService::class, function () {
            $mock = Mockery::mock(PaymongoPaymentService::class);
            $mock->shouldReceive('createCheckoutSession')->andReturn([
                'id' => 'cs_test_123',
                'checkout_url' => 'https://paymongo.test/checkout/cs_test_123',
            ]);

            return $mock;
        });

        $response = $this->postJson('/register', $this->validPayload([
            'email' => 'gcash@example.com',
            'payment_method' => 'online',
        ]));

        $response->assertCreated()
            ->assertJsonPath('payment.checkout_url', 'https://paymongo.test/checkout/cs_test_123');
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone', 'rate_plan_id', 'terms_accepted', 'payment_method']);
    }

    public function test_validation_rejects_an_email_that_belongs_to_a_non_member_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/register', $this->validPayload(['email' => 'taken@example.com']));

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_existing_member_email_gets_a_renewal_link_instead_of_a_new_account(): void
    {
        $member = $this->createMember();

        // the home form never turns into a renewal on its own: only the inbox owner can continue
        $this->postJson('/register', $this->validPayload(['email' => 'ana@example.com', 'name' => 'Someone Else']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, User::where('email', 'ana@example.com')->count());
        $this->assertSame('Ana Santos', $member->fresh()->name);
        $this->assertSame(0, $member->memberSubscriptions()->count());
        Mail::assertQueued(MembershipRenewLinkMail::class, fn ($mail) => $mail->hasTo('ana@example.com'));
        Mail::assertNotQueued(MemberRegistrationReceivedMail::class);
    }

    public function test_signed_renew_link_creates_a_pending_renewal_for_that_member(): void
    {
        $member = $this->createMember();
        $current = $member->memberSubscriptions()->create([
            'rate_plan_id' => $this->plan->id, 'sold_price' => 1500, 'status' => MemberSubscription::STATUS_ACTIVE,
            'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
        ]);
        $unpaid = $member->memberSubscriptions()->create([
            'rate_plan_id' => $this->plan->id, 'sold_price' => 1500, 'status' => MemberSubscription::STATUS_PAUSED,
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(29)->toDateString(), 'pending_payment_method' => 'on_site',
        ]);
        $payload = ['rate_plan_id' => $this->plan->id, 'payment_method' => 'on_site', 'terms_accepted' => true];

        // the page and the POST both need the signed link; the body can't name another member
        $this->get('/renew?email=ana@example.com')->assertForbidden();
        $this->postJson('/renew?email=ana@example.com', $payload)->assertForbidden();
        $this->get(URL::signedRoute('renew', ['email' => 'ana@example.com']))
            ->assertOk()
            ->assertSee('<registration-form renew', false)
            ->assertSee('initial-email="ana@example.com"', false);

        $this->postJson(URL::signedRoute('renew.store', ['email' => 'ana@example.com']), $payload + ['email' => 'other@example.com', 'name' => 'Someone Else'])
            ->assertCreated();

        $this->assertSame('Ana Santos', $member->fresh()->name); // never overwritten from the public form
        $renewal = $member->memberSubscriptions()->latest('id')->first();
        $this->assertSame(MemberSubscription::STATUS_PAUSED, $renewal->status);
        $this->assertSame('on_site', $renewal->pending_payment_method);
        // starts the day after the current membership ends
        $this->assertSame(now()->addDays(10)->toDateString(), $renewal->start_date->toDateString());
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $current->fresh()->status);
        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $unpaid->fresh()->status);
        // the running plan stays "current" for panel actions; the upcoming renewal does not take over
        $this->assertTrue($member->currentMembership()->is($current));

        Mail::assertQueued(MemberRegistrationReceivedMail::class, fn ($mail) => $mail->hasTo('ana@example.com') && $mail->renewal === true);
        $this->assertDatabaseHas('system_activities', ['subject_type' => SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION, 'subject_id' => $renewal->id]);
        $this->assertDatabaseMissing('system_activities', ['subject_type' => SystemActivity::SUBJECT_MEMBER, 'subject_id' => $member->id]);
    }

    public function test_renewal_applies_the_discount_on_file_and_blocks_suspended_members(): void
    {
        $member = $this->createMember(['status' => User::STATUS_ACTIVE], ['discount_type' => 'student']);
        $url = URL::signedRoute('renew.store', ['email' => 'ana@example.com']);

        $this->get(URL::signedRoute('renew', ['email' => 'ana@example.com']))->assertSee('initial-discount="student"', false);

        // the stored discount wins over the form (so the online shortcut is closed) and prices the plan
        $this->postJson($url, ['rate_plan_id' => $this->plan->id, 'payment_method' => 'online', 'terms_accepted' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
        $this->postJson($url, ['rate_plan_id' => $this->plan->id, 'payment_method' => 'on_site', 'terms_accepted' => true])
            ->assertCreated();
        $this->assertSame(1200.0, (float) $member->memberSubscriptions()->latest('id')->value('sold_price'));

        $member->forceFill(['status' => User::STATUS_SUSPENDED])->save();
        $this->postJson($url, ['rate_plan_id' => $this->plan->id, 'payment_method' => 'on_site', 'terms_accepted' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_failed_online_checkout_on_renewal_leaves_the_earlier_sign_up_untouched(): void
    {
        $this->app->bind(PaymongoPaymentService::class, function () {
            $mock = Mockery::mock(PaymongoPaymentService::class);
            $mock->shouldReceive('createCheckoutSession')->andThrow(new \RuntimeException('PayMongo down'));

            return $mock;
        });
        $member = $this->createMember();
        $unpaid = $member->memberSubscriptions()->create([
            'rate_plan_id' => $this->plan->id, 'sold_price' => 1500, 'status' => MemberSubscription::STATUS_PAUSED,
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(29)->toDateString(), 'pending_payment_method' => 'on_site',
        ]);

        $this->postJson(URL::signedRoute('renew.store', ['email' => 'ana@example.com']), ['rate_plan_id' => $this->plan->id, 'payment_method' => 'online', 'terms_accepted' => true])
            ->assertStatus(503);

        $this->assertSame(MemberSubscription::STATUS_PAUSED, $unpaid->fresh()->status);
        $this->assertSame(1, $member->memberSubscriptions()->count());
        Mail::assertNothingQueued();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $profile
     */
    private function createMember(array $attributes = [], array $profile = []): User
    {
        $member = User::factory()->create(['name' => 'Ana Santos', 'email' => 'ana@example.com', 'phone' => '09170000000', 'status' => User::STATUS_ACTIVE, ...$attributes]);
        $member->assignRole('member');
        $member->profile()->create(['emergency_contact_name' => 'Kin Ana', 'emergency_contact_phone' => '09170000001', ...$profile]);

        return $member;
    }

    public function test_validation_rejects_inactive_rate_plan(): void
    {
        $inactive = RatePlan::create([
            'name' => 'Old Plan',
            'duration_days' => 90,
            'price' => 4000,
            'is_active' => false,
        ]);

        $response = $this->postJson('/register', $this->validPayload(['rate_plan_id' => $inactive->id]));

        $response->assertStatus(422)->assertJsonValidationErrors('rate_plan_id');
    }

    public function test_validation_rejects_unaccepted_terms(): void
    {
        $response = $this->postJson('/register', $this->validPayload(['terms_accepted' => false]));

        $response->assertStatus(422)->assertJsonValidationErrors('terms_accepted');
    }

    public function test_validation_rejects_walk_in_only_plan(): void
    {
        $walkInPlan = RatePlan::create([
            'name' => 'Daily Pass',
            'duration_days' => 1,
            'price' => 120,
            'is_active' => true,
            'is_walk_in_only' => true,
        ]);

        $response = $this->postJson('/register', $this->validPayload(['rate_plan_id' => $walkInPlan->id]));

        $response->assertStatus(422)->assertJsonValidationErrors('rate_plan_id');
    }

    public function test_activation_service_flips_pending_member_to_active_and_sends_qr(): void
    {
        $qrSpy = Mockery::mock(MembershipQrService::class);
        $qrSpy->shouldReceive('sendEmail')->once();
        $this->app->instance(MembershipQrService::class, $qrSpy);

        $this->postJson('/register', $this->validPayload())->assertCreated();
        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $subscription = $user->memberSubscriptions()->firstOrFail();

        app(MemberActivationService::class)->activate($subscription);

        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $subscription->fresh()->status);

        Mail::assertQueued(MemberActivatedMail::class);
        Notification::assertNothingSent(); // no admin recipients seeded in this test
    }

    public function test_staff_notification_dispatched_on_registration(): void
    {
        // Seed an admin so the recipient resolver finds someone.
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->postJson('/register', $this->validPayload())->assertCreated();

        Notification::assertSentTo($admin, MemberRegistrationReceivedNotification::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
