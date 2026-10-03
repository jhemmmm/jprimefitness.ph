<?php

namespace App\Services\Hikvision;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use RuntimeException;

class HikvisionHelperClient
{
    public function __construct(
        private HttpFactory $http,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function upsertEmployee(User $employee, EmployeeProfile $employeeProfile): array
    {
        $response = $this->request('post', '/persons/upsert', [
            'employeeNo' => $employeeProfile->hikvision_employee_no,
            'name' => $employee->name,
            'userType' => 'normal',
            'validityEnabled' => false,
            'checkUser' => true,
            'closeDelayEnabled' => false,
            'numberOfFingerprints' => $employeeProfile->biometric_fingerprint_id !== null ? 1 : 1,
        ]);

        return $this->ensureSuccess($response, 'Unable to sync the employee to the biometric enrollment service.');
    }

    /**
     * @return array<string, mixed>
     */
    public function enrollFingerprint(EmployeeProfile $employeeProfile, int $fingerprintId, int $timeoutSeconds): array
    {
        $response = $this->request('post', '/fingerprint/enroll', [
            'employeeNo' => $employeeProfile->hikvision_employee_no,
            'fingerprintId' => $fingerprintId,
            'timeoutSeconds' => $timeoutSeconds,
            'saveToDevice' => true,
            'includeDebug' => true,
            'includeFingerprintData' => false,
        ]);

        return $this->ensureSuccess($response, 'Unable to enroll the employee fingerprint through the biometric enrollment service.');
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteFingerprint(EmployeeProfile $employeeProfile, int $fingerprintId): array
    {
        $response = $this->request('post', '/fingerprint/delete', [
            'employeeNo' => $employeeProfile->hikvision_employee_no,
            'fingerprintId' => $fingerprintId,
        ]);

        return $this->ensureSuccess($response, 'Unable to remove the employee fingerprint through the biometric enrollment service.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload): Response
    {
        if (! config('services.biometric.enabled')) {
            throw new RuntimeException('The biometric enrollment service integration is disabled.');
        }

        $baseUrl = trim((string) config('services.biometric.helper_base_url', ''));

        if ($baseUrl === '') {
            throw new RuntimeException('Biometric enrollment service base URL is not configured.');
        }

        $timeout = max(1, (int) config('services.biometric.helper_timeout', 60));

        try {
            return $this->http
                ->acceptJson()
                ->asJson()
                ->baseUrl($baseUrl)
                ->timeout($timeout)
                ->connectTimeout($timeout)
                ->send(strtoupper($method), ltrim($path, '/'), [
                    'json' => $payload,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The biometric enrollment service is unreachable. Please make sure the enrollment service is running.', previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureSuccess(Response $response, string $fallbackMessage): array
    {
        try {
            $response->throw();
        } catch (RequestException $exception) {
            $message = $response->json('message')
                ?? $response->json('error')
                ?? $fallbackMessage;

            throw new RuntimeException((string) $message, previous: $exception);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $status = strtolower((string) ($payload['status'] ?? ''));

        if (! in_array($status, ['success', 'succeeded', 'processed'], true)) {
            $message = (string) ($payload['message'] ?? $fallbackMessage);

            throw new RuntimeException($message !== '' ? $message : $fallbackMessage);
        }

        return $payload;
    }
}
