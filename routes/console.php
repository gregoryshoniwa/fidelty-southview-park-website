<?php

use App\Models\AssistantConversation;
use App\Models\AuditLog;
use App\Models\Notice;
use App\Models\OtpCode;
use App\Models\SmsLog;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\AssistantService;
use App\Services\LedgerService;
use App\Services\Phone;
use App\Services\SmsService;
use App\Services\WhatsAppVerification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('fspra:admin {phone} {email} {name} {--role=super_admin}', function (string $phone, string $email, string $name) {
    $p = Phone::normalise($phone) ?? throw new InvalidArgumentException('Invalid phone');
    $password = $this->secret('Password (min 12 characters)');
    if (strlen((string) $password) < 12) {
        return $this->error('Password must be at least 12 characters.');
    }
    $u = User::updateOrCreate(['phone' => $p], ['email' => $email, 'name' => $name, 'password' => $password, 'phone_verified_at' => now()]);
    $roles = $this->option('role') === 'super_admin' ? ['committee', 'finance_admin', 'super_admin'] : ['committee', $this->option('role')];
    $u->syncRoles(array_unique($roles));
    AuditLog::record('admin.created', $u, ['roles' => $roles]);
    $this->info("Committee user ready: {$email}. Sign in at /admin and set up two-factor authentication.");
})->purpose('Create or update a committee admin user');

Artisan::command('fspra:sms-flush', function (SmsService $sms) {
    SmsLog::where('status', 'queued')->where('created_at', '>=', now()->subDay())->each(fn ($l) => $sms->dispatch($l));
})->purpose('Send SMS held back during quiet hours');

Artisan::command('fspra:verify-ledger', function (LedgerService $ledger) {
    $r = $ledger->verifyChain();
    $r['ok'] ? $this->info('Ledger chain OK') : Log::critical('Ledger chain broken at entry '.$r['broken_at']);
})->purpose('Check the ledger hash chain');

Artisan::command('fspra:notice-sms', function (SmsService $sms) {
    foreach (Notice::published()->where('send_sms', true)->whereNull('notified_at')->get() as $n) {
        $n->update(['notified_at' => now()]);
        $text = 'Southview Park notice: '.$n->title.'. '.url('/notices/'.$n->slug);
        Subscriber::query()->each(function ($sub) use ($n, $sms, $text) {
            $cats = $sub->categories ?? [];
            if (! $cats || in_array($n->category, $cats, true) || $n->category === 'urgent') {
                $sms->send($sub->phone, $text, 'notice', $n->category === 'urgent');
            }
        });
        $this->info('Sent notice '.$n->slug);
    }
})->purpose('SMS newly published notices to subscribers');

Schedule::command('fspra:notice-sms')->everyFiveMinutes()->withoutOverlapping();
// On cPanel there is no long-running worker, so cron drains the queue each minute. Locally `composer dev` runs queue:listen instead.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping()->environments(['production', 'staging']);
Schedule::command('fspra:sms-flush')->dailyAt('07:05');
Schedule::command('fspra:verify-ledger')->dailyAt('02:30');
Schedule::call(fn () => app(AssistantService::class)->rebuildKnowledge())->hourly(); // also picks up notices scheduled to go live
Schedule::call(fn () => OtpCode::where('created_at', '<', now()->subDays(2))->delete())->daily();
Schedule::call(fn () => AssistantConversation::where('created_at', '<', now()->subDays(90))->delete())->daily();
Schedule::command('auth:clear-resets')->daily();

// Local testing without Meta: pretend a WhatsApp "VERIFY <code>" message arrived from <phone>.
Artisan::command('fspra:whatsapp-test {code} {phone}', function (string $code, string $phone) {
    if (app()->environment('production')) {
        $this->error('Not available in production.');

        return 1;
    }
    $c = app(WhatsAppVerification::class)->receive(ltrim((string) Phone::normalise($phone), '+'), 'VERIFY '.$code);
    $c ? $this->info("Code {$code} confirmed for {$c->phone}. The page will continue by itself.") : $this->error('No waiting code matches (wrong or expired code, or not a Zimbabwean number).');
})->purpose('Simulate an incoming WhatsApp verification message (local testing)');
