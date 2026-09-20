<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Mail\MemberRegistrationReceivedMail;
use App\Mail\MembershipRenewLinkMail;
use App\Models\MemberProfile;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\SystemActivity;
use App\Models\User;
use App\Notifications\MemberRegistrationReceivedNotification;
use App\Services\NotificationRecipientResolver;
use App\Services\PaymongoPaymentService;
use App\Services\RecaptchaService;
use App\Services\SystemActivityService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Public sign-up (POST /register) and renewal (POST /renew). A renewal is only
 * reachable through the signed link mailed to the member, so an unauthenticated
 * form can never act on someone else's account by knowing their email.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private SystemActivityService $systemActivityService,
        private RecaptchaService $recaptchaService,
        private NotificationRecipientResolver $recipientResolver,
        private PaymongoPaymentService $paymongoPaymentService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'emergency_contact_name' => ['required', 'string', 'max:120'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
            'preferred_start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
            ...$this->planRules(),
        ]);

        $this->verifyCaptcha($data);

        if ($existing = User::query()->where('email', $data['email'])->first()) {
            // Members renew through their signed link; mail it so the inbox owner can continue.
            if ($existing->hasRole('member')) {
                Mail::to($existing->email)->queue(new MembershipRenewLinkMail($existing));
            }

            throw ValidationException::withMessages([
                'email' => [$existing->hasRole('member')
                    ? "You already have a membership with us - we've emailed you a link to renew."
                    : 'The email has already been taken.'],
            ]);
        }

        $this->assertDiscountPaidOnSite($data);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'password' => Hash::make(Str::random(40)),
                'status' => User::STATUS_INACTIVE,
            ]);

            $user->assignRole('member');

            $user->profile()->create([
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_phone' => $data['emergency_contact_phone'],
                'notes' => $data['notes'] ?? null,
                'discount_type' => $data['discount_type'] ?? null,
            ]);

            return $user;
        });

        return $this->enrol($user, $data, renewal: false);
    }

    /** GET /renew (signed). */
    public function renewForm(Request $request): View
    {
        $member = $this->signedMember($request);

        return view('home.renew', [
            'email' => $member->email,
            'discount' => $member->profile?->discount_type ?? '',
        ]);
    }

    /** POST /renew (signed): the member is the one named in the signed query, never the body. */
    public function renew(Request $request): JsonResponse
    {
        $member = $this->signedMember($request);

        if ($member->status === User::STATUS_SUSPENDED) {
            throw ValidationException::withMessages([
                'email' => ['This account is suspended - please talk to the front desk to renew.'],
            ]);
        }

        $data = $request->validate($this->planRules());

        $this->verifyCaptcha($data);

        // A discount on file is applied automatically (and still verified on-site).
        $data['discount_type'] = $member->profile?->discount_type ?? $data['discount_type'] ?? null;

        $this->assertDiscountPaidOnSite($data);

        return $this->enrol($member, $data, renewal: true);
    }

    public function success(): View
    {
        return view('home.register-result', ['result' => 'success']);
    }

    public function cancelled(): View
    {
        return view('home.register-result', ['result' => 'cancelled']);
    }

    /**
     * Create the pending subscription, start online checkout if asked, and only
     * then record activity, mail the member and notify staff - so a failed
     * checkout leaves nothing behind.
     *
     * @param  array<string, mixed>  $data
     */
    private function enrol(User $user, array $data, bool $renewal): JsonResponse
    {
        $plan = RatePlan::findOrFail((int) $data['rate_plan_id']);
        $discountType = $data['discount_type'] ?? null;
        $soldPrice = $discountType !== null
            ? MemberProfile::discountedPrice((float) $plan->price)
            : (float) $plan->price;
        $startDate = $data['preferred_start_date'] ?? ($renewal ? $user->nextMembershipStartDate() : now()->toDateString());
        $endDate = $plan->duration_days <= 1
            ? null
            : Carbon::parse($startDate)->addDays((int) $plan->duration_days - 1)->toDateString();
        $online = $data['payment_method'] === MemberSubscription::PENDING_PAYMENT_ONLINE;
        $source = $renewal ? 'public_renewal' : 'public_registration';

        $subscription = $user->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => $soldPrice,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => MemberSubscription::STATUS_PAUSED,
            'pending_payment_method' => $data['payment_method'],
        ])->setRelation('ratePlan', $plan);

        $payload = [
            'ok' => true,
            'message' => $online
                ? 'Redirecting to secure payment…'
                : "Thanks! We'll see you at the gym to complete payment and activate your access.",
        ];

        if ($online) {
            try {
                $payload['payment'] = $this->paymongoPaymentService->createCheckoutSession($subscription, $user);
            } catch (Throwable $e) {
                report($e);

                // Nothing else has happened yet, so undoing is just the new rows.
                // FK cascades (member_profiles, member_subscriptions) clean up a new member's children.
                $renewal ? $subscription->delete() : $user->forceDelete();

                return response()->json([
                    'ok' => false,
                    'message' => 'Online payment is temporarily unavailable. Please try again or pay on-site at the gym.',
                    'payment_error' => 'Online payment is temporarily unavailable.',
                ], 503);
            }
        }

        if ($renewal) {
            // An earlier unpaid sign-up is superseded by this one (Eloquent update so sync sees it).
            $user->memberSubscriptions()
                ->whereKeyNot($subscription->id)
                ->where('status', MemberSubscription::STATUS_PAUSED)
                ->whereNotNull('pending_payment_method')
                ->get()
                ->each->update(['status' => MemberSubscription::STATUS_CANCELLED, 'pending_payment_method' => null]);
        } else {
            $this->systemActivityService->recordSubjectEvent(
                SystemActivity::SUBJECT_MEMBER,
                $user->id,
                'created',
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                ],
                ['source' => $source, 'payment_method' => $data['payment_method'], 'discount_type' => $discountType],
            );
        }

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            $subscription->id,
            'created',
            [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_name' => $plan->name,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'status' => $subscription->status,
            ],
            ['source' => $source, 'payment_method' => $data['payment_method'], 'discount_type' => $discountType],
        );

        Mail::to($user->email)->queue(new MemberRegistrationReceivedMail($user, $subscription, $data['payment_method'], $renewal));

        $this->recipientResolver->send(new MemberRegistrationReceivedNotification($user, $subscription, $data['payment_method'], $renewal));

        return response()->json($payload, 201);
    }

    /** @return array<string, array<int, mixed>> */
    private function planRules(): array
    {
        return [
            'rate_plan_id' => [
                'required',
                Rule::exists('rate_plans', 'id')->where(
                    fn ($q) => $q->where('is_active', true)->where('is_walk_in_only', false)
                ),
            ],
            'terms_accepted' => ['accepted'],
            'payment_method' => ['required', Rule::in([MemberSubscription::PENDING_PAYMENT_ON_SITE, MemberSubscription::PENDING_PAYMENT_ONLINE])],
            'discount_type' => ['nullable', Rule::in(MemberProfile::discountTypes())],
            'recaptcha_token' => [filled(config('services.recaptcha.site_key')) ? 'required' : 'nullable', 'string'],
        ];
    }

    /** The member named by the signed /renew link (the `signed` middleware has already vouched for it). */
    private function signedMember(Request $request): User
    {
        return User::role('member')->where('email', (string) $request->query('email'))->firstOrFail();
    }

    /** @param  array<string, mixed>  $data */
    private function verifyCaptcha(array $data): void
    {
        if (filled(config('services.recaptcha.site_key')) && ! $this->recaptchaService->verify((string) $data['recaptcha_token'])) {
            throw ValidationException::withMessages([
                'recaptcha' => ['Verification failed. Please try again.'],
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function assertDiscountPaidOnSite(array $data): void
    {
        if (($data['discount_type'] ?? null) !== null && $data['payment_method'] !== MemberSubscription::PENDING_PAYMENT_ON_SITE) {
            throw ValidationException::withMessages([
                'payment_method' => ['Discounted rates must be paid on-site so staff can verify your ID.'],
            ]);
        }
    }
}
