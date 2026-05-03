<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function verify(string $token, string $expectedAction = 'register', ?float $minScore = null): bool
    {
        $secret = (string) config('services.recaptcha.secret');
        $minScore = $minScore ?? (float) config('services.recaptcha.min_score', 0.5);

        if ($secret === '') {
            return app()->environment('local', 'testing');
        }

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
            ]);
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification request failed', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $body = $response->json();

        if (! ($body['success'] ?? false)) {
            return false;
        }

        if (($body['action'] ?? null) !== $expectedAction) {
            return false;
        }

        $score = (float) ($body['score'] ?? 0);

        return $score >= $minScore;
    }
}
