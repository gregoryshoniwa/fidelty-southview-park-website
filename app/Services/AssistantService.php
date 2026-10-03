<?php

namespace App\Services;

use App\Models\AssistantConversation;
use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\KnowledgeChunk;
use App\Models\Notice;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AssistantService
{
    public const SYSTEM_PROMPT = <<<'TXT'
You are the Southview assistant for the Fidelity Southview Park Residents Association in Amalinda, Harare.
Rules:
- On first contact say you are an automated assistant.
- Answer ONLY from the CONTEXT provided. If the answer is not in the context, say you are not sure and offer to pass the question to the committee inbox.
- Never give legal or financial advice beyond what the context says. Never discuss the association's finances or any resident's personal data.
- You cannot change payments, verification, documents or deed status.
- Reply in the language the resident uses (English, Shona or Ndebele). Keep answers under 120 words, plain and friendly.
TXT;

    public function rebuildKnowledge(): int
    {
        KnowledgeChunk::query()->delete();
        $n = 0;
        foreach (Faq::where('published', true)->get() as $f) {
            KnowledgeChunk::create(['source_type' => 'faq', 'source_id' => $f->id, 'title' => $f->question, 'content' => strip_tags($f->answer), 'url' => '/faq#faq-'.$f->id]);
            $n++;
        }
        foreach (Service::where('enabled', true)->with('partner')->get() as $s) {
            $content = $s->summary.' Fee: '.$s->feeLabel().'. Partner: '.($s->partner?->name ?? 'Association').'. '.strip_tags((string) $s->body);
            KnowledgeChunk::create(['source_type' => 'service', 'source_id' => $s->id, 'title' => $s->name, 'content' => Str::limit($content, 3000), 'url' => '/services/'.$s->slug]);
            $n++;
        }
        foreach (Notice::published()->latest('published_at')->take(30)->get() as $no) {
            KnowledgeChunk::create(['source_type' => 'notice', 'source_id' => $no->id, 'title' => $no->title, 'content' => Str::limit(strip_tags(Str::sanitizeHtml((string) $no->body)), 2000), 'url' => '/notices/'.$no->slug]);
            $n++;
        }
        foreach (CmsPage::where('published', true)->get() as $p) {
            foreach (str_split(strip_tags($p->body), 1800) as $i => $part) {
                KnowledgeChunk::create(['source_type' => 'page', 'source_id' => $p->id, 'title' => $p->title.($i ? " ({$i})" : ''), 'content' => $part, 'url' => '/'.$p->slug]);
                $n++;
            }
        }

        return $n;
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
        return $this->retrieve($query)->map(fn ($c) => "## {$c->title}\n{$c->content}\nSource: {$c->url}")->implode("\n\n");
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

    /** Text answer. Uses Gemini when configured, otherwise returns the best knowledge-base matches. */
    public function answer(string $sessionId, string $question, ?int $userId = null): array
    {
        $conv = AssistantConversation::firstOrCreate(['session_id' => $sessionId], ['user_id' => $userId, 'mode' => 'text', 'transcript' => []]);
        if ($conv->user_id !== $userId) {
            $conv = AssistantConversation::create(['session_id' => (string) Str::ulid(), 'user_id' => $userId, 'mode' => 'text', 'transcript' => []]);
        }
        $hits = $this->retrieve($question);
        $sources = $hits->map(fn ($c) => ['title' => $c->title, 'url' => $c->url])->values()->all();

        if (! $this->enabled()) {
            $reply = $hits->isEmpty()
                ? "I'm the Southview assistant. I could not find that in our help pages. Tap \"Talk to a person\" and the committee will reply privately."
                : "I'm the Southview assistant. Here is what our help pages say:\n\n".$hits->take(2)->map(fn ($c) => '**'.$c->title.'**: '.Str::limit($c->content, 260))->implode("\n\n");
            $this->append($conv, $question, $reply, 0, 0);

            return ['reply' => $reply, 'sources' => $sources, 'escalate_suggested' => $hits->isEmpty()];
        }

        $history = collect($conv->transcript ?? [])->take(-8)->map(fn ($t) => ['role' => $t['role'] === 'user' ? 'user' : 'model', 'parts' => [['text' => $t['text']]]])->values()->all();
        $context = $this->contextFor($question);
        $res = Http::timeout(25)->withHeaders(['x-goog-api-key' => config('fspra.gemini.api_key')])->post(
            'https://generativelanguage.googleapis.com/v1beta/models/'.config('fspra.gemini.text_model').':generateContent',
            [
                'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT."\n\nCONTEXT:\n".($context ?: '(no matching help pages)')]]],
                'contents' => array_merge($history, [['role' => 'user', 'parts' => [['text' => $question]]]]),
                'generationConfig' => ['maxOutputTokens' => 400, 'temperature' => 0.3],
            ]
        );
        if (! $res->successful()) {
            report(new \RuntimeException('Gemini error '.$res->status()));

            return ['reply' => 'The assistant is unavailable right now. Tap "Talk to a person" to reach the committee.', 'sources' => $sources, 'escalate_suggested' => true];
        }
        $reply = (string) data_get($res->json(), 'candidates.0.content.parts.0.text', 'Sorry, I could not answer that.');
        $this->append($conv, $question, $reply, (int) data_get($res->json(), 'usageMetadata.promptTokenCount', 0), (int) data_get($res->json(), 'usageMetadata.candidatesTokenCount', 0));

        return ['reply' => $reply, 'sources' => $sources, 'escalate_suggested' => $context === ''];
    }

    /** Ephemeral token for the browser to open a Gemini Live (voice) session directly. */
    public function liveToken(string $sessionId, ?int $userId): ?array
    {
        if (! $this->enabled() || ! Cache::add('live-day:'.today()->toDateString(), 0, 86400) && Cache::increment('live-day:'.today()->toDateString()) > (int) config('fspra.gemini.daily_live_sessions')) {
            return null;
        }
        $system = self::SYSTEM_PROMPT."\n\nCONTEXT:\n".$this->retrieve('services fees verify deed pay notices', 8)->map(fn ($c) => "## {$c->title}\n".Str::limit($c->content, 500))->implode("\n\n");
        $res = Http::timeout(15)->withHeaders(['x-goog-api-key' => config('fspra.gemini.api_key')])->post(
            'https://generativelanguage.googleapis.com/v1alpha/auth_tokens',
            [
                'uses' => 1,
                'expireTime' => now()->addMinutes(30)->toIso8601ZuluString(),
                'newSessionExpireTime' => now()->addMinutes(2)->toIso8601ZuluString(),
                // Lock the token to our model and instructions so it cannot be reused for anything else.
                'bidiGenerateContentSetup' => [
                    'model' => 'models/'.config('fspra.gemini.live_model'),
                    'generationConfig' => ['responseModalities' => ['AUDIO']],
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'inputAudioTranscription' => (object) [],
                    'outputAudioTranscription' => (object) [],
                ],
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
            'system_instruction' => $system,
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
