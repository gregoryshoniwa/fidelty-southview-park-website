<div x-show="subscribeOpen" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-labelledby="sub-title" x-on:keydown.escape.window="closeSubscribe">
    <div class="absolute inset-0 bg-forest-950/60 backdrop-blur-sm animate-fade" x-on:click="closeSubscribe"></div>
    <form method="POST" action="{{ route('subscribe') }}" class="relative w-full max-w-md animate-rise rounded-t-[16px] bg-white p-6 shadow-2xl sm:rounded-[14px]">
        @csrf
        <button type="button" class="absolute right-3 top-3 inline-flex size-9 items-center justify-center rounded-[6px] text-muted hover:bg-sand" x-on:click="closeSubscribe" aria-label="Close"><x-lucide name="x" class="size-5" /></button>
        <span class="flex size-11 items-center justify-center rounded-[8px] bg-forest-100 text-forest-700"><x-lucide name="message-square-text" class="size-6" /></span>
        <h2 id="sub-title" class="mt-4 font-serif text-2xl font-bold text-forest-900">Official notices by SMS</h2>
        <p class="mt-1 text-sm text-muted">Urgent, deeds, services and security notices. Never adverts. Reply STOP any time.</p>
        <label for="sub-phone" class="label mt-5">Mobile number</label>
        <input id="sub-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="077 123 4567" class="input" value="{{ old('phone') }}">
        @error('phone')<p class="error">{{ $message }}</p>@enderror
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <button type="submit" class="btn btn-gold mt-5 w-full">Subscribe</button>
        <p class="help mt-3 text-center">We use your number only to send official notices. <a href="{{ route('privacy') }}" class="underline">Privacy</a></p>
    </form>
</div>
