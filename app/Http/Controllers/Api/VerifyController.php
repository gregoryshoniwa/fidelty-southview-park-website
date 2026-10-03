<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VerificationService;
use App\Support\Present;
use Illuminate\Http\Request;

class VerifyController extends Controller
{
    public function __construct(private VerificationService $verify) {}

    public function start(Request $request)
    {
        $data = $request->validate([
            'national_id' => ['required', 'string', 'max:20', 'regex:/^\d{2}[\s-]?\d{6,7}[\s-]?[A-Za-z][\s-]?\d{2}$/'],
            'stand_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\/-]+$/'],
            'consent' => ['accepted'],
        ], ['national_id.regex' => 'Use the format 63-123456-A-12.', 'consent.accepted' => 'You must agree before we contact Fidelity Life.']);

        $resident = $this->verify->start($request->user(), $data['national_id'], $data['stand_number']);

        return response()->json(['status' => 'pending', 'phone_on_file_masked' => $resident->phone_on_file_masked,
            'dev_code' => app()->environment('local') ? cache('otp:last:'.$request->user()->phone.':verify') : null]);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $this->verify->confirm($request->user(), $data['code']);

        return response()->json(['user' => Present::user($request->user()->fresh())]);
    }
}
