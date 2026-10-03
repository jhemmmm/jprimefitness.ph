<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Mail\ContactAcknowledgementMail;
use App\Mail\ContactMessageMail;
use App\Notifications\ContactMessageReceivedNotification;
use App\Services\NotificationRecipientResolver;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function __construct(
        private RecaptchaService $recaptchaService,
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $captchaConfigured = filled(config('services.recaptcha.site_key'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            'contact' => ['nullable', 'string', 'max:120'],
            'topic' => ['required', Rule::in(['Membership', 'Coaching / PT', 'Walk-in Visit', 'General Question'])],
            'message' => ['required', 'string', 'max:2000'],
            'recaptcha_token' => [$captchaConfigured ? 'required' : 'nullable', 'string'],
        ]);

        if ($captchaConfigured && ! $this->recaptchaService->verify((string) ($data['recaptcha_token'] ?? ''))) {
            throw ValidationException::withMessages([
                'recaptcha' => ['Verification failed. Please try again.'],
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'contact' => $data['contact'] ?? null,
            'topic' => $data['topic'],
            'message' => $data['message'],
        ];

        Mail::to(config('mail.from.address'))->queue(new ContactMessageMail($payload));
        Mail::to($payload['email'])->queue(new ContactAcknowledgementMail($payload));

        $this->recipientResolver->send(new ContactMessageReceivedNotification($payload));

        return response()->json([
            'ok' => true,
            'message' => "Thanks! We received your message and will reply as soon as we can.",
        ], 201);
    }
}
