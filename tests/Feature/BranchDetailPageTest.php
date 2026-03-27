<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchDetailPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('manager');
    }

    public function test_manager_can_open_branch_detail_page_for_accessible_branch(): void
    {
        $branch = Branch::create([
            'name' => 'Naga',
            'status' => Branch::STATUS_OPEN,
            'country_code' => Branch::COUNTRY_PHILIPPINES,
            'city' => 'Naga City',
        ]);
        $manager = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $manager->assignRole('manager');
        $manager->branches()->sync([$branch->id]);

        $this->actingAs($manager)
            ->get(route('panel.branches.show', $branch))
            ->assertOk()
            ->assertSee('branch-detail-page', false)
            ->assertSee('Naga');
    }
}
