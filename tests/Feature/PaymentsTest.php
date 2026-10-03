<?php

namespace Tests\Feature;

use App\Integrations\Tncb\FakeGateway;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_payments_are_coming_soon_by_default(): void
    {
        $u = $this->resident('1130');
        $this->getJson('/api/billers')->assertOk()->assertJson(['live' => false]);
        $this->actingAs($u)->postJson('/api/payments', ['biller_code' => 'zesa', 'biller_reference' => '12345678901', 'amount' => 10])->assertStatus(503)->assertJson(['code' => 'payments_coming_soon']);
        $this->actingAs($u)->postJson('/api/payments/notify-me')->assertOk();
    }

    private function livePayment(): array
    {
        config(['fspra.payments_live' => true]);
        $u = $this->resident('1131');
        $this->actingAs($u)->postJson('/api/payments/quote', ['biller_code' => 'council', 'amount' => 20])->assertOk()->assertJsonPath('data.platform_fee', 0.5)->assertJsonPath('data.total', 20.5);
        $res = $this->actingAs($u)->postJson('/api/payments', ['biller_code' => 'council', 'biller_reference' => 'ACC-1001', 'amount' => 20])->assertCreated();

        return [$u, Payment::where('ulid', $res->json('data.id'))->first()];
    }

    private function webhook(array $data, ?string $sig = null)
    {
        $raw = json_encode($data);

        return $this->call('POST', '/webhooks/tncb', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig ?? FakeGateway::sign($raw), 'HTTP_ACCEPT' => 'application/json'], $raw);
    }

    public function test_webhook_requires_valid_signature(): void
    {
        [, $p] = $this->livePayment();
        $this->webhook(['reference' => $p->gateway_reference, 'status' => 'paid', 'amount' => '20.50', 'currency' => 'USD'], 'forged')->assertUnauthorized();
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_paid_webhook_posts_ledger_and_is_idempotent(): void
    {
        Storage::fake('local');
        [$u, $p] = $this->livePayment();
        $payload = ['reference' => $p->gateway_reference, 'status' => 'paid', 'amount' => '20.50', 'currency' => 'USD'];
        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk();
        $this->assertSame('paid', $p->fresh()->status);
        $this->assertSame(1, LedgerEntry::where('payment_id', $p->id)->where('type', 'fee')->count());
        $this->assertTrue(app(LedgerService::class)->verifyChain()['ok']);
        $this->actingAs($u)->get('/api/payments/'.$p->ulid.'/receipt')->assertOk();
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        [, $p] = $this->livePayment();
        $this->webhook(['reference' => $p->gateway_reference, 'status' => 'paid', 'amount' => '1.00', 'currency' => 'USD'])->assertOk();
        $this->assertSame('failed', $p->fresh()->status);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_ledger_is_append_only_with_hash_chain(): void
    {
        $l = app(LedgerService::class);
        $e = $l->post(['type' => 'expense', 'description' => 'Hosting', 'amount' => -12, 'source' => 'treasurer']);
        $l->post(['type' => 'commission', 'description' => 'ZESA', 'amount' => 3, 'source' => 'gateway_webhook']);
        $this->assertTrue($l->verifyChain()['ok']);
        $this->expectException(\LogicException::class);
        $e->update(['amount' => -1]);
    }

    public function test_ledger_tampering_is_detected(): void
    {
        $l = app(LedgerService::class);
        $e = $l->post(['type' => 'expense', 'description' => 'Hosting', 'amount' => -12, 'source' => 'treasurer']);
        \DB::table('ledger_entries')->where('id', $e->id)->update(['amount' => -1200]);
        $this->assertFalse($l->verifyChain()['ok']);
    }
}
