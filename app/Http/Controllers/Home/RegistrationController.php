<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Mail\MemberRegistrationReceivedMail;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        $captchaConfigured = filled(config('services.recaptcha.site_key'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->withoutTrashed()],
            'phone' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'rate_plan_id' => [
                'required',
                Rule::exists('rate_plans', 'id')->where(
                    fn ($q) => $q->where('is_active', true)->where('is_walk_in_only', false)
                ),
            ],
            'preferred_start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'terms_accepted' => ['accepted'],
            'payment_method' => ['required', Rule::in([MemberSubscription::PENDING_PAYMENT_ON_SITE, MemberSubscription::PENDING_PAYMENT_ONLINE])],
            'discount_type' => ['nullable', Rule::in([MemberProfile::DISCOUNT_STUDENT, MemberProfile::DISCOUNT_SENIOR])],
            'recaptcha_token' => [$captchaConfigured ? 'required' : 'nullable', 'string'],
        ]);

        if ($captchaConfigured && ! $this->recaptchaService->verify((string) $data['recaptcha_token'])) {
            throw ValidationException::withMessages([
                'recaptcha' => ['Verification failed. Please try again.'],
            ]);
        }

        $discountType = $data['discount_type'] ?? null;

        if ($discountType !== null && $data['payment_method'] !== MemberSubscription::PENDING_PAYMENT_ON_SITE) {
            throw ValidationException::withMessages([
                'payment_method' => ['Student and senior discounts must be paid on-site so staff can verify your ID.'],
            ]);
        }

        $plan = RatePlan::findOrFail((int) $data['rate_plan_id']);
        $soldPrice = $discountType !== null
            ? MemberProfile::discountedPrice((float) $plan->price)
            : (float) $plan->price;
        $startDate = $data['preferred_start_date'] ?? now()->toDateString();
        $endDate = $plan->duration_days <= 1
            ? null
            : Carbon::parse($startDate)->addDays((int) $plan->duration_days - 1)->toDateString();

        [$user, $subscription] = DB::transaction(function () use ($data, $plan, $soldPrice, $startDate, $endDate) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make(Str::random(40)),
                'status' => User::STATUS_INACTIVE,
            ]);

            $user->assignRole('member');

            $user->profile()->create([
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'discount_type' => $data['discount_type'] ?? null,
            ]);

            $subscription = $user->memberSubscriptions()->create([
                'rate_plan_id' => $plan->id,
                'sold_price' => $soldPrice,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => MemberSubscription::STATUS_PAUSED,
                'pending_payment_method' => $data['payment_method'],
            ]);

            return [$user, $subscription];
        });

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
            ['source' => 'public_registration', 'payment_method' => $data['payment_method'], 'discount_type' => $discountType],
        );

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
            ['source' => 'public_registration', 'payment_method' => $data['payment_method'], 'discount_type' => $discountType],
        );

        Mail::to($user->email)->queue(new MemberRegistrationReceivedMail(
            $user,
            $subscription->fresh(['ratePlan']),
            $data['payment_method'],
        ));

        $this->recipientResolver->send(new MemberRegistrationReceivedNotification(
            $user,
            $subscription->fresh(['ratePlan']),
            $data['payment_method'],
        ));

        $payload = [
            'ok' => true,
            'message' => $data['payment_method'] === MemberSubscription::PENDING_PAYMENT_ONLINE
                ? 'Redirecting to secure payment…'
                : "Thanks! We'll see you at the gym to complete payment and activate your access.",
        ];

        if ($data['payment_method'] === MemberSubscription::PENDING_PAYMENT_ONLINE) {
            try {
                $checkout = $this->paymongoPaymentService->createCheckoutSession($subscription->fresh(['ratePlan']), $user);
                $payload['payment'] = $checkout;
            } catch (Throwable $e) {
                report($e);

                // Roll back the orphan registration so the user can retry cleanly.
                // FK cascades (member_profiles, member_subscriptions) clean up children.
                $user->forceDelete();

                return response()->json([
                    'ok' => false,
                    'message' => 'Online payment is temporarily unavailable. Please try again or pay on-site at the gym.',
                    'payment_error' => 'Online payment is temporarily unavailable.',
                ], 503);
            }
        }

        return response()->json($payload, 201);
    }

    public function success(): \Illuminate\Contracts\View\View
    {
        return view('home.register-result', ['result' => 'success']);
    }

    public function cancelled(): \Illuminate\Contracts\View\View
    {
        return view('home.register-result', ['result' => 'cancelled']);
    }
}
