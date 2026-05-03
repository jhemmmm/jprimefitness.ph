<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Mail\MemberRegistrationReceivedMail;
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
            'payment_method' => ['required', Rule::in(['online', 'on_site'])],
            'recaptcha_token' => [$captchaConfigured ? 'required' : 'nullable', 'string'],
        ]);

        if ($captchaConfigured && ! $this->recaptchaService->verify((string) $data['recaptcha_token'])) {
            throw ValidationException::withMessages([
                'recaptcha' => ['Verification failed. Please try again.'],
            ]);
        }

        $plan = RatePlan::findOrFail((int) $data['rate_plan_id']);
        $startDate = $data['preferred_start_date'] ?? now()->toDateString();
        $endDate = Carbon::parse($startDate)->addDays((int) $plan->duration_days)->toDateString();

        [$user, $subscription] = DB::transaction(function () use ($data, $plan, $startDate, $endDate) {
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
            ]);

            $subscription = $user->memberSubscriptions()->create([
                'rate_plan_id' => $plan->id,
                'sold_price' => $plan->price,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => MemberSubscription::STATUS_PAUSED,
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
            ['source' => 'public_registration', 'payment_method' => $data['payment_method']],
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
            ['source' => 'public_registration', 'payment_method' => $data['payment_method']],
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
            'message' => $data['payment_method'] === 'online'
                ? 'Redirecting to secure payment…'
                : "Thanks! We'll see you at the gym to complete payment and activate your access.",
        ];

        if ($data['payment_method'] === 'online') {
            try {
                $checkout = $this->paymongoPaymentService->createCheckoutSession($subscription->fresh(['ratePlan']), $user);
                $payload['payment'] = $checkout;
            } catch (Throwable $e) {
                report($e);
                $payload['message'] = 'Registered, but online payment is temporarily unavailable. Please drop by the gym to complete payment.';
                $payload['payment_error'] = 'Online payment is temporarily unavailable.';
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
