<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CommunityPage;
use App\Models\InAppNotification;
use App\Models\Incident;
use App\Models\Notice;
use App\Models\Order;
use App\Models\PagePost;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Poll;
use App\Models\Product;
use App\Models\SchoolInvoice;
use App\Models\SecuritySubscription;
use App\Models\Vote;
use App\Services\PaymentService;
use App\Services\Reference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CommunityController extends Controller
{
    public function notices(Request $request)
    {
        $q = Notice::published()->orderByDesc('pinned')->orderByDesc('published_at');
        if ($c = $request->query('category')) {
            $q->where('category', $c);
        }

        return response()->json(['data' => $q->take(50)->get()->map(fn ($n) => [
            'slug' => $n->slug, 'title' => $n->title, 'excerpt' => $n->excerpt, 'category' => $n->category,
            'signed_by' => $n->signed_by_role, 'pinned' => $n->pinned, 'published_at' => $n->published_at->toIso8601String(),
            'sponsored' => $n->category === 'sponsored',
        ]), 'categories' => (object) Notice::liveCategories()]);
    }

    public function polls(Request $request)
    {
        $resident = $request->user()->resident;
        $polls = Poll::where('opens_at', '<=', now())->latest('opens_at')->take(20)->get();

        return response()->json(['data' => $polls->map(function (Poll $p) use ($resident) {
            $mine = $resident?->stand_id ? Vote::where('poll_id', $p->id)->where('stand_id', $resident->stand_id)->value('option_index') : null;
            $showResults = $p->results_published_at !== null || $mine !== null;

            return [
                'id' => $p->id, 'question' => $p->question, 'description' => $p->description, 'options' => $p->options,
                'open' => $p->isOpen(), 'closes_at' => $p->closes_at->toIso8601String(), 'my_vote' => $mine,
                'results' => $showResults ? $p->tally() : null, 'voters' => $showResults ? $p->votes()->count() : null,
            ];
        })]);
    }

    public function vote(Request $request, Poll $poll)
    {
        $resident = $request->user()->resident;
        abort_unless($poll->isOpen(), 422, 'This poll is closed.');
        $data = $request->validate(['option' => ['required', 'integer', 'min:0', 'max:'.(count($poll->options) - 1)]]);
        if (Vote::where('poll_id', $poll->id)->where('stand_id', $resident->stand_id)->exists()) {
            return response()->json(['message' => 'Your stand has already voted in this poll.'], 422);
        }
        Vote::create(['poll_id' => $poll->id, 'stand_id' => $resident->stand_id, 'resident_id' => $resident->id, 'option_index' => $data['option']]);
        AuditLog::record('poll.voted', $poll);

        return response()->json(['ok' => true, 'results' => $poll->tally()], 201);
    }

    public function pages(Request $request)
    {
        $q = CommunityPage::where('active', true)->withCount('followers');
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        $followed = $request->user()?->id ? \DB::table('page_followers')->where('user_id', $request->user()->id)->pluck('community_page_id')->all() : [];

        return response()->json(['data' => $q->orderBy('name')->get()->map(fn ($p) => [
            'slug' => $p->slug, 'name' => $p->name, 'type' => $p->type, 'tagline' => $p->tagline, 'verified' => $p->verified,
            'logo' => $p->logo_path ? asset($p->logo_path) : null, 'cover' => $p->cover_path ? asset($p->cover_path) : null,
            'followers' => $p->followers_count, 'following' => in_array($p->id, $followed, true),
        ])]);
    }

    public function page(Request $request, CommunityPage $page)
    {
        abort_unless($page->active, 404);
        $page->loadCount('followers');

        return response()->json(['data' => [
            'slug' => $page->slug, 'name' => $page->name, 'type' => $page->type, 'tagline' => $page->tagline, 'description' => $page->description,
            'phone' => $page->phone, 'address' => $page->address, 'hours' => $page->hours, 'verified' => $page->verified,
            'logo' => $page->logo_path ? asset($page->logo_path) : null, 'cover' => $page->cover_path ? asset($page->cover_path) : null,
            'followers' => $page->followers_count,
            'following' => $page->followers()->where('user_id', $request->user()->id)->exists(),
            'posts' => $page->posts()->whereNull('hidden_at')->latest('published_at')->take(20)->get()->map(fn ($p) => ['id' => $p->id, 'body' => $p->body, 'image' => $p->image_path ? asset($p->image_path) : null, 'at' => $p->published_at?->toIso8601String()]),
            'products' => $page->products()->where('active', true)->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'price' => (string) $p->price, 'currency' => $p->currency, 'image' => $p->image_path ? asset($p->image_path) : null, 'in_stock' => $p->stock > 0]),
        ]]);
    }

    public function follow(Request $request, CommunityPage $page)
    {
        $page->followers()->syncWithoutDetaching([$request->user()->id]);

        return response()->json(['following' => true]);
    }

    public function unfollow(Request $request, CommunityPage $page)
    {
        $page->followers()->detach($request->user()->id);

        return response()->json(['following' => false]);
    }

    public function postReport(Request $request, PagePost $post)
    {
        if (Cache::add('report:'.$post->id.':'.$request->user()->id, 1, now()->addYear())) {
            $post->increment('reported_count');
        }
        AuditLog::record('post.reported', $post);

        return response()->json(['ok' => true]);
    }

    public function order(Request $request, CommunityPage $page, PaymentService $payments)
    {
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:20'], 'items.*.product_id' => ['required', 'integer'], 'items.*.qty' => ['required', 'integer', 'min:1', 'max:50']]);
        $resident = $request->user()->resident;
        $lines = [];
        $total = 0;
        foreach ($data['items'] as $it) {
            $p = Product::where('community_page_id', $page->id)->where('active', true)->findOrFail($it['product_id']);
            $lines[] = ['product_id' => $p->id, 'name' => $p->name, 'qty' => $it['qty'], 'price' => (string) $p->price];
            $total += $p->price * $it['qty'];
        }
        $order = Order::create(['reference' => Reference::next('ORD', 'orders'), 'resident_id' => $resident->id, 'community_page_id' => $page->id, 'items' => $lines, 'total' => $total, 'status' => 'pending']);
        $payment = $payments->create($resident, 'order', $order->reference, $total, 'USD', null, 'order');
        $order->update(['payment_id' => $payment->id]);
        $url = $payments->checkout($payment, url('/app/receipts?paid='.$payment->ulid));

        return response()->json(['reference' => $order->reference, 'checkout_url' => $url], 201);
    }

    public function invoices(Request $request)
    {
        $items = SchoolInvoice::where('resident_id', $request->user()->resident->id)->with('partner')->latest('due_on')->get();

        return response()->json(['data' => $items->map(fn ($i) => ['id' => $i->id, 'school' => $i->partner->name, 'learner' => $i->learner_name, 'description' => $i->description, 'amount' => (string) $i->amount, 'currency' => $i->currency, 'due_on' => $i->due_on->toDateString(), 'status' => $i->status])]);
    }

    public function payInvoice(Request $request, SchoolInvoice $invoice, PaymentService $payments)
    {
        $resident = $request->user()->resident;
        abort_unless($invoice->resident_id === $resident->id && $invoice->status !== 'paid', 404);
        if ($invoice->payment_id && Payment::whereKey($invoice->payment_id)->where('status', 'pending')->where('created_at', '>', now()->subMinutes(30))->exists()) {
            return response()->json(['message' => 'A payment for this invoice is already in progress.'], 409);
        }
        $payment = $payments->create($resident, 'school', $invoice->learner_ref, (float) $invoice->amount, $invoice->currency, null, 'school');
        $payment->update(['partner_id' => $invoice->partner_id]);
        $invoice->update(['payment_id' => $payment->id]);

        return response()->json(['checkout_url' => $payments->checkout($payment, url('/app/receipts?paid='.$payment->ulid))], 201);
    }

    public function security(Request $request)
    {
        $resident = $request->user()->resident;
        $companies = Partner::where('type', 'security')->where('active', true)->get();

        return response()->json([
            'companies' => $companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'logo' => $c->logoUrl(), 'plans' => $c->workflow_steps ?: [['name' => 'Rapid response', 'price' => '15.00']]]),
            'subscriptions' => SecuritySubscription::where('resident_id', $resident->id)->with('partner')->get()->map(fn ($s) => ['company' => $s->partner->name, 'plan' => $s->plan, 'price' => (string) $s->price, 'status' => $s->status, 'next_billing_on' => $s->next_billing_on?->toDateString()]),
            'incidents' => Incident::where('resident_id', $resident->id)->latest()->take(20)->get()->map(fn ($i) => ['reference' => $i->reference, 'category' => $i->category, 'status' => $i->status, 'at' => $i->created_at->toIso8601String()]),
        ]);
    }

    public function reportIncident(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(['break_in', 'suspicious', 'assault', 'fire', 'vandalism', 'other'])],
            'location' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:5', 'max:1500'],
            'partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')->where('type', 'security')],
        ]);
        $inc = Incident::create(['reference' => Reference::next('INC', 'incidents'), 'resident_id' => $request->user()->resident->id, 'partner_id' => $data['partner_id'] ?? null,
            'category' => $data['category'], 'location' => strip_tags((string) ($data['location'] ?? '')), 'description' => strip_tags($data['description'])]);
        AuditLog::record('incident.reported', $inc);

        return response()->json(['reference' => $inc->reference], 201);
    }

    public function notifications(Request $request)
    {
        $items = InAppNotification::where('user_id', $request->user()->id)->latest()->take(50)->get();

        return response()->json(['data' => $items->map(fn ($n) => ['id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'link' => $n->link, 'kind' => $n->kind, 'read' => (bool) $n->read_at, 'at' => $n->created_at->toIso8601String()]),
            'unread' => $items->whereNull('read_at')->count()]);
    }

    public function readNotifications(Request $request)
    {
        InAppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
