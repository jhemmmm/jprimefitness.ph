<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frozen copy of EmployeeProfile::LEGAL_MINIMUM_CONTRIBUTIONS at migration time;
     * the column defaults back-fill existing rows.
     *
     * @var array<string, int>
     */
    private const SHARES = [
        'sss_employee_share' => 250,
        'sss_employer_share' => 500,
        'philhealth_employee_share' => 250,
        'philhealth_employer_share' => 250,
        'pagibig_employee_share' => 100,
        'pagibig_employer_share' => 100,
    ];

    private const BASES = [
        'sss_monthly_compensation',
        'philhealth_monthly_basic_salary',
        'pagibig_monthly_compensation',
    ];

    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            foreach (self::SHARES as $column => $default) {
                $table->decimal($column, 10, 2)->default($default)->after('pagibig_covered');
            }
        });

        // Employees whose contributions were never configured start enrolled at the
        // legal minimum; explicit opt-outs (a base was set, coverage off) are kept.
        DB::table('employee_profiles')
            ->where(fn ($query) => collect(self::BASES)->each(fn (string $column) => $query->whereNull($column)))
            ->update([
                'sss_covered' => true,
                'philhealth_covered' => true,
                'pagibig_covered' => true,
            ]);

        // Separate call: SQLite rebuilds the table on drop.
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropColumn(self::BASES);
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            foreach (self::BASES as $column) {
                $table->decimal($column, 10, 2)->nullable();
            }
        });

        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::SHARES));
        });
    }
};
