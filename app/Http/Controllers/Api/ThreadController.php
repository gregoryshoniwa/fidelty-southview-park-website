<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Services\MessagingService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThreadController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    public function index(Request $request)
    {
        $threads = $request->user()->resident->threads()->with('partner', 'request')->orderByDesc('last_message_at')->get();

        return response()->json(['data' => $threads->map(fn ($t) => Present::thread($t))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Thread::CATEGORIES))],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $thread = $this->messaging->openThread($request->user()->resident, strip_tags($data['subject']), strip_tags($data['body']), null, null, $data['category']);

        return response()->json(['data' => Present::thread($thread)], 201);
    }

    public function show(Request $request, Thread $thread)
    {
        abort_unless($thread->resident_id === $request->user()->resident?->id, 404);
        $thread->messages()->whereNull('read_by_resident_at')->update(['read_by_resident_at' => now()]);
        $thread->load('messages', 'partner', 'request', 'resident.user');

        return response()->json(['data' => Present::thread($thread, true)]);
    }

    public function reply(Request $request, Thread $thread)
    {
        abort_unless($thread->resident_id === $request->user()->resident?->id, 404);
        abort_if($thread->status === 'closed', 422, 'This conversation is closed. Start a new one.');
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $this->messaging->post($thread, 'resident', $request->user()->id, strip_tags($data['body']));
        $thread->load('messages', 'partner', 'request', 'resident.user');

        return response()->json(['data' => Present::thread($thread, true)], 201);
    }
}
