<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'kiosk' => [
        'token' => env('KIOSK_TOKEN'),
        'walk_in_amount' => env('KIOSK_WALK_IN_AMOUNT', 150),
        'payment_timeout_seconds' => env('KIOSK_PAYMENT_TIMEOUT_SECONDS', 60),
    ],

    'biometric' => [
        'token' => env('BIOMETRIC_TOKEN'),
        'enabled' => (bool) env('BIOMETRIC_HELPER_ENABLED', true),
        'helper_base_url' => rtrim((string) env('BIOMETRIC_HELPER_BASE_URL', ''), '/'),
        'helper_timeout' => (int) env('BIOMETRIC_HELPER_TIMEOUT', 60),
        'enrollment_timeout' => (int) env('BIOMETRIC_ENROLLMENT_TIMEOUT', 30),
        'fingerprint_id' => (int) env('BIOMETRIC_FINGERPRINT_ID', 1),
    ],

];
