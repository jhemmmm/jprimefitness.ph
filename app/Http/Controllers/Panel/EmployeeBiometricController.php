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
    /**
     * Create a new employee biometric controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('can:manage employees');
    }

    /**
     * Start a new biometric enrollment session for an employee.
     *
     * @param  Request  $request
     * @param  User  $employee
     * @param  HikvisionBiometricService  $hikvisionBiometricService
     * @return JsonResponse
     */
    public function store(Request $request, User $employee, HikvisionBiometricService $hikvisionBiometricService): JsonResponse
    {
        $session = $hikvisionBiometricService->startEnrollment($employee, $request->user());

        return response()->json([
            'session' => $hikvisionBiometricService->serializeSession($session),
            'employee_profile' => $hikvisionBiometricService->serializeProfile($session->employeeProfile),
        ], 202);
    }

    /**
     * Display an employee biometric enrollment session.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Remove an employee biometric fingerprint.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, User $employee, HikvisionBiometricService $hikvisionBiometricService): JsonResponse
    {
        $profile = $hikvisionBiometricService->removeFingerprint($employee, $request->user());

        return response()->json([
            'employee_profile' => $hikvisionBiometricService->serializeProfile($profile),
        ]);
    }
}
