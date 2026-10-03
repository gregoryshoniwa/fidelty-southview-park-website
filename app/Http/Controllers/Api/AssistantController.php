<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Services\AssistantService;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssistantController extends Controller
{
    public function __construct(private AssistantService $assistant) {}

    public function config()
    {
        return response()->json(['voice' => $this->assistant->enabled(), 'llm' => $this->assistant->enabled()]);
    }

    public function chat(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:800'], 'session_id' => ['nullable', 'string', 'max:64', 'alpha_dash']]);
        $sid = $data['session_id'] ?? (string) Str::ulid();
        $out = $this->assistant->answer($sid, strip_tags($data['message']), $request->user()?->id);

        return response()->json($out + ['session_id' => $sid]);
    }

    public function live(Request $request)
    {
        $request->validate(['consent' => ['accepted']]);
        if ($r = $request->user()?->resident) {
            $r->update(['consent_assistant_voice_at' => now()]);
        }
        $sid = (string) Str::ulid();
        $tok = $this->assistant->liveToken($sid, $request->user()?->id);
        if (! $tok) {
            return response()->json(['message' => 'Voice is not available right now. You can type instead.'], 503);
        }

        return response()->json($tok + ['session_id' => $sid]);
    }

    public function transcript(Request $request)
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:64', 'alpha_dash'], 'turns' => ['required', 'array', 'max:60'], 'turns.*.role' => ['required', 'in:user,assistant'], 'turns.*.text' => ['required', 'string', 'max:2000']]);
        $conv = AssistantConversation::where('session_id', $data['session_id'])->first();
        if ($conv && (! $conv->user_id || $conv->user_id === $request->user()?->id)) {
            $conv->update(['transcript' => array_merge($conv->transcript ?? [], $data['turns'])]);
        }

        return response()->json(['ok' => true]);
    }

    public function escalate(Request $request, MessagingService $messaging)
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:64', 'alpha_dash'], 'summary' => ['required', 'string', 'min:5', 'max:1500']]);
        $resident = $request->user()?->resident;
        abort_unless($resident, 401, 'Sign in so the committee can reply to you privately.');
        $conv = AssistantConversation::where('session_id', $data['session_id'])->first();
        $transcript = collect($conv?->transcript ?? [])->take(-12)->map(fn ($t) => strtoupper($t['role']).': '.$t['text'])->implode("\n");
        $thread = $messaging->openThread($resident, 'Question from the assistant', strip_tags($data['summary']).($transcript ? "\n\n--- Assistant transcript ---\n".$transcript : ''), null, null, 'assistant');
        $conv?->update(['escalated_thread_id' => $thread->id]);

        return response()->json(['reference' => $thread->reference], 201);
    }
}
