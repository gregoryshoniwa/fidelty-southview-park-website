<div id="assistant-root" data-signed-in="{{ auth()->check() ? '1' : '0' }}">
    <button type="button" id="assistant-launch" class="fixed bottom-4 right-4 z-50 inline-flex size-14 items-center justify-center rounded-full border-[1.5px] border-gold-500 bg-forest-700 text-cream shadow-lift transition hover:scale-105 hover:bg-forest-600 sm:bottom-6 sm:right-6" title="Ask {{ \App\Services\AssistantService::name() }}, the assistant" aria-haspopup="dialog">
        <x-lucide name="message-circle-question" class="size-6 text-gold-400" />
        <span class="sr-only">Ask {{ \App\Services\AssistantService::name() }}, the assistant</span>
    </button>
</div>
