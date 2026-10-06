<?php

namespace App\Services;

use App\Models\AssistantConversation;
use App\Models\CmsPage;
use App\Models\CommitteeMember;
use App\Models\CommunityPage;
use App\Models\Faq;
use App\Models\KnowledgeChunk;
use App\Models\Minute;
use App\Models\Notice;
use App\Models\Partner;
use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AssistantService
{
    /** Friendly names for resident app pages the assistant links to. */
    private const APP_PAGES = ['/app' => 'Resident app', '/app/verify' => 'Verify my stand', '/app/deed' => 'My deed file', '/app/inbox/new' => 'Write to the committee',
        '/app/notices' => 'Notices', '/app/pay' => 'Pay bills', '/app/agreement' => 'My Agreement of Sale', '/partner' => 'Partner sign-in'];

    /** Above this size the whole site no longer fits comfortably in each request; fall back to the best matches. */
    private const FULL_KNOWLEDGE_MAX_CHARS = 150000;

    public static function name(): string
    {
        return (string) config('fspra.assistant.name', 'Tariro');
    }

    public function systemPrompt(bool $webSearch): string
    {
        $name = self::name();
        $partners = $this->partnerNames()->implode(', ');
        $search = $webSearch
            ? "- Use search_web for questions about Fidelity Southview Park or a current partner ({$partners}) that the WEBSITE KNOWLEDGE does not answer, including anything recent: news, announcements, opening hours, branches or contact details. Put the place or partner name in the query. Never search for anything else, and never search for a private person.\n- When an answer comes from the web, say so in a few words and say the association's website and committee are the official source."
            : '- You have no internet access. If the WEBSITE KNOWLEDGE does not answer it, say you are not sure.';

        return <<<TXT
You are {$name}, the automated assistant of the Fidelity Southview Park Residents Association in Amalinda, Harare. Today is {$this->today()}.
Rules:
- In your first reply of a conversation introduce yourself as {$name}, an automated assistant; after that, do not introduce yourself again.
- Answer from the WEBSITE KNOWLEDGE below. It is everything published on the association's website and is the official source.
{$search}
- Only discuss Fidelity Southview Park, the association, its services and its partners. Politely decline anything else.
- If you cannot answer, say so and offer to pass the question to the committee ("Talk to a person").
- Never give legal or financial advice beyond what the knowledge says. Never discuss the association's internal finances or any resident's personal data, and never ask for ID numbers, PINs or passwords.
- You cannot change payments, verification, documents or deed status; point residents to the right page in the app.
- When you mention a page, give its path (for example /fees or /app/deed).
- Reply in the language the resident uses (English, Shona or Ndebele). Keep answers under 120 words, plain and friendly.
TXT;
    }

    /** Content changed: rebuild the knowledge before the next answer. */
    public static function markStale(): void
    {
        Cache::forever('assistant-knowledge-stale', true);
    }

    public function rebuildKnowledge(): int
    {
        $rows = [];
        $add = function (string $type, ?int $id, string $title, string $content, ?string $url) use (&$rows) {
            $content = trim(preg_replace('/[ \t]+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ["\n", "\n", "\n", "\n", "\n"], $content)))));
            if ($content !== '') {
                $rows[] = ['source_type' => $type, 'source_id' => $id, 'title' => Str::limit($title, 250), 'content' => $content, 'url' => $url];
            }
        };

        $add('site', null, 'About this website and where to do things', $this->siteGuide(), '/');
        foreach (Service::where('enabled', true)->with('partner')->orderBy('sort')->get() as $s) {
            $providers = $s->providerPartners()->pluck('name')->implode(', ') ?: $s->providerName();
            $add('service', $s->id, 'Service: '.$s->name, $s->summary."\nProvided by: {$providers}. Fee to residents: {$s->feeLabel()}. "
                .($s->requires_verification ? 'Requires a verified stand. ' : '').'Phase '.$s->phase.".\n".Str::sanitizeHtml((string) $s->body), '/services/'.$s->slug);
        }
        foreach (Partner::where('active', true)->orderBy('name')->get() as $p) {
            $add('partner', $p->id, 'Partner: '.$p->name, collect([
                'Type: '.(Partner::TYPES[$p->type] ?? $p->type),
                $p->website ? 'Website: '.$p->website : null,
                $p->address ? 'Address: '.$p->address : null,
                $p->contact_phone ? 'Phone: '.$p->contact_phone : null,
                $p->contact_email ? 'Email: '.$p->contact_email : null,
                $p->workflow_steps ? 'Steps on a file with them: '.implode(' → ', $p->workflow_steps) : null,
            ])->filter()->implode("\n"), null);
        }
        foreach (Faq::where('published', true)->orderBy('topic')->orderBy('sort')->get() as $f) {
            $add('faq', $f->id, 'Question: '.$f->question, $f->answer, '/faq#faq-'.$f->id);
        }
        foreach (Notice::published()->where('category', '!=', 'sponsored')->latest('published_at')->take(40)->get() as $n) {
            $add('notice', $n->id, 'Notice ('.$n->published_at->format('j F Y').'): '.$n->title, ($n->signed_by_role ? 'Signed: '.$n->signed_by_role."\n" : '').Str::sanitizeHtml((string) $n->body), '/notices/'.$n->slug);
        }
        foreach (CmsPage::where('published', true)->get() as $p) {
            $add('page', $p->id, 'Page: '.$p->title, Str::sanitizeHtml((string) $p->body), '/'.$p->slug);
        }
        $members = CommitteeMember::where('public', true)->orderBy('sort')->get();
        if ($members->isNotEmpty()) {
            $add('committee', null, 'The committee', $members->map(fn ($m) => "{$m->name}: {$m->role}".($m->area ? " ({$m->area})" : '').($m->bio ? '. '.$m->bio : ''))->implode("\n"), '/about');
        }
        foreach (Minute::whereNotNull('published_at')->latest('meeting_date')->take(12)->get() as $m) {
            $add('minutes', $m->id, 'Minutes: '.$m->title.($m->meeting_date ? ' ('.$m->meeting_date->format('j F Y').')' : ''), Str::limit(strip_tags((string) $m->body), 4000), '/about/minutes/'.$m->id);
        }
        foreach (CommunityPage::where('active', true)->orderBy('name')->get() as $c) {
            $add('community', $c->id, (CommunityPage::TYPES[$c->type] ?? 'Community').': '.$c->name, collect([
                $c->tagline, $c->description, $c->address ? 'Address: '.$c->address : null, $c->phone ? 'Phone: '.$c->phone : null,
                $c->verified ? 'Verified by the association.' : null,
            ])->filter()->implode("\n"), '/community/'.$c->slug);
        }

        DB::transaction(function () use ($rows) {
            KnowledgeChunk::query()->delete();
            foreach (array_chunk($rows, 100) as $batch) {
                KnowledgeChunk::insert(array_map(fn ($r) => $r + ['created_at' => now(), 'updated_at' => now()], $batch));
            }
        });
        Cache::forget('assistant-knowledge-stale');
        Cache::forget('assistant-knowledge');

        return count($rows);
    }

    /** Everything published on the website, as one text block for the model. */
    public function knowledge(): string
    {
        if (Cache::get('assistant-knowledge-stale') || ! KnowledgeChunk::exists()) {
            $this->rebuildKnowledge();
        }

        return Cache::rememberForever('assistant-knowledge', fn () => KnowledgeChunk::orderBy('id')->get()
            ->map(fn ($c) => "## {$c->title}\n{$c->content}".($c->url ? "\nPage: {$c->url}" : ''))->implode("\n\n"));
    }

    private function siteGuide(): string
    {
        $channel = config('fspra.whatsapp.channel_url');
        $lines = [
            'Website: '.config('app.url').'. The association is a residents association for Fidelity Southview Park, Amalinda, Harare. It charges no levy or membership fee.',
            'Public pages: home /, services /services, fees /fees, notices /notices, community directory /community, about the association and committee /about, questions and answers /faq, advertising /advertise, privacy /privacy, terms /terms, complaints /complaints.',
            'Resident app (sign in with Google, an email link or WhatsApp): /app. Verify your stand with Fidelity Life records: /app/verify. Title deed file: /app/deed. Write to the committee privately: /app/inbox/new. Notices: /app/notices.',
            'Partner staff sign in at /partner. Committee members sign in at /admin.',
            'Official notices are dated and signed by a committee role. There is no group chat; residents write to the committee privately and get a reference number.',
            $channel ? "Residents can follow official notices on the association's WhatsApp channel: {$channel}" : null,
            'Commitments: fees printed before every payment; two signatories on every payment out; minutes published within 7 days; independent review of the accounts each year; conflict of interest register; founding committee term ends after 12 months, then residents vote.',
        ];

        return implode("\n", array_filter($lines));
    }

    private function partnerNames(): Collection
    {
        return Partner::where('active', true)->orderBy('name')->pluck('name');
    }

    private function today(): string
    {
        return now()->format('l j F Y');
    }

    /** @return Collection<int, KnowledgeChunk> */
    public function retrieve(string $query, int $k = 5)
    {
        $q = trim(preg_replace('/[^\pL\pN\s]/u', ' ', $query));
        if ($q === '') {
            return collect();
        }
        $hits = KnowledgeChunk::whereFullText(['title', 'content'], $q)->take($k)->get();
        if ($hits->isEmpty()) {
            $words = collect(explode(' ', $q))->filter(fn ($w) => mb_strlen($w) > 3)->take(6);
            $hits = KnowledgeChunk::where(function ($w) use ($words) {
                foreach ($words as $word) {
                    $w->orWhere('content', 'like', "%{$word}%")->orWhere('title', 'like', "%{$word}%");
                }
            })->take($k)->get();
        }

        return $hits;
    }

    public function contextFor(string $query): string
    {
        $knowledge = $this->knowledge();
        if (mb_strlen($knowledge) <= self::FULL_KNOWLEDGE_MAX_CHARS) {
            return $knowledge;
        }

        return $this->retrieve($query, 12)->map(fn ($c) => "## {$c->title}\n{$c->content}".($c->url ? "\nPage: {$c->url}" : ''))->implode("\n\n");
    }

    public function enabled(): bool
    {
        return filled(config('fspra.gemini.api_key')) && ! $this->overCap();
    }

    public function overCap(): bool
    {
        $used = AssistantConversation::whereDate('created_at', today())->sum(\DB::raw('tokens_in + tokens_out'));

        return $used >= config('fspra.gemini.daily_token_cap');
    }

    /** Web search is offered while switched on, under today's cap, and not refused by Google in the last half hour. */
    public function webSearchAvailable(): bool
    {
        return $this->enabled() && config('fspra.assistant.web_search')
            && ! Cache::has('assistant-web-unavailable')
            && (int) Cache::get('assistant-web-day:'.today()->toDateString(), 0) < (int) config('fspra.assistant.daily_web_searches');
    }

    public static function searchTool(): array
    {
        return ['functionDeclarations' => [[
            'name' => 'search_web',
            'description' => 'Search the internet for current information about Fidelity Southview Park (Amalinda, Harare) or one of the association\'s current partners. Only use it when the website knowledge does not answer the question.',
            'parameters' => ['type' => 'object', 'properties' => [
                'query' => ['type' => 'string', 'description' => 'What to look up. Must name Fidelity Southview Park or the partner, e.g. "Marufu Attorneys Harare office hours".'],
            ], 'required' => ['query']],
        ]]];
    }

    /**
     * Searches the web, but only about Southview Park or a current partner: the scope is enforced here, not left to the model.
     *
     * @return array{summary?: string, sources?: list<array{title: string, url: string}>, error?: string}
     */
    public function searchWeb(string $query): array
    {
        $query = Str::limit(trim(strip_tags($query)), 200, '');
        if (! $this->inScope($query)) {
            return ['error' => 'Out of scope: only Fidelity Southview Park and the association\'s current partners can be searched.'];
        }
        if (! $this->webSearchAvailable()) {
            return ['error' => 'Web search is not available right now.'];
        }
        $day = 'assistant-web-day:'.today()->toDateString();
        Cache::add($day, 0, 86400);
        Cache::increment($day);

        $res = $this->generate([
            'systemInstruction' => ['parts' => [['text' => 'Search the web and report only facts about the subject of the query: Fidelity Southview Park in Amalinda, Harare, or the named organisation ('.$this->partnerNames()->implode(', ').'). Ignore unrelated results and anything about private individuals. Give dates where known. At most 120 words, no speculation.']]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $query]]]],
            'tools' => [['google_search' => (object) []]],
            'generationConfig' => ['maxOutputTokens' => 2048, 'temperature' => 0.2, 'thinkingConfig' => ['thinkingLevel' => 'low']],
        ]);
        if (! $res?->successful()) {
            if ($res?->status() === 429) {
                Cache::put('assistant-web-unavailable', true, now()->addMinutes(30));
            }
            report(new \RuntimeException('Gemini web search error '.($res?->status() ?? 'timeout')));

            return ['error' => 'Web search is not available right now.'];
        }

        return [
            'summary' => $this->text($res->json()),
            'sources' => collect($res->json('candidates.0.groundingMetadata.groundingChunks') ?? [])
                ->filter(fn ($c) => filled($c['web']['uri'] ?? null))
                ->map(fn ($c) => ['title' => (string) ($c['web']['title'] ?? 'Web result'), 'url' => (string) $c['web']['uri']])
                ->unique('url')->take(4)->values()->all(),
        ];
    }

    /** The query must name Southview Park or a current partner (by a distinctive word of its name). */
    public function inScope(string $query): bool
    {
        $q = Str::lower($query);
        if (Str::contains($q, ['southview', 'south view'])) {
            return true;
        }
        $generic = ['and', 'the', 'associates', 'attorneys', 'legal', 'practitioners', 'partners', 'bank', 'assurance', 'life', 'company', 'limited', 'ltd', 'law', 'firm', 'school', 'security'];

        return $this->partnerNames()->contains(function ($name) use ($q, $generic) {
            if (Str::contains($q, Str::lower($name))) {
                return true;
            }
            $words = collect(preg_split('/[^\pL\pN]+/u', Str::lower($name)))->filter(fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, $generic, true));

            return $words->isNotEmpty() && $words->contains(fn ($w) => preg_match('/\b'.preg_quote($w, '/').'\b/u', $q));
        });
    }

    /** Text answer. Uses Gemini when configured, otherwise returns the best knowledge-base matches. */
    public function answer(string $sessionId, string $question, ?int $userId = null): array
    {
        $conv = AssistantConversation::firstOrCreate(['session_id' => $sessionId], ['user_id' => $userId, 'mode' => 'text', 'transcript' => []]);
        if ($conv->user_id !== $userId) {
            $conv = AssistantConversation::create(['session_id' => (string) Str::ulid(), 'user_id' => $userId, 'mode' => 'text', 'transcript' => []]);
        }
        $this->knowledge(); // rebuild first if the website changed
        $hits = $this->retrieve($question);
        $sources = $hits->map(fn ($c) => ['title' => $c->title, 'url' => $c->url])->filter(fn ($s) => $s['url'])->values()->all();

        if (! $this->enabled()) {
            $reply = $hits->isEmpty()
                ? "I'm ".self::name().', the Southview assistant. I could not find that in our help pages. Tap "Talk to a person" and the committee will reply privately.'
                : "I'm ".self::name().", the Southview assistant. Here is what our help pages say:\n\n".$hits->take(2)->map(fn ($c) => '**'.$c->title.'**: '.Str::limit($c->content, 260))->implode("\n\n");
            $this->append($conv, $question, $reply, 0, 0);

            return ['reply' => $reply, 'sources' => $sources, 'escalate_suggested' => $hits->isEmpty()];
        }

        $webSearch = $this->webSearchAvailable();
        $contents = collect($conv->transcript ?? [])->take(-8)->map(fn ($t) => ['role' => $t['role'] === 'user' ? 'user' : 'model', 'parts' => [['text' => $t['text']]]])->values()->all();
        $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];
        $body = [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt($webSearch)."\n\nWEBSITE KNOWLEDGE:\n".$this->contextFor($question)]]],
            // Gemini 3 spends part of the output budget on thinking: keep thinking low and leave room for the reply.
            'generationConfig' => ['maxOutputTokens' => 2048, 'temperature' => 0.3, 'thinkingConfig' => ['thinkingLevel' => 'low']],
        ] + ($webSearch ? ['tools' => [self::searchTool()]] : []);

        $in = $out = 0;
        $webSources = [];
        for ($round = 0; $round < 3; $round++) {
            $res = $this->generate($body + ['contents' => $contents]);
            if (! $res?->successful()) {
                report(new \RuntimeException('Gemini error '.($res?->status() ?? 'timeout')));

                return ['reply' => 'The assistant is unavailable right now. Tap "Talk to a person" to reach the committee.', 'sources' => $sources, 'escalate_suggested' => true];
            }
            $in += max(0, (int) $res->json('usageMetadata.promptTokenCount', 0) - (int) $res->json('usageMetadata.cachedContentTokenCount', 0));
            $out += (int) $res->json('usageMetadata.candidatesTokenCount', 0);
            $calls = collect($res->json('candidates.0.content.parts') ?? [])->pluck('functionCall')->filter()->values();
            if ($calls->isEmpty() || $round === 2) {
                break;
            }
            // Return the model's turn unchanged (Gemini 3 needs its thought signatures back), then the search results.
            $contents[] = $res->json('candidates.0.content');
            $contents[] = ['role' => 'user', 'parts' => $calls->map(function ($call) use (&$webSources) {
                $result = ($call['name'] ?? '') === 'search_web' ? $this->searchWeb((string) ($call['args']['query'] ?? '')) : ['error' => 'Unknown tool.'];
                $webSources = array_merge($webSources, $result['sources'] ?? []);

                return ['functionResponse' => ['name' => $call['name'] ?? 'search_web', 'response' => $result]];
            })->all()];
        }
        $reply = $this->text($res->json()) ?: 'Sorry, I could not answer that. Tap "Talk to a person" to reach the committee.';
        $this->append($conv, $question, $reply, $in, $out);

        return ['reply' => $reply, 'sources' => array_values(array_merge($webSources, $this->pagesMentioned($reply))), 'escalate_suggested' => false];
    }

    /** Links for the site pages an answer points to (by path), so the chips match the answer. */
    private function pagesMentioned(string $reply): array
    {
        preg_match_all('#(?<![\w.])(/[a-z0-9][a-z0-9/_-]*)#i', $reply, $m);
        $paths = collect($m[1])->map(fn ($p) => rtrim($p, '/.'))->unique()->take(3);
        $titles = KnowledgeChunk::whereIn('url', $paths)->pluck('title', 'url');

        return $paths->map(fn ($p) => ['title' => self::APP_PAGES[$p] ?? Str::after($titles[$p] ?? Str::headline(Str::afterLast($p, '/')), ': '), 'url' => $p])->values()->all();
    }

    /** Calls the main text model, then the lighter fallback when Google is busy (429/5xx), slow, or sends back an empty reply. */
    private function generate(array $body): ?Response
    {
        $res = null;
        foreach (array_unique(array_filter([config('fspra.gemini.text_model'), config('fspra.gemini.text_fallback_model')])) as $model) {
            try {
                $res = Http::timeout(30)->withHeaders(['x-goog-api-key' => config('fspra.gemini.api_key')])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', $body);
            } catch (ConnectionException) {
                $res = null;
            }
            $empty = $res?->successful() && ! collect($res->json('candidates.0.content.parts') ?? [])->contains(fn ($p) => filled($p['text'] ?? null) || isset($p['functionCall']));
            if ($res && ! $empty && ! in_array($res->status(), [429, 500, 503], true)) {
                break;
            }
        }

        return $res;
    }

    private function text(array $json): string
    {
        return trim(collect(data_get($json, 'candidates.0.content.parts', []))
            ->reject(fn ($p) => $p['thought'] ?? false)->pluck('text')->filter()->implode(''));
    }

    /** Ephemeral token for the browser to open a Gemini Live (voice) session directly. */
    public function liveToken(string $sessionId, ?int $userId): ?array
    {
        if (! $this->enabled() || ! Cache::add('live-day:'.today()->toDateString(), 0, 86400) && Cache::increment('live-day:'.today()->toDateString()) > (int) config('fspra.gemini.daily_live_sessions')) {
            return null;
        }
        $webSearch = $this->webSearchAvailable();
        $system = $this->systemPrompt($webSearch)."\n- You are speaking aloud: keep answers short and do not read out web addresses in full.\n\nWEBSITE KNOWLEDGE:\n".$this->contextFor('services fees verify deed pay notices partners');
        $setup = [
            'model' => 'models/'.config('fspra.gemini.live_model'),
            'generationConfig' => ['responseModalities' => ['AUDIO'], 'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => config('fspra.assistant.voice')]]]],
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'inputAudioTranscription' => (object) [],
            'outputAudioTranscription' => (object) [],
        ] + ($webSearch ? ['tools' => [self::searchTool()]] : []);
        $res = Http::timeout(15)->withHeaders(['x-goog-api-key' => config('fspra.gemini.api_key')])->post(
            'https://generativelanguage.googleapis.com/v1alpha/auth_tokens',
            [
                'uses' => 1,
                'expireTime' => now()->addMinutes(30)->toIso8601ZuluString(),
                'newSessionExpireTime' => now()->addMinutes(2)->toIso8601ZuluString(),
                // Lock the token to our model, voice, instructions and tools so it cannot be reused for anything else.
                'bidiGenerateContentSetup' => $setup,
            ]
        );
        if (! $res->successful()) {
            report(new \RuntimeException('Gemini token error '.$res->status()));

            return null;
        }
        AssistantConversation::firstOrCreate(['session_id' => $sessionId], ['user_id' => $userId, 'mode' => 'voice', 'transcript' => []]);

        return [
            'token' => $res->json('name'),
            'model' => config('fspra.gemini.live_model'),
            'setup' => $setup,
        ];
    }

    private function append(AssistantConversation $conv, string $q, string $a, int $in, int $out): void
    {
        $t = $conv->transcript ?? [];
        $t[] = ['role' => 'user', 'text' => Str::limit($q, 1000), 'at' => now()->toIso8601String()];
        $t[] = ['role' => 'assistant', 'text' => $a, 'at' => now()->toIso8601String()];
        $conv->update(['transcript' => $t, 'tokens_in' => $conv->tokens_in + $in, 'tokens_out' => $conv->tokens_out + $out]);
    }
}
