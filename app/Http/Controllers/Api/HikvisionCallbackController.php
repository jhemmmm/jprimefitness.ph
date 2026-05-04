<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hikvision\HikvisionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
