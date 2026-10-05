<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Mail\ResidentNotificationMail;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailLoginTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_new_resident_signs_up_by_email_link(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/email', ['email' => 'Chipo@Example.com'])->assertOk()->assertJson(['sent' => true]);
        Mail::assertSent(MagicLinkMail::class, fn ($m) => $m->hasTo('chipo@example.com') && $m->newAccount);
        $token = cache('magic:last:chipo@example.com');

        $this->postJson('/api/auth/email/verify', ['token' => $token])->assertStatus(422)->assertJson(['needs_details' => true]);
        $this->postJson('/api/auth/email/verify', ['token' => $token, 'name' => 'Chipo Dube', 'accept_terms' => true])->assertOk()->assertJsonPath('user.name', 'Chipo Dube');
        $u = User::where('email', 'chipo@example.com')->first();
        $this->assertNotNull($u->email_verified_at);
        $this->assertAuthenticatedAs($u);
        // single use
        $this->postJson('/api/auth/logout');
        $this->flushSession();
        $this->postJson('/api/auth/email/verify', ['token' => $token])->assertStatus(422)->assertJsonValidationErrors('token');
    }

    public function test_expired_or_forged_links_fail(): void
    {
        $this->postJson('/api/auth/email/verify', ['token' => str_repeat('a', 64)])->assertStatus(422);
        Mail::fake();
        $this->postJson('/api/auth/email', ['email' => 'late@example.com']);
        $token = cache('magic:last:late@example.com');
        $this->travel(16)->minutes();
        $this->postJson('/api/auth/email/verify', ['token' => $token, 'name' => 'Late', 'accept_terms' => true])->assertStatus(422);
    }

    public function test_staff_emails_get_no_link_and_response_is_identical(): void
    {
        Mail::fake();
        $a = $this->postJson('/api/auth/email', ['email' => 'admin@example.test'])->assertOk()->json();
        $b = $this->postJson('/api/auth/email', ['email' => 'nobody@example.com'])->assertOk()->json();
        $this->assertSame($a, $b);
        Mail::assertNotSent(MagicLinkMail::class, fn ($m) => $m->hasTo('admin@example.test'));
    }

    public function test_email_link_requests_are_rate_limited(): void
    {
        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/email', ['email' => 'spam@example.com'])->assertOk();
        }
        $this->postJson('/api/auth/email', ['email' => 'spam@example.com'])->assertStatus(429);
    }

    public function test_notifications_are_emailed_to_verified_addresses_when_enabled(): void
    {
        Mail::fake();
        $u = $this->resident('1170');
        $u->forceFill(['email' => 'res@example.com', 'email_verified_at' => now()])->save();
        app(NotificationService::class)->notify($u, 'Your deed moved to step 2', 'Balance confirmed', '/app/deed', 'request', false);
        Mail::assertQueued(ResidentNotificationMail::class, fn ($m) => $m->hasTo('res@example.com'));

        Mail::fake();
        $u->update(['notification_prefs' => ['sms' => true, 'email' => false]]);
        app(NotificationService::class)->notify($u->fresh(), 'Quiet', null, null, 'info', false);
        Mail::assertNothingQueued();
    }
}
