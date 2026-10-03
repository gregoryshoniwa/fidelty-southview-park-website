<div id="assistant-root" data-signed-in="{{ auth()->check() ? '1' : '0' }}">
    <button type="button" id="assistant-launch" class="fixed bottom-4 right-4 z-50 inline-flex size-14 items-center justify-center gap-2 rounded-full border-[1.5px] border-gold-500 bg-forest-700 sm:size-auto sm:rounded-[8px] sm:px-4 sm:py-3 text-sm font-bold text-cream shadow-lift hover:bg-forest-600 sm:bottom-6 sm:right-6" aria-haspopup="dialog">
        <x-lucide name="message-circle-question" class="size-5 text-gold-400" />
        <span class="sr-only sm:not-sr-only">Ask the assistant</span>
    </button>
</div>
