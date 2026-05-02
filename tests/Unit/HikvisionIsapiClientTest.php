<?php

namespace Tests\Unit;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Hikvision\HikvisionHelperClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HikvisionIsapiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.biometric.enabled', true);
        config()->set('services.biometric.helper_base_url', 'http://helper.test');
        config()->set('services.biometric.helper_timeout', 60);
    }

    public function test_upsert_employee_posts_expected_payload_to_the_helper(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('http://helper.test/persons/upsert', $request->url());
            $this->assertStringContainsString('"employeeNo":"00000001"', $request->body());
            $this->assertStringContainsString('"name":"Coach Kai"', $request->body());

            return Http::response([
                'status' => 'success',
                'message' => 'Employee synced.',
            ], 200);
        });

        $employee = new User(['id' => 1, 'name' => 'Coach Kai']);
        $profile = new EmployeeProfile(['hikvision_employee_no' => '00000001']);

        $payload = app(HikvisionHelperClient::class)->upsertEmployee($employee, $profile);

        $this->assertSame('success', $payload['status']);
        Http::assertSentCount(1);
    }

    public function test_enroll_fingerprint_throws_the_helper_message_when_capture_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://helper.test/fingerprint/enroll' => Http::response([
                'status' => 'failed',
                'message' => 'Fingerprint enrollment timed out.',
            ], 200),
        ]);

        $profile = new EmployeeProfile(['hikvision_employee_no' => '00000001']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fingerprint enrollment timed out.');

        app(HikvisionHelperClient::class)->enrollFingerprint($profile, 1, 30);
    }

    public function test_delete_fingerprint_throws_a_reachable_service_message_when_service_is_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $profile = new EmployeeProfile(['hikvision_employee_no' => '00000001']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The biometric enrollment service is unreachable. Please make sure the enrollment service is running.');

        app(HikvisionHelperClient::class)->deleteFingerprint($profile, 1);
    }
}
