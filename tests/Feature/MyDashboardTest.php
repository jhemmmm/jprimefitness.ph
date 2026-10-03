<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\MemberPtPackage;
use App\Models\PTProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MyDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);
    }

    public function test_dashboard_renders_by_role(): void
    {
        $this->actingAs($this->createUserWithRole('manager'))
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('<dashboard-page', false)
            ->assertDontSee('my-dashboard-page', false);

        $this->actingAs($this->createUserWithRole('staff'))
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('<my-dashboard-page', false)
            ->assertDontSee('/panel/sales', false)
            ->assertDontSee('/panel/pt-sessions', false)
            ->assertSee(route('panel.my.show', 'payroll'), false);

        $this->actingAs($this->createUserWithRole('coach'))
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('<my-dashboard-page', false)
            ->assertSee(route('panel.pt-sessions.index'), false);
    }

    public function test_staff_data_is_self_scoped_and_has_no_coach_block(): void
    {
        $staff = $this->createUserWithRole('staff');
        $other = $this->createUserWithRole('staff');

        $staff->payrolls()->create($this->payrollAttributes(net: 1500));
        $other->payrolls()->create($this->payrollAttributes(net: 9999));

        $this->actingAs($staff)
            ->getJson('/panel/my-dashboard/data')
            ->assertOk()
            ->assertJsonPath('is_coach', false)
            ->assertJsonCount(1, 'payrolls')
            ->assertJsonPath('payrolls.0.net_amount', 1500)
            ->assertJsonPath('stats.outstanding_payroll_balance', 1500)
            ->assertJsonMissingPath('trainees');

        $this->actingAs($staff)->getJson('/panel/dashboard/data')->assertForbidden();
    }

    public function test_coach_sees_only_own_trainees_without_contact_details(): void
    {
        $coach = $this->createUserWithRole('coach');
        $otherCoach = $this->createUserWithRole('coach');
        $member = $this->createMember('Trainee Tess');
        $product = $this->createPtProduct();

        $this->createPackage($member, $product, $coach);
        $this->createPackage($this->createMember('Someone Else'), $product, $otherCoach);

        $this->actingAs($coach)
            ->getJson('/panel/my-dashboard/data')
            ->assertOk()
            ->assertJsonPath('is_coach', true)
            ->assertJsonPath('stats.active_trainees', 1)
            ->assertJsonCount(1, 'trainees')
            ->assertJsonPath('trainees.0.member_name', 'Trainee Tess')
            ->assertJsonMissingPath('trainees.0.email')
            ->assertJsonMissingPath('trainees.0.member.email');
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->withEmployeeProfile()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    private function createMember(string $name): User
    {
        $member = User::factory()->create(['name' => $name, 'status' => User::STATUS_ACTIVE]);
        $member->assignRole('member');

        return $member;
    }

    private function createPtProduct(): PTProduct
    {
        return PTProduct::create([
            'name' => '12 Sessions',
            'session_count' => 12,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => 6000,
            'is_active' => true,
        ]);
    }

    private function createPackage(User $member, PTProduct $product, User $coach): MemberPtPackage
    {
        return $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'assigned_at' => '2026-09-01',
            'created_by' => $coach->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollAttributes(float $net): array
    {
        return [
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'gross_amount' => $net,
            'net_amount' => $net,
            'status' => 'approved',
        ];
    }
}
