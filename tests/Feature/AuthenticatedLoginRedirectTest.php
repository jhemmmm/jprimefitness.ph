<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticatedLoginRedirectTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Ensure authenticated users skip the login page and land in the panel.
     *
     * @return void
     */
    public function test_authenticated_users_are_redirected_from_login_to_panel_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/panel/dashboard');
    }

    public function test_logout_redirects_to_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
