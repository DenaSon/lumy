<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_panel(): void
    {
        $this->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_can_be_rendered(): void
    {
        $this->get(route('dashboard'))
            ->assertOk();
    }
}
