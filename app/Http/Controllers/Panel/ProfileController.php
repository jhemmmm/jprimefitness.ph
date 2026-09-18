<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in user's own account: name, phone, password.
 * Email stays manager-managed (it is the login identity); employee
 * details are shown read-only and edited by managers under Employees.
 */
class ProfileController extends Controller
{
    /**
     * Display the profile page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(): View
    {
        $user = auth()->user();

        return view('panel.profile', [
            'user' => [
                ...$user->only(['name', 'email', 'phone', 'address']),
                'employee_profile' => $user->employeeProfile?->details(),
            ],
        ]);
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
            // required going forward; a legacy account with no phone yet may still change its password
            'phone' => [$user->phone === null ? 'nullable' : 'required', 'string', 'max:20'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return response()->json($user->only(['name', 'email', 'phone']));
    }
}
