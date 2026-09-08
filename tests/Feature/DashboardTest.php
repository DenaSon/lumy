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

    public function test_sidebar_keeps_primary_navigation_minimal_and_groups_intelligence(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('داشبورد')
            ->assertSee('محتوا')
            ->assertSee('هوشمندی')
            ->assertSee('تحلیل')
            ->assertSee('تنظیمات')
            ->assertSee('wire:navigate.hover', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('content.index').'"', false)
            ->assertSee('href="'.route('intelligence.analysis').'"', false)
            ->assertSee('href="'.route('intelligence.index').'"', false);
    }
}
