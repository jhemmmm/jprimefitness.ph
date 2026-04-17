<?php

return [
    'enabled' => (bool) env('HIKVISION_HELPER_ENABLED', true),
    'helper_base_url' => rtrim((string) env('HIKVISION_HELPER_BASE_URL', ''), '/'),
    'helper_timeout' => (int) env('HIKVISION_HELPER_TIMEOUT', 60),
    'helper_forward_token' => (string) env('HIKVISION_HELPER_FORWARD_TOKEN', ''),
    'enrollment_timeout' => (int) env('HIKVISION_ENROLLMENT_TIMEOUT', 30),
    'fingerprint_id' => (int) env('HIKVISION_FINGERPRINT_ID', 1),
];
