<footer class="bg-forest-900 text-cream/80">
    <div class="wrap grid gap-10 py-14 md:grid-cols-2 lg:grid-cols-4">
        <div class="flex flex-col gap-3">
            <img src="/images/logo-128.webp" width="64" height="64" alt="Fidelity Southview Park Residents Association logo" class="size-16 rounded-[8px] bg-white" loading="lazy">
            <p class="font-serif text-lg font-bold text-cream">Fidelity Southview Park Residents Association</p>
            <p class="text-sm leading-relaxed">Amalinda, Harare. A service-first association.<br>{{ config('fspra.site_domain') }}</p>
        </div>
        <div>
            <h2 class="eyebrow mb-3 font-sans text-gold-500">Services</h2>
            <ul class="space-y-2 text-sm">
                <li><a class="hover:text-gold-400" href="/app/verify">Verify my stand</a></li>
                <li><a class="hover:text-gold-400" href="{{ url('/services/my-agreement') }}">My Agreement of Sale</a></li>
                <li><a class="hover:text-gold-400" href="{{ url('/services/title-deed-tracker') }}">Title Deed Tracker</a></li>
                <li><a class="hover:text-gold-400" href="{{ url('/services/pay-bills') }}">Pay bills</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('services') }}">All services</a></li>
            </ul>
        </div>
        <div>
            <h2 class="eyebrow mb-3 font-sans text-gold-500">Association</h2>
            <ul class="space-y-2 text-sm">
                <li><a class="hover:text-gold-400" href="{{ route('about') }}">About and committee</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('constitution') }}">Constitution</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('notices') }}">Notice board</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('faq') }}">Questions and answers</a></li>
                <li><a class="hover:text-gold-400" href="/app/inbox/new">Write to the committee</a></li>
            </ul>
        </div>
        <div>
            <h2 class="eyebrow mb-3 font-sans text-gold-500">Advertise and legal</h2>
            <ul class="space-y-2 text-sm">
                <li><a class="hover:text-gold-400" href="{{ route('advertise') }}">Advertise with us</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('privacy') }}">Privacy and data protection</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('terms') }}">Terms of use</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('fees') }}">Fees and commissions</a></li>
                <li><a class="hover:text-gold-400" href="{{ route('complaints') }}">Complaints policy</a></li>
                <li><a class="hover:text-gold-400" href="/partner/login">Partner portal</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="wrap flex flex-col gap-2 py-6 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} Fidelity Southview Park Residents Association.</p>
            <p>Data controller licence: {{ \App\Models\Setting::get('potraz_licence', 'application in progress') }}</p>
        </div>
    </div>
</footer>
