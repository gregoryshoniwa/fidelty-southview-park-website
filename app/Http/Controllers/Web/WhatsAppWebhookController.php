<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppVerification;
use Illuminate\Http\Request;

/** Meta WhatsApp Cloud API webhook: only reads incoming "VERIFY 123456" texts. */
class WhatsAppWebhookController extends Controller
{
    /** Meta calls this once with hub.mode, hub.verify_token and hub.challenge when you save the webhook. */
    public function verify(Request $request)
    {
        $token = (string) config('fspra.whatsapp.verify_token');
        abort_unless($token !== '' && $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, WhatsAppVerification $wa)
    {
        $secret = (string) config('fspra.whatsapp.app_secret');
        $sent = (string) $request->header('X-Hub-Signature-256');
        abort_unless($secret !== '' && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $sent), 403);

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['messages'] ?? []) as $msg) {
                    if (($msg['type'] ?? '') === 'text' && isset($msg['from'], $msg['text']['body'])) {
                        $wa->receive((string) $msg['from'], mb_substr((string) $msg['text']['body'], 0, 200));
                    }
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
