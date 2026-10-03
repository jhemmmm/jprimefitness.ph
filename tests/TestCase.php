<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles + permissions are reference data: RefreshDatabase runs this once
     * with `migrate:fresh`, before per-test transactions, so every test sees
     * the real RoleSeeder::MATRIX without hand-building it.
     */
    protected $seeder = RoleSeeder::class;

    /**
     * Every cell of a streamed XLSX download, tab-joined, so tests can
     * `assertStringContainsString` on it like a CSV.
     */
    protected function xlsxText(TestResponse $response): string
    {
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        try {
            return collect(IOFactory::load($path)->getAllSheets())
                ->flatMap(fn ($sheet) => $sheet->toArray())
                ->map(fn (array $row) => implode("\t", $row))
                ->implode("\n");
        } finally {
            unlink($path);
        }
    }
}
