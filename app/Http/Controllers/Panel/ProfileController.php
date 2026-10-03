<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SystemActivity;
use App\Models\User;
use App\Services\SystemActivityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in user's own account: name, phone, address, password, and (for
 * employees) their personal details. Email stays manager-managed (it is the
 * login identity) and so does the hire date.
 */
class ProfileController extends Controller
{
    public function __construct(private SystemActivityService $systemActivityService) {}

    /**
     * Display the profile page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(): View
    {
        return view('panel.profile', ['user' => $this->serialize(auth()->user())]);
    }

    /**
     * Update the current user's profile.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // phone/address/employee_profile.* share the employee form's "required going forward" rules
            ...Arr::except(EmployeeController::personRules($user), ['employee_profile.hired_at']),
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
        ]);

        // only what the request sent: a partial PUT must not null out stored details
        $user->fill(Arr::only($data, ['name', 'phone', 'address']));

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if ($user->employeeProfile) {
            // every detail column but the hire date, which stays with the manager
            $user->employeeProfile->fill(Arr::except($data['employee_profile'] ?? [], ['hired_at']))->save();

            // same subject event a manager's edit under Employees leaves, so Audit History shows self-service changes too
            $this->systemActivityService->recordSubjectEvent(
                SystemActivity::SUBJECT_EMPLOYEE,
                $user->id,
                'updated',
                ['id' => $user->id, 'name' => $user->name, ...$user->employeeProfile->details()],
                [],
                $user->id,
                $user->name,
                now(),
            );
        }

        return response()->json($this->serialize($user->fresh('employeeProfile')));
    }

    /** @return array<string, mixed> */
    private function serialize(User $user): array
    {
        return [
            ...$user->only(['name', 'email', 'phone', 'address']),
            'employee_profile' => $user->employeeProfile?->details(),
        ];
    }
}
