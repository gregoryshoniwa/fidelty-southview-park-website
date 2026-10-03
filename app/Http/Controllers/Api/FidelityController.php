<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integrations\Fidelity\FidelityClient;
use App\Models\AuditLog;
use App\Models\Service;
use App\Services\PaymentService;
use App\Services\RequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FidelityController extends Controller
{
    public function __construct(private FidelityClient $fidelity) {}

    public function payments(Request $request)
    {
        $r = $request->user()->resident;
        $rows = Cache::remember('fidelity:payments:'.$r->id, 3600, fn () => $this->fidelity->paymentHistory($r->fidelity_reference));
        AuditLog::record('fidelity.payments_viewed', $r);

        return response()->json(['data' => $rows, 'stand' => $r->stand?->stand_number]);
    }

    public function agreement(Request $request)
    {
        $r = $request->user()->resident;
        $pdf = $this->fidelity->agreementPdf($r->fidelity_reference);
        AuditLog::record('fidelity.agreement_downloaded', $r);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="agreement-of-sale-stand-'.$r->stand?->stand_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function replace(Request $request, RequestService $requests, PaymentService $payments)
    {
        $data = $request->validate(['reason' => ['required', 'in:lost,damaged,stolen,other'], 'details' => ['nullable', 'string', 'max:500']]);
        $r = $request->user()->resident;
        $service = Service::where('slug', 'my-agreement')->firstOrFail();
        $req = $requests->open($r, $service, ['reason' => $data['reason'], 'details' => strip_tags((string) ($data['details'] ?? ''))]);
        $this->fidelity->requestReplacement($r->fidelity_reference, $req->reference);

        $fee = (float) $service->fee_amount;
        $checkout = null;
        if ($service->fee_type === 'flat' && $fee > 0 && config('fspra.payments_live')) {
            $req->update(['status' => 'waiting_payment']);
            $payment = $payments->create($r, 'agreement', $req->reference, $fee, $service->fee_currency, $req, 'service');
            $checkout = $payments->checkout($payment, url('/app/requests/'.$req->reference));
        }

        return response()->json(['reference' => $req->reference, 'checkout_url' => $checkout], 201);
    }
}
