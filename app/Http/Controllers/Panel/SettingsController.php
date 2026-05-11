<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Redirect to the settings page.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(): RedirectResponse
    {
        return to_route('panel.business.settings');
    }

    /**
     * Display the settings page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function settingsPage(): View
    {
        return view('panel.business.settings', [
            'businessProfile' => BusinessProfile::current(),
        ]);
    }

    /**
     * Update the business settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'pay_overwork_hours' => ['sometimes', 'boolean'],
            'payroll_withholding_tax_enabled' => ['sometimes', 'boolean'],
            'payroll_government_contributions_enabled' => ['sometimes', 'boolean'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],
            'operating_hours' => ['required', 'array', 'size:7'],
            'operating_hours.*.day' => ['required', 'string', Rule::in(BusinessProfile::operatingDayNames())],
            'operating_hours.*.opening_time' => ['required', 'date_format:H:i'],
            'operating_hours.*.closing_time' => ['required', 'date_format:H:i'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $operatingHours = $request->input('operating_hours', []);

            if (! is_array($operatingHours)) {
                return;
            }

            $dayNames = collect($operatingHours)->pluck('day')->filter()->values()->all();

            if (count(array_unique($dayNames)) !== count(BusinessProfile::operatingDayNames())
                || collect(BusinessProfile::operatingDayNames())->diff($dayNames)->isNotEmpty()) {
                $validator->errors()->add('operating_hours', 'Set operating hours for every day of the week.');
            }

            foreach ($operatingHours as $index => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                if (empty($entry['opening_time']) || empty($entry['closing_time'])) {
                    continue;
                }

                if (! is_string($entry['opening_time']) || ! is_string($entry['closing_time'])
                    || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $entry['opening_time'])
                    || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $entry['closing_time'])) {
                    continue;
                }

                if (Carbon::createFromFormat('H:i', $entry['closing_time'])->lessThanOrEqualTo(Carbon::createFromFormat('H:i', $entry['opening_time']))) {
                    $validator->errors()->add("operating_hours.{$index}.closing_time", 'The closing time must be after the opening time.');
                }
            }
        });

        $data = $validator->validate();
        $data['country_code'] = strtoupper($data['country_code']);
        $data['operating_hours'] = $this->normalizeOperatingHours($data['operating_hours']);
        $data['opening_time'] = $data['operating_hours'][0]['opening_time'];
        $data['closing_time'] = $data['operating_hours'][0]['closing_time'];

        $profile = BusinessProfile::current();
        $profile->update($data);
        $profile = $profile->fresh();

        return response()->json($profile);
    }

    /**
     * Normalize the weekly schedule into canonical day order.
     *
     * @param  array<int, array{day:string, opening_time:string, closing_time:string}>  $operatingHours
     * @return array<int, array{key:string, day:string, opening_time:string, closing_time:string}>
     */
    private function normalizeOperatingHours(array $operatingHours): array
    {
        $entriesByDay = collect($operatingHours)->keyBy('day');

        return collect(BusinessProfile::OPERATING_DAYS)
            ->map(function (array $day) use ($entriesByDay): array {
                $entry = $entriesByDay->get($day['day']);

                return [
                    ...$day,
                    'opening_time' => (string) $entry['opening_time'],
                    'closing_time' => (string) $entry['closing_time'],
                ];
            })
            ->all();
    }
}
