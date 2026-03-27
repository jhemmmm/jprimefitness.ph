<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BranchGovernmentContributionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::query()
            ->where('country_code', Branch::COUNTRY_PHILIPPINES)
            ->get()
            ->each(function (Branch $branch): void {
                $settings = $branch->resolvedPayrollSettings();
                $currentContributions = collect($settings['contributions'] ?? [])
                    ->keyBy(fn (array $contribution) => $this->normalizeContributionKey((string) ($contribution['name'] ?? '')));
                $seededContributions = collect(Branch::governmentContributionTemplates($branch->country_code))
                    ->keyBy(fn (array $contribution) => $this->normalizeContributionKey((string) ($contribution['name'] ?? '')));

                $mergedContributions = $seededContributions
                    ->map(fn (array $contribution, string $key) => $currentContributions->get($key, $contribution))
                    ->merge($currentContributions->except($seededContributions->keys()))
                    ->values()
                    ->all();

                $settings['contributions'] = $mergedContributions;

                $branch->update([
                    'payroll_settings' => Branch::normalizePayrollSettings($settings, $branch->country_code),
                ]);
            });
    }

    private function normalizeContributionKey(string $value): string
    {
        return (string) Str::of($value)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
