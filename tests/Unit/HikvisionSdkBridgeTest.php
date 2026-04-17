<?php

namespace Tests\Unit;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Hikvision\HikvisionHelperClient;
use RuntimeException;
use Tests\TestCase;

class HikvisionSdkBridgeTest extends TestCase
{
    public function test_helper_client_requires_the_helper_to_be_enabled(): void
    {
        config()->set('hikvision.enabled', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The biometric enrollment service integration is disabled.');

        app(HikvisionHelperClient::class)->upsertEmployee(
            new User(['id' => 1, 'name' => 'Coach Kai']),
            new EmployeeProfile(['hikvision_employee_no' => '00000001'])
        );
    }

    public function test_helper_client_requires_a_base_url(): void
    {
        config()->set('hikvision.enabled', true);
        config()->set('hikvision.helper_base_url', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Biometric enrollment service base URL is not configured.');

        app(HikvisionHelperClient::class)->enrollFingerprint(
            new EmployeeProfile(['hikvision_employee_no' => '00000001']),
            1,
            30
        );
    }
}
