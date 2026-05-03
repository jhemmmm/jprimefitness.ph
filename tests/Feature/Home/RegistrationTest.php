<?php

namespace Tests\Feature\Home;

use App\Mail\MemberActivatedMail;
use App\Mail\MemberRegistrationReceivedMail;
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
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'rate_plan_id', 'terms_accepted', 'payment_method']);
    }

    public function test_validation_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/register', $this->validPayload(['email' => 'taken@example.com']));

        $response->assertStatus(422)->assertJsonValidationErrors('email');
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
