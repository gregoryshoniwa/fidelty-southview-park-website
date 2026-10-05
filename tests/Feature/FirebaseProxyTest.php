<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseProxyTest extends TestCase
{
    public function test_auth_handler_is_relayed_from_firebase_without_frame_blocking(): void
    {
        config(['fspra.firebase.project_id' => 'fidelity-southview-park']);
        Http::fake(['https://fidelity-southview-park.firebaseapp.com/__/auth/handler*' => Http::response('<html>handler</html>', 200, ['Content-Type' => 'text/html'])]);
        $res = $this->get('/__/auth/handler?apiKey=x&authType=signInViaPopup')->assertOk()->assertSee('handler', false);
        $this->assertNull($res->headers->get('X-Frame-Options'));
        $this->assertNull($res->headers->get('Content-Security-Policy'));
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://fidelity-southview-park.firebaseapp.com/__/auth/handler?apiKey=x'));
    }

    public function test_only_firebase_paths_are_relayed(): void
    {
        config(['fspra.firebase.project_id' => 'fidelity-southview-park']);
        Http::fake();
        $this->get('/__/other/thing')->assertNotFound();
        $this->get('/__/auth/../../etc/passwd')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_init_json_is_served_from_our_config(): void
    {
        config(['fspra.firebase' => ['project_id' => 'fidelity-southview-park', 'api_key' => 'k', 'auth_domain' => 'fidelity-southview.co.zw', 'app_id' => 'a', 'providers' => 'google']]);
        Http::fake();
        $this->get('/__/firebase/init.json')->assertOk()
            ->assertExactJson(['apiKey' => 'k', 'authDomain' => 'fidelity-southview.co.zw', 'projectId' => 'fidelity-southview-park', 'appId' => 'a']);
        Http::assertNothingSent();
    }
}
