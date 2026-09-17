<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_link_signs_into_the_seeded_event(): void
    {
        config(['gatezo.demo.enabled' => true]);
        $this->seed(DemoSeeder::class);

        $this->get('/demo')->assertRedirect('/admin/sharad-utsav');
        $this->assertAuthenticatedAs(User::where('email', 'demo@gatezo.local')->first());
        $this->get('/admin/sharad-utsav')->assertOk()->assertSee('shared demo event');
    }

    public function test_demo_link_is_404_when_disabled_or_unseeded(): void
    {
        config(['gatezo.demo.enabled' => false]);
        $this->get('/demo')->assertNotFound();

        config(['gatezo.demo.enabled' => true]);
        $this->get('/demo')->assertNotFound();
        $this->assertGuest();
    }

    public function test_demo_reset_rebuilds_the_event(): void
    {
        config(['gatezo.demo.enabled' => true]);
        $this->seed(DemoSeeder::class);
        Event::where('slug', 'sharad-utsav')->first()->update(['name' => 'Vandalised']);

        $this->artisan('gatezo:demo-reset')->assertSuccessful();

        $this->assertSame('Sharad Utsav Garba 2026', Event::where('slug', 'sharad-utsav')->value('name'));
    }

    public function test_landing_and_event_pages_carry_link_preview_tags(): void
    {
        $this->get('/')->assertOk()->assertSee('property="og:image" content="'.url('/og.png').'"', false);

        $this->seed(DemoSeeder::class);
        $this->get('/e/sharad-utsav')->assertOk()->assertSee('og:title" content="Sharad Utsav Garba 2026', false);
    }
}
