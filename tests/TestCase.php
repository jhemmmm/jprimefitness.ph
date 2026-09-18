<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles + permissions are reference data: RefreshDatabase runs this once
     * with `migrate:fresh`, before per-test transactions, so every test sees
     * the real RoleSeeder::MATRIX without hand-building it.
     */
    protected $seeder = RoleSeeder::class;
}
