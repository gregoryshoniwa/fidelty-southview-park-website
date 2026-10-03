<div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:bottom-6 sm:right-6 sm:left-auto sm:items-end" aria-live="polite" x-data="toasts" @if(session('toast')) data-initial="{{ json_encode(session('toast')) }}" @endif>
    <template x-for="t in items" x-bind:key="t.id">
        <div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-[10px] border border-line bg-white px-4 py-3 text-sm shadow-lift animate-rise" role="status">
            <span class="mt-0.5 size-2.5 shrink-0 rounded-full" x-bind:class="t.dot"></span>
            <p class="flex-1 font-semibold text-ink" x-text="t.message"></p>
            <button type="button" class="text-muted hover:text-ink" x-on:click="dismiss" x-bind:data-id="t.id" aria-label="Dismiss"><x-lucide name="x" class="size-4" /></button>
        </div>
    </template>
</div>
