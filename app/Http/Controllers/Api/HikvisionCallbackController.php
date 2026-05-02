<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hikvision\HikvisionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HikvisionCallbackController extends Controller
{
    /**
     * Handle the Hikvision attendance callback.
     *
     * @return JsonResponse
     */
    public function store(Request $request, HikvisionAttendanceService $hikvisionAttendanceService): JsonResponse
    {
        $result = $hikvisionAttendanceService->ingest($this->payload($request));
        $statusCode = $result['status'] === 'duplicate' ? 200 : 202;

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

    /**
     * Determine whether a log context key should be redacted.
     *
     * @return bool
     */
    private function shouldRedactKey(string $key): bool
    {
        return in_array(Str::lower($key), [
            'token',
            'x-biometric-token',
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
