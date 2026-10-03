<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Integrations\Tncb\FakeGateway;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/** Simulated TN CyberTech hosted checkout. Only routed when TNCB_DRIVER=fake. */
class DevCheckoutController extends Controller
{
    public function show(Request $request, string $payment)
    {
        $p = Payment::where('ulid', $payment)->firstOrFail();

        return view('dev.checkout', ['payment' => $p, 'ref' => $request->query('ref'), 'return' => $request->query('return')]);
    }

    public function pay(Request $request, string $payment, PaymentService $payments)
    {
        $p = Payment::where('ulid', $payment)->firstOrFail();
        $status = $request->input('outcome') === 'fail' ? 'failed' : 'paid';
        $payload = json_encode(['reference' => $p->gateway_reference, 'status' => $status, 'amount' => (string) $p->total, 'currency' => $p->currency]);
        // Same code path as the real webhook: verify signature then handle.
        $req = Request::create(route('webhooks.tncb'), 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => FakeGateway::sign($payload)], $payload);
        app()->handle($req);

        $return = $request->input('return');
        $host = is_string($return) ? parse_url($return, PHP_URL_HOST) : null;
        $safe = $host && $host === $request->getHost() ? $return : url('/app/receipts');

        return redirect()->to($safe);
    }
}
