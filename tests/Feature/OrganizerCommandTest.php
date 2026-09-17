<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Notifications\SetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_organizer_with_first_event_and_sends_set_password_link(): void
    {
        Notification::fake();

        $this->artisan('gatezo:organizer', ['name' => 'Bhavesh Patel', 'email' => 'Bhavesh@Example.com', '--event' => 'Sharad Utsav 2026', '--type' => 'festival', '--phone' => '9800000000'])
            ->expectsOutputToContain('Created Bhavesh Patel <bhavesh@example.com>')
            ->expectsOutputToContain('/admin/password-reset/reset?email=bhavesh%40example.com')
            ->assertSuccessful();

        $user = User::where('email', 'bhavesh@example.com')->firstOrFail();
        $event = Event::where('name', 'Sharad Utsav 2026')->firstOrFail();
        $this->assertSame('festival', $event->type);
        $this->assertTrue($user->isOrganizerOf($event));
        $this->assertSame($user->id, $event->created_by);

        Notification::assertSentTo($user, SetPassword::class, function (SetPassword $n) use ($user) {
            $mail = $n->toMail($user);

            return str_contains($n->url, '/admin/password-reset/reset') && $mail->subject === 'Your Gatezo login';
        });

        // The link is a real Filament reset link: it opens the form.
        $url = Notification::sent($user, SetPassword::class)->first()->url;
        $this->get($url)->assertOk()->assertSee('Confirm password');
    }

    public function test_rerun_for_existing_email_reissues_link_without_duplicating_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'hetal@example.com']);

        $this->artisan('gatezo:organizer', ['name' => 'ignored', 'email' => 'hetal@example.com', '--no-mail' => true])
            ->expectsOutputToContain('Existing account')
            ->assertSuccessful();

        $this->assertSame(1, User::where('email', 'hetal@example.com')->count());
        Notification::assertNothingSent();
    }

    public function test_rejects_bad_email_or_type(): void
    {
        $this->artisan('gatezo:organizer', ['name' => 'X', 'email' => 'not-an-email'])->assertFailed();
        $this->artisan('gatezo:organizer', ['name' => 'X', 'email' => 'x@example.com', '--type' => 'circus'])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
