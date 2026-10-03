<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Integrations\Tncb\PaymentGateway;
use App\Models\AuditLog;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function tncb(Request $request, PaymentGateway $gateway, PaymentService $payments)
    {
        $raw = $request->getContent();
        if (! $gateway->verifySignature($raw, $request->header('X-Signature'))) {
            AuditLog::record('webhook.bad_signature', null, ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['reference'])) {
            return response()->json(['message' => 'Bad payload'], 422);
        }
        $payment = $payments->handleWebhook($data);

        return response()->json(['ok' => (bool) $payment]);
    }
}
