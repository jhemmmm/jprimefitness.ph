<?php

namespace App\Http\Controllers;

use App\Services\Hikvision\HikvisionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HikvisionCallbackController extends Controller
{
    public function store(Request $request, HikvisionAttendanceService $hikvisionAttendanceService): JsonResponse
    {
        Log::info('Received Hikvision attendance callback', $this->requestLogContext($request));

        $payload = $this->payload($request);

        if (! $this->hasValidToken($request)) {
            Log::warning('Rejected Hikvision helper callback because of invalid token', $this->requestLogContext($request));

            abort(403, 'Invalid Hikvision helper forward token.');
        }

        $result = $hikvisionAttendanceService->ingest($payload);
        $statusCode = $result['status'] === 'duplicate' ? 200 : 202;

        Log::info('Hikvision attendance callback processed', [
            'status' => $result['status'],
            'action' => $result['action'],
            'employee_no' => $result['employee_no'],
            'event_serial_no' => $result['event_serial_no'],
            'event_type' => $result['event_type'],
            'occurred_at' => $result['occurred_at'],
            'attendance_id' => $result['attendance']?->id,
            'source_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => $result['status'],
            'action' => $result['action'],
            'employee_no' => $result['employee_no'],
            'event_serial_no' => $result['event_serial_no'],
            'event_type' => $result['event_type'],
            'occurred_at' => $result['occurred_at'],
            'attendance_id' => $result['attendance']?->id,
            'debug' => sprintf(
                'Hikvision %s for employee %s at %s',
                $result['action'],
                $result['employee_no'] !== '' ? $result['employee_no'] : 'unknown',
                $result['occurred_at']
            ),
        ], $statusCode);
    }

    private function hasValidToken(Request $request): bool
    {
        $expected = (string) config('hikvision.helper_forward_token', '');

        if ($expected === '') {
            return false;
        }

        return hash_equals(
            $expected,
            (string) ($request->query('token') ?: $request->header('X-Hikvision-Token', ''))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestLogContext(Request $request): array
    {
        return [
            'source_ip' => $request->ip(),
            'query_params' => $this->sanitizeLogContext($request->query()),
            'headers' => $this->sanitizeLogContext($request->headers->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeLogContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($this->shouldRedactKey((string) $key)) {
                $context[$key] = is_array($value)
                    ? array_fill(0, count($value), '[redacted]')
                    : '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->sanitizeLogContext($value);
            }
        }

        return $context;
    }

    private function shouldRedactKey(string $key): bool
    {
        return in_array(Str::lower($key), [
            'token',
            'x-hikvision-token',
            'authorization',
            'cookie',
            'set-cookie',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        if ($request->isJson()) {
            return $request->json()->all();
        }

        $content = trim((string) $request->getContent());

        if ($content === '') {
            return $request->all();
        }

        if (Str::startsWith($content, '<')) {
            $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

            return $xml ? json_decode(json_encode($xml), true) ?? [] : [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : $request->all();
    }
}
