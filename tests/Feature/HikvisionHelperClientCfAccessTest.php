<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Services\Hikvision\HikvisionHelperClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HikvisionHelperClientCfAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.biometric.enabled', true);
        config()->set('services.biometric.helper_base_url', 'http://helper.test');
        config()->set('services.biometric.helper_timeout', 60);
    }

    public function test_delete_fingerprint_sends_cloudflare_access_headers_when_service_token_is_configured(): void
    {
        config()->set('services.biometric.cf_access_client_id', 'abc123.access');
        config()->set('services.biometric.cf_access_client_secret', 'super-secret');

        $this->fakeSuccessfulFingerprintDelete();

        $payload = app(HikvisionHelperClient::class)->deleteFingerprint($this->employeeProfile(), 1);

        $this->assertSame('success', $payload['status']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://helper.test/fingerprint/delete'
                && $request->hasHeader('CF-Access-Client-Id', 'abc123.access')
                && $request->hasHeader('CF-Access-Client-Secret', 'super-secret');
        });
    }

    public function test_delete_fingerprint_omits_cloudflare_access_headers_when_service_token_is_not_configured(): void
    {
        config()->set('services.biometric.cf_access_client_id', null);
        config()->set('services.biometric.cf_access_client_secret', null);

        $this->fakeSuccessfulFingerprintDelete();

        $payload = app(HikvisionHelperClient::class)->deleteFingerprint($this->employeeProfile(), 1);

        $this->assertSame('success', $payload['status']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://helper.test/fingerprint/delete'
                && ! $request->hasHeader('CF-Access-Client-Id')
                && ! $request->hasHeader('CF-Access-Client-Secret');
        });
    }

    public function test_delete_fingerprint_omits_cloudflare_access_headers_when_only_the_client_id_is_configured(): void
    {
        config()->set('services.biometric.cf_access_client_id', 'abc123.access');
        config()->set('services.biometric.cf_access_client_secret', '');

        $this->fakeSuccessfulFingerprintDelete();

        app(HikvisionHelperClient::class)->deleteFingerprint($this->employeeProfile(), 1);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return ! $request->hasHeader('CF-Access-Client-Id')
                && ! $request->hasHeader('CF-Access-Client-Secret');
        });
    }

    /**
     * Fake the helper's fingerprint-delete endpoint with its success payload.
     */
    private function fakeSuccessfulFingerprintDelete(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://helper.test/fingerprint/delete' => Http::response(['status' => 'success'], 200),
        ]);
    }

    /**
     * An unsaved profile is enough: the client only reads hikvision_employee_no.
     */
    private function employeeProfile(): EmployeeProfile
    {
        return new EmployeeProfile(['hikvision_employee_no' => '00000001']);
    }
}
