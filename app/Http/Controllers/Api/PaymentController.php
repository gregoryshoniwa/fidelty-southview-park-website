<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\Subscriber;
use App\Services\PaymentService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    private const RESIDENT_BILLERS = ['council', 'zesa', 'airtime', 'legal', 'school', 'dstv', 'merchant'];

    public function billers()
    {
        return response()->json(['live' => (bool) config('fspra.payments_live'), 'data' => collect(Payment::BILLERS)->only(self::RESIDENT_BILLERS)->map(fn ($b, $code) => [
            'code' => $code, 'label' => $b['label'], 'reference_label' => $b['reference'], 'fee' => number_format($b['fee'], 2),
        ])->values()]);
    }

    public function notifyMe(Request $request)
    {
        Subscriber::updateOrCreate(['phone' => $request->user()->phone], ['categories' => ['services', 'payments']]);

        return response()->json(['ok' => true, 'message' => 'We will send you an SMS when online payments open.']);
    }

    public function quote(Request $request)
    {
        $data = $request->validate([
            'biller_code' => ['required', Rule::in(self::RESIDENT_BILLERS)],
            'amount' => ['required', 'numeric'],
            'currency' => ['nullable', Rule::in(['USD', 'ZWG'])],
        ]);

        return response()->json(['data' => $this->payments->quote($data['biller_code'], (float) $data['amount'], $data['currency'] ?? 'USD')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'biller_code' => ['required', Rule::in(self::RESIDENT_BILLERS)],
            'biller_reference' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9 +\/-]+$/'],
            'amount' => ['required', 'numeric'],
            'currency' => ['nullable', Rule::in(['USD', 'ZWG'])],
            'request_reference' => ['nullable', 'string', 'max:20'],
        ]);
        $resident = $request->user()->resident;
        $req = null;
        if (! empty($data['request_reference'])) {
            $req = ServiceRequest::where('reference', $data['request_reference'])->where('resident_id', $resident->id)->firstOrFail();
        }
        $payment = $this->payments->create($resident, $data['biller_code'], $data['biller_reference'], (float) $data['amount'], $data['currency'] ?? 'USD', $req);
        $url = $this->payments->checkout($payment, url('/app/receipts?paid='.$payment->ulid));

        return response()->json(['data' => Present::payment($payment->fresh()), 'checkout_url' => $url], 201);
    }

    public function index(Request $request)
    {
        $items = $request->user()->resident->payments()->latest()->take(100)->get();

        return response()->json(['data' => $items->map(fn ($p) => Present::payment($p))]);
    }

    public function show(Request $request, Payment $payment)
    {
        abort_unless($payment->resident_id === $request->user()->resident?->id, 404);

        return response()->json(['data' => Present::payment($payment)]);
    }

    public function receipt(Request $request, Payment $payment)
    {
        abort_unless($payment->resident_id === $request->user()->resident?->id && $payment->status === 'paid', 404);
        $path = $payment->receipt_path ?: $this->payments->generateReceipt($payment);

        return Storage::disk('local')->download($path, 'receipt-'.$payment->ulid.'.pdf', ['Cache-Control' => 'private, no-store']);
    }
}
