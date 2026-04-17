<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EmployeeBiometricSession;
use App\Models\User;
use App\Services\Hikvision\HikvisionBiometricService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeBiometricController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage employees');
    }

    public function store(Request $request, User $employee, HikvisionBiometricService $hikvisionBiometricService): JsonResponse
    {
        $session = $hikvisionBiometricService->startEnrollment($employee, $request->user());

        return response()->json([
            'session' => $hikvisionBiometricService->serializeSession($session),
            'employee_profile' => $hikvisionBiometricService->serializeProfile($session->employeeProfile),
        ], 202);
    }

    public function show(
        User $employee,
        EmployeeBiometricSession $session,
        HikvisionBiometricService $hikvisionBiometricService,
    ): JsonResponse {
        $profile = $hikvisionBiometricService->ensureProfile($employee);

        abort_if((int) $session->employee_profile_id !== (int) $profile->id, 404);

        $session = $hikvisionBiometricService->refreshSession($session);

        return response()->json([
            'session' => $hikvisionBiometricService->serializeSession($session),
            'employee_profile' => $hikvisionBiometricService->serializeProfile($session->employeeProfile),
        ]);
    }

    public function destroy(Request $request, User $employee, HikvisionBiometricService $hikvisionBiometricService): JsonResponse
    {
        $profile = $hikvisionBiometricService->removeFingerprint($employee, $request->user());

        return response()->json([
            'employee_profile' => $hikvisionBiometricService->serializeProfile($profile),
        ]);
    }
}
